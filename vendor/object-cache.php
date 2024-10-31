<?php
/*
Description: Runcache Purger Redis Object Cache Drop-In. A persistent object cache backend powered by Redis.


Original source http://plugins.svn.wordpress.org/redis-cache/trunk/includes/object-cache.php
*/

if (! defined('WP_REDIS_DISABLED') || ! WP_REDIS_DISABLED || !defined('RUNCACHE_PURGER_DROPIN') ) :

define('RUNCACHE_PURGER_DROPIN', true);
!defined('WP_REDIS_SELECTIVE_FLUSH') && define('WP_REDIS_SELECTIVE_FLUSH', true);
!defined('RUNCACHE_PURGER_PATH_VENDOR') && define('RUNCACHE_PURGER_PATH_VENDOR', ( defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR : WP_CONTENT_DIR . '/plugins/runcache-purger/vendor/' ) );

/*@CONFIG-MARKER@*/

function wp_cache_is_redis_connect()
{
    static $__redis_is_connect = false;

    if ( $__redis_is_connect ) {
        return true;
    }

    if ( !function_exists('__redis_is_connect') && defined('RUNCACHE_PURGER_DROPIN_HOST') && defined('RUNCACHE_PURGER_DROPIN_PORT') ) {
        $file = RUNCACHE_PURGER_PATH_VENDOR.'redis_is_connect.php';
        if ( file_exists($file) ) {
            include_once($file);

            if ( function_exists('__redis_is_connect') ) {
                if ( __redis_is_connect(RUNCACHE_PURGER_DROPIN_HOST, RUNCACHE_PURGER_DROPIN_PORT) ) {
                    $__redis_is_connect = true;
                    return true;
                }
            }
        }
    }

    $__redis_is_connect = false;
    return false;
}

if ( wp_cache_is_redis_connect() ):

function wp_cache_add($key, $value, $group = '', $expiration = 0)
{
    global $wp_object_cache;

    return $wp_object_cache->add($key, $value, $group, $expiration);
}

function wp_cache_close()
{
    return true;
}

function wp_cache_decr($key, $offset = 1, $group = '')
{
    global $wp_object_cache;

    return $wp_object_cache->decrement($key, $offset, $group);
}

function wp_cache_delete($key, $group = '')
{
    global $wp_object_cache;

    return $wp_object_cache->delete($key, $group);
}

function wp_cache_flush($delay = 0)
{
    global $wp_object_cache;

    return $wp_object_cache->flush($delay);
}

function wp_cache_get($key, $group = '', $force = false, &$found = null)
{
    global $wp_object_cache;

    return $wp_object_cache->get($key, $group, $force, $found);
}

function wp_cache_get_multi($groups)
{
    global $wp_object_cache;

    return $wp_object_cache->get_multi($groups);
}

function wp_cache_incr($key, $offset = 1, $group = '')
{
    global $wp_object_cache;

    return $wp_object_cache->increment($key, $offset, $group);
}

function wp_cache_init()
{
    global $wp_object_cache;

    if (! ($wp_object_cache instanceof WP_Object_Cache)) {
        $fail_gracefully = ! defined('WP_REDIS_GRACEFUL') || WP_REDIS_GRACEFUL;

        $wp_object_cache = new WP_Object_Cache($fail_gracefully);
    }
}

function wp_cache_replace($key, $value, $group = '', $expiration = 0)
{
    global $wp_object_cache;

    return $wp_object_cache->replace($key, $value, $group, $expiration);
}

function wp_cache_set($key, $value, $group = '', $expiration = 0)
{
    global $wp_object_cache;

    return $wp_object_cache->set($key, $value, $group, $expiration);
}

function wp_cache_switch_to_blog($_blog_id)
{
    global $wp_object_cache;

    return $wp_object_cache->switch_to_blog($_blog_id);
}

function wp_cache_add_global_groups($groups)
{
    global $wp_object_cache;

    $wp_object_cache->add_global_groups($groups);
}

function wp_cache_add_non_persistent_groups($groups)
{
    global $wp_object_cache;

    $wp_object_cache->add_non_persistent_groups($groups);
}

function wp_cache_self_remove()
{
    @unlink(__FILE__);
}

class WP_Object_Cache
{
    private $redis;
    private $redis_connected = false;
    private $fail_gracefully = true;
    public $cache = [];
    public $redis_client = null;

    public $global_groups = [
        'blog-details',
        'blog-id-cache',
        'blog-lookup',
        'global-posts',
        'networks',
        'rss',
        'sites',
        'site-details',
        'site-lookup',
        'site-options',
        'site-transient',
        'users',
        'useremail',
        'userlogins',
        'usermeta',
        'user_meta',
        'userslugs',
    ];

    public $ignored_groups = array('counts', 'plugins');
    public $global_prefix = '';
    public $blog_prefix = '';
    public $cache_hits = 0;
    public $cache_misses = 0;

    public function __construct($fail_gracefully = true)
    {
        global $blog_id, $table_prefix;

        $this->fail_gracefully = $fail_gracefully;

        $parameters = array(
            'scheme' => 'tcp',
            'host' => '127.0.0.1',
            'port' => 6379
        );

        if ( defined('RUNCACHE_PURGER_DROPIN_HOST') ) {
            $parameters['host'] = RUNCACHE_PURGER_DROPIN_HOST;
        }

        if ( defined('RUNCACHE_PURGER_DROPIN_PORT') ) {
            $parameters['port'] = RUNCACHE_PURGER_DROPIN_PORT;
        }

        foreach (array('scheme', 'host', 'port', 'path', 'password', 'database') as $setting) {
            $constant = sprintf('WP_REDIS_%s', strtoupper($setting));

            if (defined($constant)) {
                $parameters[$setting] = constant($constant);
            }
        }

        if (defined('WP_REDIS_GLOBAL_GROUPS') && is_array(WP_REDIS_GLOBAL_GROUPS)) {
            $this->global_groups = WP_REDIS_GLOBAL_GROUPS;
        }

        if (defined('WP_REDIS_IGNORED_GROUPS') && is_array(WP_REDIS_IGNORED_GROUPS)) {
            $this->ignored_groups = WP_REDIS_IGNORED_GROUPS;
        }

        $client = defined('WP_REDIS_CLIENT') ? WP_REDIS_CLIENT : null;

        if (class_exists('Redis') && strcasecmp('predis', $client) !== 0) {
            $client = defined('HHVM_VERSION') ? 'hhvm' : 'pecl';
        } else {
            $client = 'predis';
        }

        try {
            if (strcasecmp('hhvm', $client) === 0) {
                $this->redis_client = sprintf('HHVM Extension (v%s)', HHVM_VERSION);
                $this->redis = new Redis();

                if (strcasecmp('unix', $parameters['scheme']) === 0) {
                    $parameters['host'] = 'unix://' . $parameters['path'];
                    $parameters['port'] = 0;
                }

                $this->redis->connect($parameters['host'], $parameters['port']);
            }

            if (strcasecmp('pecl', $client) === 0) {
                $this->redis_client = sprintf('PhpRedis (v%s)', phpversion('redis'));

                if (defined('WP_REDIS_SHARDS')) {
                    $this->redis = new RedisArray(array_values(WP_REDIS_SHARDS));
                } elseif (defined('WP_REDIS_CLUSTER')) {
                    $this->redis = new RedisCluster(null, array_values(WP_REDIS_CLUSTER));
                } else {
                    $this->redis = new Redis();

                    if (strcasecmp('unix', $parameters['scheme']) === 0) {
                        $this->redis->connect($parameters['path']);
                    } else {
                        $this->redis->connect($parameters['host'], $parameters['port']);
                    }
                }
            }

            if (strcasecmp('pecl', $client) === 0 || strcasecmp('hhvm', $client) === 0) {
                if (isset($parameters['password'])) {
                    $this->redis->auth($parameters['password']);
                }

                if (isset($parameters['database'])) {
                    $this->redis->select($parameters['database']);
                }
            }

            if (strcasecmp('predis', $client) === 0) {
                $this->redis_client = 'Predis';

                if (version_compare(PHP_VERSION, '5.4.0', '<')) {
                    throw new Exception('Predis required PHP 5.4 or newer.');
                }

                if (! class_exists('Predis\Client')) {
                    $predis = RUNCACHE_PURGER_PATH_VENDOR.'predis/autoload.php';

                    if (file_exists($predis)) {
                        require_once $predis;
                    } else {
                        throw new Exception('Predis library not found. Re-install Runcache Purger plugin or delete object-cache.php.');
                    }
                }

                $options = array();

                if (defined('WP_REDIS_SHARDS')) {
                    $parameters = WP_REDIS_SHARDS;
                } elseif (defined('WP_REDIS_SENTINEL')) {
                    $parameters = WP_REDIS_SERVERS;
                    $options['replication'] = 'sentinel';
                    $options['service'] = WP_REDIS_SENTINEL;
                } elseif (defined('WP_REDIS_SERVERS')) {
                    $parameters = WP_REDIS_SERVERS;
                    $options['replication'] = true;
                } elseif (defined('WP_REDIS_CLUSTER')) {
                    $parameters = WP_REDIS_CLUSTER;
                    $options['cluster'] = 'redis';
                }

                foreach (array('WP_REDIS_SERVERS', 'WP_REDIS_SHARDS', 'WP_REDIS_CLUSTER') as $constant) {
                    if (defined('WP_REDIS_PASSWORD') && defined($constant)) {
                        $options['parameters']['password'] = WP_REDIS_PASSWORD;
                    }
                }

                $this->redis = new Predis\Client($parameters, $options);
                $this->redis->connect();

                $this->redis_client .= sprintf(' (v%s)', Predis\Client::VERSION);
            }

            if (defined('WP_REDIS_CLUSTER')) {
                $this->redis->ping(current(array_values(WP_REDIS_CLUSTER)));
            } else {
                $this->redis->ping();
            }

            $this->redis_connected = true;
        } catch (Exception $exception) {
            $this->handle_exception($exception);
        }

        if (function_exists('is_multisite')) {
            $this->global_prefix = (is_multisite() || defined('CUSTOM_USER_TABLE') && defined('CUSTOM_USER_META_TABLE')) ? '' : $table_prefix;
            $this->blog_prefix = (is_multisite() ? $blog_id : $table_prefix);
        }
    }

    public function redis_status()
    {
        return $this->redis_connected;
    }

    public function redis_instance()
    {
        return $this->redis;
    }

    public function add($key, $value, $group = 'default', $expiration = 0)
    {
        return $this->add_or_replace(true, $key, $value, $group, $expiration);
    }

    public function replace($key, $value, $group = 'default', $expiration = 0)
    {
        return $this->add_or_replace(false, $key, $value, $group, $expiration);
    }

    protected function add_or_replace($add, $key, $value, $group = 'default', $expiration = 0)
    {
        $cache_addition_suspended = function_exists('wp_suspend_cache_addition')
            ? wp_suspend_cache_addition()
            : false;

        if ($add && $cache_addition_suspended) {
            return false;
        }

        $result = true;
        $derived_key = $this->build_key($key, $group);

        if (! in_array($group, $this->ignored_groups) && $this->redis_status()) {
            try {
                $exists = $this->redis->exists($derived_key);

                if ($add == $exists) {
                    return false;
                }

                $expiration = apply_filters('redis_cache_expiration', $this->validate_expiration($expiration), $key, $group);

                if ($expiration) {
                    $result = $this->parse_redis_response($this->redis->setex($derived_key, $expiration, $this->maybe_serialize($value)));
                } else {
                    $result = $this->parse_redis_response($this->redis->set($derived_key, $this->maybe_serialize($value)));
                }
            } catch (Exception $exception) {
                $this->handle_exception($exception);

                return false;
            }
        }

        $exists = isset($this->cache[$derived_key]);

        if ($add == $exists) {
            return false;
        }

        if ($result) {
            $this->add_to_internal_cache($derived_key, $value);
        }

        return $result;
    }

    public function delete($key, $group = 'default')
    {
        $result = false;
        $derived_key = $this->build_key($key, $group);

        if (isset($this->cache[$derived_key])) {
            unset($this->cache[$derived_key]);
            $result = true;
        }

        if ($this->redis_status() && ! in_array($group, $this->ignored_groups)) {
            try {
                $result = $this->parse_redis_response($this->redis->del($derived_key));
            } catch (Exception $exception) {
                $this->handle_exception($exception);

                return false;
            }
        }

        if (function_exists('do_action')) {
            do_action('redis_object_cache_delete', $key, $group);
        }

        return $result;
    }

    public function flush($delay = 0)
    {
        $delay = abs(intval($delay));

        if ($delay) {
            sleep($delay);
        }

        $results = [];
        $this->cache = array();

        if ($this->redis_status()) {
            $salt = defined('RUNCACHE_PURGER_DROPIN_PREFIX') ? trim(RUNCACHE_PURGER_DROPIN_PREFIX) : null;
            $selective = defined('WP_REDIS_SELECTIVE_FLUSH') ? WP_REDIS_SELECTIVE_FLUSH : null;

            if ($salt && $selective) {
                $script = "
                    local i = 0
                    for _,k in ipairs(redis.call('keys', '{$salt}*')) do
                        redis.call('del', k)
                        i = i + 1
                    end
                    return i
                ";

                if (defined('WP_REDIS_CLUSTER')) {
                    try {
                        foreach ($this->redis->_masters() as $master) {
                            $redis = new Redis;
                            $redis->connect($master[0], $master[1]);
                            $results[] = $this->parse_redis_response($this->redis->eval($script));
                            unset($redis);
                        }
                    } catch (Exception $exception) {
                        $this->handle_exception($exception);

                        return false;
                    }
                } else {
                    try {
                        $results[] = $this->parse_redis_response(
                            $this->redis->eval(
                                $script,
                                $this->redis instanceof Predis\Client ? 0 : []
                            )
                        );
                    } catch (Exception $exception) {
                        $this->handle_exception($exception);

                        return false;
                    }
                }
            } else {
                if (defined('WP_REDIS_CLUSTER')) {
                    try {
                        foreach ($this->redis->_masters() as $master) {
                            $results[] = $this->parse_redis_response($this->redis->flushdb($master));
                        }
                    } catch (Exception $exception) {
                        $this->handle_exception($exception);

                        return false;
                    }
                } else {
                    try {
                        $results[] = $this->parse_redis_response($this->redis->flushdb());
                    } catch (Exception $exception) {
                        $this->handle_exception($exception);

                        return false;
                    }
                }
            }

            if (function_exists('do_action')) {
                do_action('redis_object_cache_flush', $results, $delay, $selective, $salt);
            }
        }

        if (empty($results)) {
            return false;
        }

        foreach ($results as $result) {
            if (! $result) {
                return false;
            }
        }

        return true;
    }

    public function get($key, $group = 'default', $force = false, &$found = null)
    {
        $derived_key = $this->build_key($key, $group);

        if (isset($this->cache[$derived_key]) && ! $force) {
            $found = true;
            $this->cache_hits++;

            return is_object($this->cache[$derived_key]) ? clone $this->cache[$derived_key] : $this->cache[$derived_key];
        } elseif (in_array($group, $this->ignored_groups) || ! $this->redis_status()) {
            $found = false;
            $this->cache_misses++;

            return false;
        }

        try {
            $result = $this->redis->get($derived_key);
        } catch (Exception $exception) {
            $this->handle_exception($exception);

            return false;
        }

        if ($result === null || $result === false) {
            $found = false;
            $this->cache_misses++;

            return false;
        } else {
            $found = true;
            $this->cache_hits++;
            $value = $this->maybe_unserialize($result);
        }

        $this->add_to_internal_cache($derived_key, $value);

        $value = is_object($value) ? clone $value : $value;

        if (function_exists('do_action')) {
            do_action('redis_object_cache_get', $key, $value, $group, $force, $found);
        }

        if (function_exists('apply_filters') && function_exists('has_filter')) {
            if (has_filter('redis_object_cache_get_value')) {
                return apply_filters('redis_object_cache_get_value', $value, $key, $group, $force, $found);
            }
        }

        return $value;
    }

    public function get_multi($groups)
    {
        if (empty($groups) || ! is_array($groups)) {
            return false;
        }

        $cache = array();

        foreach ($groups as $group => $keys) {
            if (in_array($group, $this->ignored_groups) || ! $this->redis_status()) {
                foreach ($keys as $key) {
                    $cache[$this->build_key($key, $group)] = $this->get($key, $group);
                }
            } else {
                $derived_keys = array();

                foreach ($keys as $key) {
                    $derived_keys[] = $this->build_key($key, $group);
                }

                try {
                    $group_cache = $this->redis->mget($derived_keys);
                } catch (Exception $exception) {
                    $this->handle_exception($exception);
                    $group_cache = array_fill(0, count($derived_keys) - 1, false);
                }

                $group_cache = array_combine($derived_keys, $group_cache);
                $group_cache = array_map(array($this, 'maybe_unserialize'), $group_cache);
                $group_cache = array_map(array($this, 'filter_redis_get_multi'), $group_cache);

                $cache = array_merge($cache, $group_cache);
            }
        }

        foreach ($cache as $key => $value) {
            if ($value) {
                $this->cache_hits++;
                $this->add_to_internal_cache($key, $value);
            } else {
                $this->cache_misses++;
            }
        }

        return $cache;
    }

    public function set($key, $value, $group = 'default', $expiration = 0)
    {
        $result = true;
        $derived_key = $this->build_key($key, $group);

        if (! in_array($group, $this->ignored_groups) && $this->redis_status()) {
            $expiration = apply_filters('redis_cache_expiration', $this->validate_expiration($expiration), $key, $group);

            try {
                if ($expiration) {
                    $result = $this->parse_redis_response($this->redis->setex($derived_key, $expiration, $this->maybe_serialize($value)));
                } else {
                    $result = $this->parse_redis_response($this->redis->set($derived_key, $this->maybe_serialize($value)));
                }
            } catch (Exception $exception) {
                $this->handle_exception($exception);

                return false;
            }
        }

        if ($result) {
            $this->add_to_internal_cache($derived_key, $value);
        }

        if (function_exists('do_action')) {
            do_action('redis_object_cache_set', $key, $value, $group, $expiration);
        }

        return $result;
    }

    public function increment($key, $offset = 1, $group = 'default')
    {
        $derived_key = $this->build_key($key, $group);
        $offset = (int) $offset;

        if (in_array($group, $this->ignored_groups) || ! $this->redis_status()) {
            $value = $this->get_from_internal_cache($derived_key, $group);
            $value += $offset;
            $this->add_to_internal_cache($derived_key, $value);

            return $value;
        }

        try {
            $result = $this->parse_redis_response($this->redis->incrBy($derived_key, $offset));

            $this->add_to_internal_cache($derived_key, (int) $this->redis->get($derived_key));
        } catch (Exception $exception) {
            $this->handle_exception($exception);

            return false;
        }

        return $result;
    }

    public function incr($key, $offset = 1, $group = 'default')
    {
        return $this->increment($key, $offset, $group);
    }

    public function decrement($key, $offset = 1, $group = 'default')
    {
        $derived_key = $this->build_key($key, $group);
        $offset = (int) $offset;

        if (in_array($group, $this->ignored_groups) || ! $this->redis_status()) {
            $value = $this->get_from_internal_cache($derived_key, $group);
            $value -= $offset;
            $this->add_to_internal_cache($derived_key, $value);
            return $value;
        }

        try {
            $result = $this->parse_redis_response($this->redis->decrBy($derived_key, $offset));
            $this->add_to_internal_cache($derived_key, (int) $this->redis->get($derived_key));
        } catch (Exception $exception) {
            $this->handle_exception($exception);
            return false;
        }

        return $result;
    }

    public function stats()
    {
        ?>
        <p>
            <strong>Redis Status:</strong> <?php echo $this->redis_status() ? 'Connected' : 'Not Connected'; ?><br />
            <strong>Redis Client:</strong> <?php echo $this->redis_client; ?><br />
            <strong>Cache Hits:</strong> <?php echo $this->cache_hits; ?><br />
            <strong>Cache Misses:</strong> <?php echo $this->cache_misses; ?>
        </p>

        <ul>
            <?php foreach ($this->cache as $group => $cache) : ?>
                <li><?php printf('%s - %sk', strip_tags($group), number_format(strlen(serialize($cache)) / 1024, 2)); ?></li>
            <?php endforeach; ?>
        </ul><?php
    }

    public function build_key($key, $group = 'default')
    {
        if (empty($group)) {
            $group = 'default';
        }

        $salt = defined('RUNCACHE_PURGER_DROPIN_PREFIX') ? trim(RUNCACHE_PURGER_DROPIN_PREFIX) : '';
        $prefix = in_array($group, $this->global_groups) ? $this->global_prefix : $this->blog_prefix;
        return "{$salt}{$prefix}:{$group}:{$key}";
    }

    protected function filter_redis_get_multi($value)
    {
        if (is_null($value)) {
            $value = false;
        }

        return $value;
    }

    protected function parse_redis_response($response)
    {
        if (is_bool($response)) {
            return $response;
        }

        if (is_numeric($response)) {
            return $response;
        }

        if (is_object($response) && method_exists($response, 'getPayload')) {
            return $response->getPayload() === 'OK';
        }

        return false;
    }

    public function add_to_internal_cache($derived_key, $value)
    {
        $this->cache[$derived_key] = $value;
    }

    public function get_from_internal_cache($key, $group)
    {
        $derived_key = $this->build_key($key, $group);

        if (isset($this->cache[$derived_key])) {
            return $this->cache[$derived_key];
        }

        return false;
    }

    public function switch_to_blog($_blog_id)
    {
        if (! function_exists('is_multisite') || ! is_multisite()) {
            return false;
        }

        $this->blog_prefix = $_blog_id;

        return true;
    }

    public function add_global_groups($groups)
    {
        $groups = (array) $groups;

        if ($this->redis_status()) {
            $this->global_groups = array_unique(array_merge($this->global_groups, $groups));
        } else {
            $this->ignored_groups = array_unique(array_merge($this->ignored_groups, $groups));
        }
    }

    public function add_non_persistent_groups($groups)
    {
        $groups = (array) $groups;

        $this->ignored_groups = array_unique(array_merge($this->ignored_groups, $groups));
    }

    protected function validate_expiration($expiration)
    {
        $expiration = is_int($expiration) || ctype_digit($expiration) ? (int) $expiration : 0;

        if (defined('WP_REDIS_MAXTTL')) {
            $max = (int) WP_REDIS_MAXTTL;

            if ($expiration === 0 || $expiration > $max) {
                $expiration = $max;
            }
        }

        return $expiration;
    }

    protected function maybe_unserialize($original)
    {
        if (defined('WP_REDIS_IGBINARY') && WP_REDIS_IGBINARY && function_exists('igbinary_unserialize')) {
            return igbinary_unserialize($original);
        }

        if ($this->is_serialized($original)) {
            return @unserialize($original);
        }

        return $original;
    }

    protected function maybe_serialize($data)
    {
        if (defined('WP_REDIS_IGBINARY') && WP_REDIS_IGBINARY && function_exists('igbinary_serialize')) {
            return igbinary_serialize($data);
        }

        if (is_array($data) || is_object($data)) {
            return serialize($data);
        }

        if ($this->is_serialized($data, false)) {
            return serialize($data);
        }

        return $data;
    }

    protected function is_serialized($data, $strict = true)
    {
        if (! is_string($data)) {
            return false;
        }

        $data = trim($data);

        if ('N;' == $data) {
            return true;
        }

        if (strlen($data) < 4) {
            return false;
        }

        if (':' !== $data[1]) {
            return false;
        }

        if ($strict) {
            $lastc = substr($data, -1);

            if (';' !== $lastc && '}' !== $lastc) {
                return false;
            }
        } else {
            $semicolon = strpos($data, ';');
            $brace = strpos($data, '}');

            if (false === $semicolon && false === $brace) {
                return false;
            }

            if (false !== $semicolon && $semicolon < 3) {
                return false;
            }

            if (false !== $brace && $brace < 4) {
                return false;
            }
        }
        $token = $data[0];

        switch ($token) {
            case 's':
                if ($strict) {
                    if ('"' !== substr($data, -2, 1)) {
                        return false;
                    }
                } elseif (false === strpos($data, '"')) {
                    return false;
                }
            case 'a':
            case 'O':
                return (bool) preg_match("/^{$token}:[0-9]+:/s", $data);
            case 'b':
            case 'i':
            case 'd':
                $end = $strict ? '$' : '';

                return (bool) preg_match("/^{$token}:[0-9.E-]+;$end/", $data);
        }

        return false;
    }

    protected function handle_exception($exception) {
        $this->redis_connected = false;
        $this->ignored_groups = array_unique(array_merge($this->ignored_groups, $this->global_groups));

        if (! $this->fail_gracefully) {
            throw $exception;
        }

        error_log($exception);
    }
}

endif; // is_redis_connect
endif; // if defined
