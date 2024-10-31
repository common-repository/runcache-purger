<?php
/**
 * @wordpress-plugin
 * Plugin Name:     RunCache Purger
 * Plugin URI:      https://wordpress.org/plugins/runcache-purger/
 * Description:     This plugin will purge RunCloud.io NGINX fastcgi, Proxy Cache and Redis Object Cache.
 * Version:         1.11.1
 * Author:          RunCloud
 * Author URI:      https://runcloud.io/
 * License:         GPL-2.0+
 * License URI:     http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:     runcachepurger
 * Domain Path:     /languages
 */

/*
Copyright 2019 RunCloud.io
All rights reserved.

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version
2 of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
with this program. If not, visit: https://www.gnu.org/licenses/
 */

if (!defined('WPINC') || defined('RUNCACHE_PURGER_HOOK')) {
    exit;
}

/**
 * constant
 */
define('RUNCACHE_PURGER_FILE', __FILE__);
define('RUNCACHE_PURGER_HOOK', plugin_basename(RUNCACHE_PURGER_FILE));
define('RUNCACHE_PURGER_PATH', realpath(plugin_dir_path(RUNCACHE_PURGER_FILE)) . '/');
define('RUNCACHE_PURGER_PATH_LANG', RUNCACHE_PURGER_PATH . 'languages/');
define('RUNCACHE_PURGER_PATH_VIEW', RUNCACHE_PURGER_PATH . 'views/');
!defined('RUNCACHE_PURGER_PATH_VENDOR') && define('RUNCACHE_PURGER_PATH_VENDOR', RUNCACHE_PURGER_PATH . 'vendor/');

final class RunCache_Purger
{

    // dependencies checking
    private static $depend_version_php     = '5.6.8';
    private static $depend_version_wp      = '5.1.1';
    private static $depend_check_wp        = true;
    private static $depend_check_php       = true;
    private static $depend_check_webserver = true;

    // reference
    private static $name       = 'RunCache Purger';
    private static $slug       = 'runcache-purger';
    private static $islug      = 'runcache_purger';
    private static $dslug      = 'runcache_purger_settings';
    private static $textdomain = 'runcachepurger';

    // version
    private static $version      = '1.11.1';
    private static $version_prev = '1.11.0';

    // later
    private static $hostname = '';
    private static $hook     = '';
    private static $ordfirst;
    private static $ordlast;
    private static $options;

    // url
    private static $plugin_url        = '';
    private static $plugin_url_assets = '';
    private static $plugin_url_logo   = '';
    private static $plugin_url_logo_w = '';

    // view
    private static $checked = [];
    private static $value   = [];

    // is cond
    private static $is_purge_home     = false;
    private static $is_purge_content  = false;
    private static $is_purge_archives = false;
    private static $is_purge_object   = false;

    // request status
    private static $req_status = [];

    /**
     * Flushes all response data to the client.
     */
    private static function fastcgi_close()
    {
        if ((php_sapi_name() === 'fpm-fcgi')
            && function_exists('fastcgi_finish_request')) {
            @session_write_close();
            @fastcgi_finish_request();
        }
    }

    /**
     * Terminate the current script.
     */
    private static function close_exit($content = '')
    {
        if (!empty($content)) {
            echo $content;
        }
        self::fastcgi_close();
        exit;
    }

    /**
     * is_wp_ssl.
     */
    private static function is_wp_ssl()
    {
        $scheme = parse_url(get_site_url(), PHP_URL_SCHEME);
        return ('https' === $scheme ? true : false);
    }

    /**
     * is_wp_cli.
     */
    private static function is_wp_cli()
    {
        return (defined('WP_CLI') && WP_CLI);
    }

    /**
     * is_defined_halt.
     */
    private static function is_defined_halt()
    {
        return defined('RUNCACHE_PURGER_HALT');
    }

    /**
     * define_halt.
     */
    private static function define_halt()
    {
        if (!self::is_defined_halt() && !self::is_wp_cli()) {
            define('RUNCACHE_PURGER_HALT', true);
        }
    }

    /**
     * is_apache.
     */
    private static function is_apache()
    {

        // dont use wp is_apache, since litespeed is not apache we know
        if (false !== strpos($_SERVER['SERVER_SOFTWARE'], 'Apache')) {
            return true;
        }

        if ('apache2handler' === php_sapi_name()) {
            return true;
        }

        return false;
    }

    /**
     * is_nginx.
     */
    private static function is_nginx()
    {

        if (isset($GLOBALS['is_nginx']) && (bool) $GLOBALS['is_nginx']) {
            return true;
        }

        if (false !== strpos($_SERVER['SERVER_SOFTWARE'], 'nginx')) {
            return true;
        }

        return false;
    }

    /**
     * is_redis_connect.
     */
    private static function is_redis_connect($host = null, $port = null, &$error = '')
    {

        if (function_exists('__redis_is_connect')) {
            if (empty($host) || empty($port)) {
                $options = self::get_settings();
                $host    = $options['redis_host'];
                $port    = $options['redis_port'];
            }
            if (__redis_is_connect($host, $port, $error)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Define the locale for this plugin for internationalization.
     */
    private static function register_locale()
    {
        add_action(
            'plugins_loaded',
            function () {
                load_plugin_textdomain(
                    self::$textdomain,
                    false,
                    RUNCACHE_PURGER_PATH_LANG
                );
            },
            0
        );
    }

    /**
     * register.
     */
    public static function register()
    {

        require_once RUNCACHE_PURGER_PATH_VENDOR . 'redis_is_connect.php';

        self::register_locale();

        if (self::is_wp_cli()) {
            return true;
        }

        if (version_compare($GLOBALS['wp_version'], self::$depend_version_wp, '<')) {
            self::$depend_check_wp = false;
        }

        if (version_compare(PHP_VERSION, self::$depend_version_php, '<')) {
            self::$depend_check_php = false;
        }

        if (!self::is_apache() && !self::is_nginx()) {
            self::$depend_check_webserver = false;
        }

        if (!self::$depend_check_wp || !self::$depend_check_php || !self::$depend_check_webserver) {
            self::define_halt();

            add_action('all_admin_notices', [__CLASS__, 'halt_notice'], 1);
            add_action(
                'admin_init',
                function () {
                    deactivate_plugins(RUNCACHE_PURGER_HOOK);
                }
            );
            return false;
        }

        return true;
    }

    /**
     * halt_notice.
     */
    public static function halt_notice()
    {
        $msg = '<style>';
        $msg .= '.wp-rcpurger-plugins-error {';
        $msg .= 'overflow: hidden;';
        $msg .= 'padding-left: 20px;';
        $msg .= 'list-style-type: disc';
        $msg .= '}';
        $msg .= '.wp-rcpurger-plugins-error li {';
        $msg .= 'line-height: 18px';
        $msg .= '}</style>';

        $msg .= '<p>';
        $msg .= sprintf(
            __('The <strong>%1$s</strong> plugin version %2$s requires:', self::$textdomain),
            self::$name,
            self::$version
        );
        $msg .= '</p>';

        $msg .= '<ul class="wp-rcpurger-plugins-error">';

        if (!self::$depend_check_wp) {
            $msg .= '<li>';
            $msg .= sprintf(
                __('WordPress version %s or higher.', self::$textdomain),
                self::$depend_version_wp
            );
            $msg .= '</li>';
        }

        if (!self::$depend_check_php) {
            $msg .= '<li>';
            $msg .= sprintf(
                __('PHP version %s or higher.', self::$textdomain),
                self::$depend_version_php
            );
            $msg .= '</li>';
        }

        if (!self::$depend_check_webserver) {
            $msg .= '<li>';
            $msg .= __('Apache or NGINX Web Server.', self::$textdomain);
            $msg .= '</li>';
        }

        $msg .= '</ul>';

        $msg .= '<p>' . __('The plugin has now deactivated itself. Please contact your hosting provider or system administrator for version upgrade.', self::$textdomain) . '</p>';

        echo '<div class="notice notice-error">' . $msg . '</div>';
    }

    /**
     * register_init.
     */
    public static function register_init()
    {
        self::$hook = RUNCACHE_PURGER_HOOK;

        self::$hostname = parse_url(get_site_url(), PHP_URL_HOST);

        self::$plugin_url        = plugin_dir_url(RUNCACHE_PURGER_FILE);
        self::$plugin_url_assets = self::$plugin_url . 'assets/';
        self::$plugin_url_logo   = self::$plugin_url_assets . self::$islug . '.svg';
        self::$plugin_url_logo_w = self::$plugin_url_assets . self::$islug . '-w.svg';

        self::$ordfirst = 0;
        self::$ordlast  = PHP_INT_MAX;

        self::reset_settings();
        define('RUNCACHE_PURGER_INIT', true);
    }

    /**
     * default_settings.
     */
    private static function default_settings()
    {
        $options = [
            'homepage_post_onn'            => 1,
            'homepage_removed_onn'         => 1,
            'content_publish_onn'          => 1,
            'content_comment_approved_onn' => 1,
            'content_comment_removed_onn'  => 1,
            'archives_homepage_onn'        => 1,
            'archives_content_onn'         => 1,
            'redis_purge_onn'              => 1,
            'redis_cache_onn'              => 1,
            'redis_prefix'                 => (defined('WP_CACHE_KEY_SALT') ? md5(WP_CACHE_KEY_SALT) : md5('runcache-purger' . time())),
            'redis_host'                   => '127.0.0.1',
            'redis_port'                   => 6379,
        ];

        return $options;
    }

    /**
     * is_network_admin_plugin.
     */
    private static function is_network_admin_plugin($action = 'deactivate')
    {
        if (!empty($_SERVER['REQUEST_URI']) && !empty($_GET)
            && preg_match('/\/wp-admin\/network\/plugins\.php/', $_SERVER['REQUEST_URI'])
            && $action === $_GET['action']) {
            return true;
        }

        return false;
    }

    /**
     * install_options.
     */
    private static function install_options()
    {

        $__varfunc_install = function () {
            $options                 = self::default_settings();
            $key                     = $options['redis_prefix'] . mt_rand(5, 150) . time();
            $options['redis_prefix'] = wp_hash($key, 'secure_auth');
            add_option(self::$dslug, $options);
        };

        if (is_multisite() && current_user_can(apply_filters('capability_network', 'manage_network_plugins'))
            && self::is_network_admin_plugin('activate')) {

            $sites = get_sites();
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);

                $__varfunc_install();

                restore_current_blog();
            }
        } else {

            $__varfunc_install();
        }
    }

    /**
     * uninstall_options.
     */
    private static function uninstall_options()
    {

        $__varfunc_uninstall = function () {
            delete_option(self::$dslug);
        };

        if (is_multisite() && current_user_can(apply_filters('capability_network', 'manage_network_plugins'))
            && self::is_network_admin_plugin('activate')) {

            $sites = get_sites();
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);

                $__varfunc_uninstall();

                restore_current_blog();
            }
        } else {

            $__varfunc_uninstall();
        }
    }

    /**
     * get_dropin_file.
     */
    private static function get_dropin_file()
    {
        return WP_CONTENT_DIR . '/object-cache.php';
    }

    /**
     * install_dropin.
     */
    private static function install_dropin()
    {

        $file = RUNCACHE_PURGER_PATH_VENDOR . 'object-cache.php';
        if (!file_exists($file)) {
            return false;
        }

        $options = self::get_settings();
        if (empty($options['redis_cache_onn'])) {
            return self::uninstall_dropin();
        }

        $buff = file_get_contents($file);
        if (!empty($buff)) {

            $redis_host   = $options['redis_host'];
            $redis_port   = $options['redis_port'];
            $redis_prefix = $options['redis_prefix'];

            $code = "";
            $code .= "!defined('RUNCACHE_PURGER_DROPIN_HOST') && define('RUNCACHE_PURGER_DROPIN_HOST', '" . $redis_host . "');" . PHP_EOL;
            $code .= "!defined('RUNCACHE_PURGER_DROPIN_PORT') && define('RUNCACHE_PURGER_DROPIN_PORT', '" . $redis_port . "');" . PHP_EOL;
            $code .= "!defined('RUNCACHE_PURGER_DROPIN_PREFIX') && define('RUNCACHE_PURGER_DROPIN_PREFIX', '" . addslashes($redis_prefix) . "');" . PHP_EOL;

            $buff = str_replace('/*@CONFIG-MARKER@*/', trim($code), $buff);
            $buff .= '//@' . date('YmdHis') . PHP_EOL;

            $file_dropin = self::get_dropin_file();

            $perm = self::get_fileperms('file');
            if (file_put_contents($file_dropin, $buff)) {
                @chmod($filesave, $perm);
                return true;
            }
        }

        return false;
    }

    /**
     * uninstall_dropin.
     */
    private static function uninstall_dropin()
    {
        $file_dropin = self::get_dropin_file();
        if (file_exists($file_dropin) && defined('RUNCACHE_PURGER_DROPIN')) {
            return unlink($file_dropin);
        }
        return true;
    }

    /**
     * try_install_dropin.
     */
    private static function try_install_dropin()
    {
        if (!defined('RUNCACHE_PURGER_DROPIN') || !file_exists(self::get_dropin_file())) {
            self::install_dropin();
        }
    }

    /**
     * is_plugin_active_for_network.
     */
    private static function is_plugin_active_for_network($plugin)
    {

        if (!function_exists('is_plugin_active_for_network')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        return is_plugin_active_for_network($plugin);
    }

    /**
     * force_site_deactivate_plugin.
     */
    private static function force_site_deactivate_plugin()
    {
        if (is_multisite()
            && self::is_plugin_active_for_network(self::$hook)
            && current_user_can(apply_filters('capability_network', 'manage_network_plugins'))) {

            $sites = get_sites();
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                deactivate_plugins(self::$hook, true, false);
                restore_current_blog();
            }
        }
    }

    /**
     * callback_links.
     */
    public static function callback_links($links)
    {

        array_unshift(
            $links,
            sprintf(
                '<a href="%s">%s</a>',
                admin_url('options-general.php?page=' . self::$slug),
                __('Settings', self::$textdomain)
            )
        );
        return $links;
    }

    /**
     * callback_page.
     */
    public static function callback_page()
    {
        $icon = '<img src="' . self::$plugin_url_logo_w . '" width="18" style="margin-right:4px;margin-bottom:1px;">';
        add_options_page(
            self::$name,
            $icon . self::$name,
            apply_filters('capability', 'manage_options'),
            self::$slug,
            [__CLASS__, 'view_index']
        );
    }

    /**
     * view_fname.
     */
    private static function view_fname($name)
    {
        echo self::$dslug . '[' . $name . ']';
    }

    /**
     * view_fname.
     *
     * @since   0.0.0
     */
    private static function view_checked($name)
    {
        echo self::$checked[$name];
    }

    /**
     * view_fvalue.
     */
    private static function view_fvalue($name)
    {
        echo self::$value[$name];
    }

    /**
     * view_index.
     */
    public static function view_index()
    {
        $options       = self::get_settings();
        self::$checked = [];
        if (!empty($options) && is_array($options)) {
            foreach ($options as $key => $val) {
                if (preg_match('/.*_onn$/', $key)) {
                    $val                 = (int) $val;
                    self::$checked[$key] = (1 === $val ? ' checked' : '');
                } else {
                    self::$value[$key] = $val;
                }
            }
        }
        include_once RUNCACHE_PURGER_PATH_VIEW . 'settings.php';
    }

    /**
     * Register the css/js for the admin area.
     */
    public static function callback_assets()
    {
        wp_enqueue_style(
            self::$islug . '-admin',
            self::$plugin_url_assets . self::$islug . '.css',
            [],
            self::$version,
            'all'
        );
    }

    /**
     * append_wp_http_referer.
     */
    private static function append_wp_http_referer()
    {
        $referer = '';
        if (!empty($_SERVER['REQUEST_URI'])) {
            $referer = filter_var(wp_unslash($_SERVER['REQUEST_URI']), FILTER_SANITIZE_URL);
            $referer = '&_wp_http_referer=' . rawurlencode(remove_query_arg('fl_builder', $referer));
        }

        return $referer;
    }

    /**
     * callback_adminbar.
     */
    public static function callback_adminbar($wp_admin_bar)
    {
        if (!current_user_can(apply_filters('capability', 'manage_options'))) {
            return;
        }

        global $pagenow, $post;

        $referer = self::append_wp_http_referer();
        $icon    = '<img src="' . self::$plugin_url_logo_w . '" style="width:20px!important;margin-right:4px;">';
        $wp_admin_bar->add_menu(
            [
                'id'    => self::$slug,
                'title' => $icon . self::$name,
            ]
        );

        $wp_admin_bar->add_menu(
            [
                'parent' => self::$slug,
                'id'     => self::$slug . '-settings',
                'title'  => __('Settings', self::$textdomain),
                'href'   => admin_url('options-general.php?page=' . self::$slug),
            ]
        );

        $action   = 'flushcache';
        $action_a = 'purge_cache';

        if (is_admin()) {

            if ($post && 'post.php' === $pagenow && isset($_GET['action'], $_GET['post'])) {
                $wp_admin_bar->add_menu(
                    [
                        'parent' => self::$slug,
                        'id'     => self::$slug . '-clearcachepost',
                        'title'  => __('Clear Cache this Post', self::$textdomain),
                        'href'   => wp_nonce_url(admin_url('admin-post.php?action=' . $action . '&type=post-' . $post->ID . $referer), $action_a . '_post-' . $post->ID),
                    ]
                );
            }
        } else {

            $wp_admin_bar->add_menu(
                [
                    'parent' => self::$slug,
                    'id'     => self::$slug . '-clearcacheurl',
                    'title'  => __('Clear Cache this URL', self::$textdomain),
                    'href'   => wp_nonce_url(admin_url('admin-post.php?action=' . $action . '&type=url' . $referer), $action_a . '_url'),
                ]
            );
        }

        $wp_admin_bar->add_menu(
            [
                'parent' => self::$slug,
                'id'     => self::$slug . '-clearcacheall',
                'title'  => __('Clear All Cache', self::$textdomain),
                'href'   => wp_nonce_url(admin_url('admin-post.php?action=' . $action . '&type=all' . $referer), $action_a . '_all'),
            ]
        );
    }

    /**
     * callback_updates.
     */
    public static function callback_updates($old, $options)
    {
        if (!empty($options)) {
            self::install_dropin();
        }
    }

    /**
     * callback_settings.
     */
    public static function callback_settings()
    {
        register_setting(
            self::$slug,
            self::$dslug,
            [__CLASS__, 'settings_validate']
        );

        // try install dropin
        self::try_install_dropin();
    }

    /**
     * callback_notices.
     */
    public static function callback_notices()
    {
        if (defined('DOING_AUTOSAVE') || defined('DOING_AJAX')) {
            return;
        }

        if (current_user_can(apply_filters('capability', 'manage_options'))) {
            add_action('all_admin_notices', [__CLASS__, 'callback_flushcache_notice'], self::$ordlast);

            add_action('all_admin_notices', function() {
                $html = '<div class="notice notice-error is-dismissible"><p><strong>';
                $html .= 'This plugin has been deprecated. Please install our latest RunCloud Hub plugin through RunCloud Panel to enjoy server side caching, cache exclusion and better performance.';
                $html .= '</strong></p></div>';
                echo $html;
            }, self::$ordlast);

        }
    }

    /**
     * get_settings.
     */
    private static function get_settings()
    {
        $options = get_option(self::$dslug, self::default_settings());
        if (!empty($options) && is_array($options)) {
            $options = array_merge(self::default_settings(), $options);
        } else {
            $options = [];
        }

        return $options;
    }

    /**
     * set_settings.
     */
    private static function reset_settings()
    {
        self::$is_purge_home     = false;
        self::$is_purge_content  = false;
        self::$is_purge_archives = false;
        self::$is_purge_object   = false;

        $options = self::get_settings();
        if (!empty($options)) {
            if (!empty($options['homepage_post_onn']) || !empty($options['homepage_removed_onn'])) {
                self::$is_purge_home = true;
            }

            if (!empty($options['content_publish_onn'])
                || !empty($options['content_comment_approved_onn'])
                || !empty($options['content_comment_removed_onn'])) {
                self::$is_purge_content = true;
            }

            if (!empty($options['archives_homepage_onn']) || self::$is_purge_home) {
                self::$is_purge_archives = true;
            }

            if (!empty($options['archives_content_onn']) || self::$is_purge_content) {
                self::$is_purge_archives = true;
            }

            if (!empty($options['redis_purge_onn'])) {
                self::$is_purge_object = true;
            }
        }
    }

    /**
     * settings_validate.
     */
    public static function settings_validate($input)
    {
        $options = array_merge(self::default_settings(), self::get_settings());

        if (!empty($input)) {
            foreach ($options as $key => $val) {
                if (preg_match('/.*_onn$/', $key)) {
                    if (!isset($input[$key])) {
                        $input[$key] = 0;
                    }
                }
            }
        } else {
            $input = self::default_settings();
        }

        $options = array_merge($options, $input);

        return $options;
    }

    /**
     * Register all of the hooks related to the admin area functionality
     * of the plugin.
     */
    public static function register_admin_hooks()
    {
        add_filter('plugin_action_links_' . self::$hook, [__CLASS__, 'callback_links'], self::$ordfirst);
        add_action('admin_print_styles-settings_page_' . self::$slug, [__CLASS__, 'callback_assets'], self::$ordlast);
        add_action('admin_menu', [__CLASS__, 'callback_page'], self::$ordfirst);
        add_action('admin_bar_menu', [__CLASS__, 'callback_adminbar'], self::$ordlast);
        add_action('update_option_' . self::$dslug, [__CLASS__, 'callback_updates'], self::$ordfirst, 2);
        add_action('admin_init', [__CLASS__, 'callback_settings'], self::$ordfirst);
        add_action('admin_post_flushcache', [__CLASS__, 'callback_flushcache'], self::$ordlast);
        add_action('plugins_loaded', [__CLASS__, 'callback_notices'], self::$ordfirst);
    }

    /**
     * Short Description. (use period)
     *
     * @since   0.0.0
     */
    private static function remote_request($url, $options = [])
    {

        if (self::is_defined_halt()) {
            return false;
        }

        $hostname      = self::$hostname;
        static $done   = [];
        static $done_o = false;

        if (isset($done[$hostname][$url])) {
            return;
        }

        add_action('http_api_curl', function ($handle, $r, $url) {

            $myhost = parse_url(get_site_url(), PHP_URL_HOST);

            $url_host   = parse_url($url, PHP_URL_HOST);
            $url_scheme = parse_url($url, PHP_URL_SCHEME);
            $url_port   = parse_url($url, PHP_URL_PORT);

            // 03102019
            if ( $myhost !== $url_host ) {
                return;
            }

            $port = 80;

            if ('https' === $url_scheme) {
                $port = 443;
            }

            if (!empty($url_port) && $url_port !== $port) {
                $port = $url_port;
            }

            curl_setopt($handle, CURLOPT_RESOLVE,
                [
                    $url_host . ':' . $port . ':127.0.0.1',
                ]
            );
        }, 10, 3);

        $args = [
            'method'      => 'GET',
            'timeout'     => 45,
            'redirection' => 5,
            'httpversion' => '1.1',
            'user-agent'  => self::get_user_agent(),
            'blocking'    => true,
            'headers'     => ['Host' => $hostname],
            'cookies'     => [],
            'body'        => null,
            'compress'    => false,
            'decompress'  => true,
            'sslverify'   => false,
            'stream'      => false,
            'filename'    => null,
        ];

        if (!empty($options) && is_array($options)) {
            $args = array_merge($args, $options);
        }

        $return = [
            'code'         => '',
            'status'       => '',
            'request_host' => $args['headers']['Host'],
            'request_url'  => $url,
            'method'       => $args['method'],
        ];

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            $return['code']   = -1;
            $return['status'] = $response->get_error_message();
            self::define_halt();
        } else {
            $return['header'] = (is_object($response['headers']) ? (array) $response['headers'] : null);
            $return['code']   = wp_remote_retrieve_response_code($response);
            $return['status'] = $response['body'];
        }

        switch ($return['code']) {
            case '200':
                $return['status'] = 'Successful purge';
                break;
            case '400':
                $return['status'] = 'Request Forbidden';
                self::define_halt();
                break;
            case '403':
                $return['status'] = 'Request Forbidden';
                self::define_halt();
                break;
            case '404':
                $return['status'] = 'Request Not found';
                self::define_halt();
                break;
            case '405':
                $return['status'] = 'Method Not Allowed';
                self::define_halt();
                break;
            default:
                if (substr($return['code'], 0, 2) == 50) {
                    $return['status'] = 'Failed to connect';
                    self::define_halt();
                }
        }

        self::debug(__METHOD__, $return);

        self::$req_status = $return;

        $done[$hostname][$url] = 1;

        if (self::$is_purge_object && !$done_o) {
            self::flush_object(true);
            $done_o = true;
        }

        return $return;
    }

    /**
     * get_user_ip.
     */
    private static function get_user_ip()
    {
        foreach ([
            'HTTP_CF_CONNECTING_IP',
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_X_REAL_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR',
        ] as $key) {
            if (!empty($_SERVER[$key])) {
                $ip = explode(',', $_SERVER[$key]);
                $ip = end($ip);

                if (false !== filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * get_user_agent.
     */
    private static function get_user_agent($fallback_default = false)
    {
        $default_ua = 'Mozilla/5.0 (compatible; RunCachePurger; +https://runcloud.io)';
        $ua         = '';
        if (!empty($_SERVER['HTTP_USER_AGENT'])) {
            if (false === strpos($_SERVER['HTTP_USER_AGENT'], 'RunCachePurger')) {
                $ua = $_SERVER['HTTP_USER_AGENT'] . ' (compatible; RunCachePurger; +https://runcloud.io)';
            } else {
                $ua = $_SERVER['HTTP_USER_AGENT'];
            }
        }

        if ($fallback_default && empty($ua)) {
            $ua = $default_ua;
        }

        return $ua;
    }

    /**
     * request_purge_type.
     */
    private static function request_purge_type()
    {
        return (self::is_nginx() ? 'fastcgi' : 'proxy');
    }

    /**
     * request_purge_all.
     */
    private static function request_purge_all($type = null, $proto = null)
    {

        if (self::is_defined_halt()) {
            return false;
        }

        // see at bottom of remote_request
        //self::flush_object();

        $type = (!empty($type) ? $type : self::request_purge_type());

        if (empty($proto) && 'http' !== $proto && 'https' !== $proto) {
            $proto = (self::is_wp_ssl() ? 'https' : 'http');
        }

        $request_query = $proto . '://127.0.0.1/runcache-purgeall-' . $type;
        return self::remote_request($request_query, ['method' => 'PURGE']);
    }

    /**
     * request_purge_url.
     */
    private static function request_purge_url($url)
    {

        if (self::is_defined_halt()) {
            return false;
        }

        $type          = self::request_purge_type();
        $request_query = str_replace(self::$hostname, self::$hostname . '/runcache-purge-' . $type . '/', $url) . '*';
        $request_query = str_replace('runcache-purge-' . $type . '//', 'runcache-purge-' . $type . '/', $request_query);

        return self::remote_request($request_query, ['method' => 'GET']);
    }

    /**
     * callback_flushcache.
     */
    public static function callback_flushcache()
    {

        if (isset($_GET['type'], $_GET['_wpnonce'])) {
            if (!wp_verify_nonce($_GET['_wpnonce'], 'purge_cache_' . $_GET['type'])) {
                wp_nonce_ays('');
            }

            if (!current_user_can(apply_filters('capability', 'manage_options'))) {
                return;
            }

            $wp_referer = wp_get_referer();
            $get_type   = sanitize_text_field($_GET['type']);

            $type = explode('-', $get_type);
            $type = reset($type);
            $id   = explode('-', $get_type);
            $id   = end($id);

            if (isset($_GET['action']) && 'flushcache' === $_GET['action']) {
                switch ($type) {
                    case 'all':
                        self::flush_home(true);
                        break;
                    case 'post':
                        self::$is_purge_archives = true;
                        self::$is_purge_home     = true;
                        self::$is_purge_content  = true;
                        self::$is_purge_object   = true;

                        self::flush_post($id);
                        break;
                    case 'url':
                        if ('/' === $wp_referer) {
                            self::flush_home();
                        } else {
                            self::flush_url($wp_referer);
                        }
                        break;
                    case 'homepage':
                        self::flush_home();
                        break;
                    case 'content':
                        self::flush_content();
                        break;
                    case 'archives':
                        self::flush_archives();
                        break;
                    case 'redis':
                        self::flush_object();
                        break;
                }
            }
        }

        self::reset_settings();

        set_transient('rcpurge/callback_flushcache', self::$req_status, 30);
        wp_safe_redirect(esc_url_raw($wp_referer));
        self::close_exit();
    }

    /**
     * callback_flushcache_notice.
     */
    public static function callback_flushcache_notice()
    {
        $qsk = get_transient('rcpurge/callback_flushcache');
        if (!empty($qsk)) {

            $msg         = __('Failed to purge cache.', self::$textdomain);
            $notice_type = 'error';

            $req_status = $qsk;
            if (!empty($req_status) && is_array($req_status)) {
                $req_status['code'] = (int) $req_status['code'];
                if (200 === $req_status['code']) {
                    $msg = __('Purging cache was successful.', self::$textdomain);
                    if (!empty($req_status['is_redis'])) {
                        $msg = __('Purging redis object cache was successful.', self::$textdomain);
                    }

                    $notice_type = 'success';
                } elseif (501 === $req_status['code'] || 400 === $req_status['code'] || 403 === $req_status['code'] || 404 === $req_status['code'] || 405 === $req_status['code']) {
                    $msg = sprintf(__('Purging method not implement. Status Code %s', self::$textdomain), $req_status['code']);

                    if (!empty($req_status['is_redis']) && 404 === $req_status['code']) {
                        if (!empty($req_status['is_avail'])) {
                            if (!empty($req_status['is_connect'])) {
                                $msg = __('Redis server not connected.', self::$textdomain);
                            } else {
                                $msg = __('Failed to purge redis object cache.', self::$textdomain);
                            }
                        } else {
                            $msg = __('Purging method not implement.', self::$textdomain);
                        }
                    }

                } else {
                    $msg = sprintf(__('Failed to purge cache. Status Code %s', self::$textdomain), $req_status['code']);
                }
            }

            $html = '';
            $html .= '<div class="notice notice-' . $notice_type . ' is-dismissible">';
            $html .= '<p><strong>' . self::$name . ':</strong>&nbsp;' . $msg . '</p>';
            $html .= '</div>';

            echo $html;
        }
        delete_transient('rcpurge/callback_flushcache');
    }

    /**
     * get_post_terms_urls.
     */
    private static function get_post_terms_urls($post_id)
    {
        $urls       = [];
        $taxonomies = get_object_taxonomies(get_post_type($post_id), 'objects');

        foreach ($taxonomies as $taxonomy) {
            if (!$taxonomy->public) {
                continue;
            }

            if (class_exists('WooCommerce')) {
                if ('product_shipping_class' === $taxonomy->name) {
                    continue;
                }
            }

            $terms = get_the_terms($post_id, $taxonomy->name);

            if (!empty($terms)) {
                foreach ($terms as $term) {
                    $term_url = get_term_link($term->slug, $taxonomy->name);
                    if (!is_wp_error($term_url)) {
                        $urls[] = $term_url;
                    }
                }
            }
        }

        return $urls;
    }

    /**
     * get_post_dates_urls.
     */
    private static function get_post_dates_urls($post_id)
    {
        $date       = explode('-', get_the_time('Y-m-d', $post_id));
        $link_year  = trailingslashit(get_year_link($date[0]));
        $link_month = trailingslashit(get_month_link($date[0], $date[1]));
        $link_day   = trailingslashit(get_day_link($date[0], $date[1], $date[2]));
        $urls       = [
            $link_year,
            $link_month,
            $link_day,
        ];

        if (is_object($GLOBALS['wp_rewrite'])) {
            $pagination_base = trailingslashit($GLOBALS['wp_rewrite']->pagination_base);
            $urls[]          = $link_year . $pagination_base;
            $urls[]          = $link_month . $pagination_base;
        }

        return $urls;
    }

    /**
     * flush_object.
     */
    public static function flush_object($purge = false)
    {
        $ok         = false;
        $filesave   = WP_CONTENT_DIR . '/object-cache.php';
        $is_avail   = (file_exists($filesave) ? true : false);
        $is_connect = self::is_redis_connect(null, null, $redis_error);

        if ($is_connect) {
            $ok = wp_cache_flush();
        }

        $return = [
            'code'       => ($ok ? 200 : 404),
            'is_redis'   => 1,
            'is_avail'   => ($is_avail ? 1 : 0),
            'is_connect' => ($is_connect ? 1 : 0),
        ];

        if (!$is_connect && !empty($redis_error)) {
            $return['is_connect_error'] = $redis_error;
        }

        if (!$purge) {
            self::$req_status = $return;
        }

        self::debug(__METHOD__, $return);

        return $return;
    }

    /**
     * flush_feed.
     */
    public static function flush_feed()
    {

        if (self::is_defined_halt()) {
            return;
        }

        $urls   = [];
        $urls[] = get_feed_link();
        $urls[] = get_feed_link('comments_');
        if (!empty($urls)) {
            foreach ($urls as $url) {
                self::request_purge_url($url);
            }
        }
    }

    /**
     * flush_url.
     */
    public static function flush_url($url)
    {

        if (self::is_defined_halt()) {
            return;
        }

        return self::request_purge_url($url);
    }

    /**
     * flush_content.
     */
    public static function flush_content()
    {

        if (self::is_defined_halt()) {
            return;
        }

        $post_types = get_post_types(['public' => true]);
        $post_types = array_filter($post_types, 'is_post_type_viewable');

        $numberposts = 100;
        $data_post   = get_posts(
            [
                'numberposts'    => $numberposts,
                'posts_per_page' => -1,
                'orderby'        => 'modified',
                'post_type'      => $post_types,
                'post_status'    => 'publish',
                'order'          => 'DESC',
            ]
        );

        if (!empty($data_post) && is_array($data_post)) {
            foreach ($data_post as $post) {

                if (!empty($post->post_password)) {
                    continue;
                }

                $url = get_permalink($post);
                if (false !== $url) {
                    self::request_purge_url($url);
                }
            }
        }

        self::flush_archives();
        self::flush_feed();
    }

    /**
     * flush_archives.
     */
    public static function flush_archives()
    {

        if (self::is_defined_halt()) {
            return;
        }

        // category
        $categories = get_categories(
            [
                'hide_empty' => true,
                'orderby'    => 'name',
                'order'      => 'DESC',
                'parent'     => 0,
            ]
        );

        if (!empty($categories) && is_array($categories)) {
            foreach ($categories as &$category) {
                if (!empty($category->term_id)) {
                    $url = get_category_link($category->term_id);
                    if (false !== $url) {
                        self::request_purge_url($url);
                    }
                }
            }
        }

        // tag
        $tags = get_tags(
            [
                'hide_empty' => true,
                'orderby'    => 'name',
                'order'      => 'DESC',
                'parent'     => 0,
            ]
        );

        if (!empty($tags) && is_array($tags)) {
            foreach ($tags as &$tag) {
                if (!empty($tag->term_id)) {
                    $url = get_tag_link($tag->term_id);
                    if (false !== $url) {
                        self::request_purge_url($url);
                    }
                }
            }
        }
    }

    /**
     * flush_home.
     */
    public static function flush_home($purge = false)
    {
        if (self::is_defined_halt()) {
            return;
        }

        if ($purge) {
            return self::request_purge_all();
        }

        $home_url = get_home_url('/');
        self::request_purge_url($home_url);
    }

    /**
     * flush_post.
     */
    public static function flush_post($post_id)
    {
        if (defined('DOING_AUTOSAVE') || self::is_defined_halt()) {
            return;
        }

        $post_data = get_post($post_id);
        if (!is_object($post_data)) {
            return;
        }

        if ('auto-draft' === $post_data->post_status
            || empty($post_data->post_type)
            || 'attachment' === $post_data->post_type
            || 'nav_menu_item' === $post_data->post_type) {
            return;
        }

        $post_type = get_post_type_object($post_data->post_type);
        if (!is_object($post_type) || true !== $post_type->public) {
            return;
        }

        // vars
        $purge_data      = [];
        $purge_permalink = '';

        // permalink
        $permalink = get_permalink($post_id);
        if (false !== strpos($permalink, '?')) {
            // fix permalink url when status set to trashed
            if (!function_exists('get_sample_permalink')) {
                include_once ABSPATH . 'wp-admin/includes/post.php';
            }
            $permalink_structure = get_sample_permalink($post_id);
            $permalink           = str_replace(array('%postname%', '%pagename%'), $permalink_structure[1], $permalink_structure[0]);
        }

        if ('/' !== parse_url($permalink, PHP_URL_PATH)) {
            $purge_permalink = str_replace('__trashed/', '/', $permalink);
        }
        unset($permalink);

        // post page
        $page_for_posts_id = (int) get_option('page_for_posts');
        if ('post' === $post_data->post_type && $page_for_posts_id > 0) {
            $purge_data[] = get_permalink($page_for_posts_id);
        }

        // archive
        if ('post' !== $post_data->post_type) {
            $post_type_archive = get_post_type_archive_link(get_post_type($post_id));
            if ($post_type_archive && self::$is_purge_archives) {
                $post_type_archive = trailingslashit($post_type_archive);
                $purge_data[]      = $post_type_archive;
                if (is_object($GLOBALS['wp_rewrite'])) {
                    $purge_data[] = $post_type_archive . trailingslashit($GLOBALS['wp_rewrite']->pagination_base);
                }
            }
        }

        // next post
        $next_post = get_adjacent_post(false, '', false);
        if ($next_post) {
            $purge_data[] = get_permalink($next_post);
        }

        // next post in same category
        $next_post_same_cat = get_adjacent_post(true, '', false);
        if ($next_post_same_cat && $next_post_same_cat !== $next_post) {
            $purge_data[] = get_permalink($next_post_same_cat);
        }

        // previous post
        $previous_post = get_adjacent_post(false, '', true);
        if ($previous_post) {
            $purge_data[] = get_permalink($previous_post);
        }

        // previous post in same category
        $previous_post_same_cat = get_adjacent_post(true, '', true);
        if ($previous_post_same_cat && $previous_post_same_cat !== $previous_post) {
            $purge_data[] = get_permalink($previous_post_same_cat);
        }

        // terms archive page
        if (self::$is_purge_archives) {
            $purge_terms = self::get_post_terms_urls($post_id);
            if (!empty($purge_terms) && is_array($purge_terms)) {
                $purge_data = array_merge($purge_data, $purge_terms);
            }

            // dates archive page
            $purge_dates = self::get_post_dates_urls($post_id);
            if (!empty($purge_dates) && is_array($purge_dates)) {
                $purge_data = array_merge($purge_data, $purge_dates);
            }

            // author page
            $purge_author = array(get_author_posts_url($post_data->post_author));
            if (!empty($purge_author) && is_array($purge_author)) {
                $purge_data = array_merge($purge_data, $purge_author);
            }
        }

        // all parents
        $parents = get_post_ancestors($post_id);
        if (!empty($parents) && is_array($parents)) {
            foreach ($parents as $parent_id) {
                $purge_data[] = get_permalink($parent_id);
            }
        }

        if (!empty($purge_data) && is_array($purge_data) && count($purge_data) > 0) {
            foreach ($purge_data as $url) {
                self::request_purge_url($url);
            }
        }

        if (!empty($purge_permalink) && self::$is_purge_home) {
            self::request_purge_url($purge_permalink);
        }

        if (self::$is_purge_archives) {
            self::flush_feed();
        }
    }

    /**
     * upgrader_process_complete_callback.
     */
    public static function upgrader_process_complete_callback($wp_upgrader, $options)
    {

        if (self::is_defined_halt()) {
            return;
        }

        if ('update' !== $options['action']) {
            return;
        }

        // me update
        if ('plugin' === $options['type'] && !empty($options['plugins'])) {
            if (!is_array($options['plugins'])) {
                return;
            }
            foreach ($options['plugins'] as $plugin) {
                if ($plugin === self::$hook) {
                    self::flush_home(true);
                    break;
                }
            }
        }

        // theme update
        if ('theme' === $options['type']) {
            $current_theme = wp_get_theme();
            $themes        = [
                $current_theme->get_template(),
                $current_theme->get_stylesheet(),
            ];

            if (!array_intersect($options['themes'], $themes)) {
                return;
            }

            self::flush_home(true);
        }
    }

    /**
     * widget_update_callback.
     */
    public static function widget_update_callback($obj)
    {

        if (self::is_defined_halt()) {
            return;
        }

        self::flush_home(true);
        return $obj;
    }

    /**
     * purge_woo_product_variation.
     */
    public static function purge_woo_product_variation($variation_id)
    {

        if (self::is_defined_halt()) {
            return;
        }

        $product_id = wp_get_post_parent_id($variation_id);

        if (!empty($product_id)) {
            self::$is_purge_home     = true;
            self::$is_purge_content  = true;
            self::$is_purge_archives = true;
            self::$is_purge_object   = true;

            self::flush_post($product_id);

            self::reset_settings();
        }
    }

    /**
     * Register all of the hooks related to the purging.
     */
    public static function register_purge_hooks()
    {
        if (self::$is_purge_home || self::$is_purge_content || self::$is_purge_archives) {
            add_action('edit_post', [__CLASS__, 'flush_post']);
        }

        if (self::$is_purge_home) {
            add_action('save_post', [__CLASS__, 'flush_home']);
        }

        if (self::$is_purge_home || self::$is_purge_content || self::$is_purge_archives) {
            add_action('wp_trash_post', [__CLASS__, 'flush_post']);
            add_action('delete_post', [__CLASS__, 'flush_post']);
            add_action('clean_post_cache', [__CLASS__, 'flush_post']);
            add_action('wp_update_comment_count', [__CLASS__, 'flush_post']);
        }

        if (self::$is_purge_home) {
            add_action('switch_theme', [__CLASS__, 'flush_home']);
            add_action('user_register', [__CLASS__, 'flush_home']);
            add_action('profile_update', [__CLASS__, 'flush_home']);
            add_action('deleted_user', [__CLASS__, 'flush_home']);
            add_action('wp_update_nav_menu', [__CLASS__, 'flush_home']);
            add_action('update_option_sidebars_widgets', [__CLASS__, 'flush_home']);
            add_action('update_option_category_base', [__CLASS__, 'flush_home']);
            add_action('update_option_tag_base', [__CLASS__, 'flush_home']);
            add_action('permalink_structure_changed', [__CLASS__, 'flush_home']);
            add_action('create_term', [__CLASS__, 'flush_home']);
            add_action('edited_terms', [__CLASS__, 'flush_home']);
            add_action('delete_term', [__CLASS__, 'flush_home']);
            add_action('add_link', [__CLASS__, 'flush_home']);
            add_action('edit_link', [__CLASS__, 'flush_home']);
            add_action('delete_link', [__CLASS__, 'flush_home']);
            add_action('customize_save', [__CLASS__, 'flush_home']);
        }

        if (self::$is_purge_home || self::$is_purge_content || self::$is_purge_archives) {
            add_action('update_option_theme_mods_' . get_option('stylesheet'), [__CLASS__, 'flush_home']);
            add_action('upgrader_process_complete', [__CLASS__, 'upgrader_process_complete_callback'], 10, 2);
            add_action('woocommerce_save_product_variation', [__CLASS__, 'purge_woo_product_variation']);

            add_filter('widget_update_callback', [__CLASS__, 'widget_update_callback']);
        }
    }

    /**
     * __shutdown.
     */
    private static function __shutdown()
    {
        add_action('shutdown', function () {
            wp_cache_flush(0);
            wp_cache_delete('alloptions', 'options');

            if (function_exists('wp_cache_self_remove') && defined('RUNCACHE_PURGER_DROPIN')) {
                wp_cache_self_remove();
            }
        });
    }

    /**
     * activate.
     */
    public static function activate()
    {
        self::install_options();
        self::install_dropin();
    }

    /**
     * deactivate.
     */
    public static function deactivate()
    {
        self::force_site_deactivate_plugin();
        self::__shutdown();
    }

    /**
     * uninstall.
     */
    public static function uninstall()
    {
        self::uninstall_options();
        self::__shutdown();
    }

    /**
     * register_hook.
     */
    public static function register_plugin_hooks()
    {
        register_activation_hook(RUNCACHE_PURGER_HOOK, [__CLASS__, 'activate']);
        register_deactivation_hook(RUNCACHE_PURGER_HOOK, [__CLASS__, 'deactivate']);
        register_uninstall_hook(RUNCACHE_PURGER_HOOK, [__CLASS__, 'uninstall']);
    }

    /**
     * reinstall_options.
     */
    public static function reinstall_options()
    {
        self::uninstall_options();
        self::install_options();
    }

    /**
     * reinstall_dropin.
     */
    public static function reinstall_dropin()
    {
        self::uninstall_dropin();
        return self::install_dropin();
    }

    /**
     * register_wpcli_hooks.
     */
    public static function register_wpcli_hooks()
    {
        if (self::is_wp_cli()) {
            require_once RUNCACHE_PURGER_PATH . 'runcache-purger-cli.php';
        }
    }

    /**
     * Merge one or more arrays recursively.
     */
    private static function array_merge_recm()
    {

        if (func_num_args() < 2) {
            trigger_error(__FUNCTION__ . ' invalid input', E_USER_WARNING);
            return;
        }

        $arrays = func_get_args();
        $merged = [];

        while ($array = @array_shift($arrays)) {

            if (!is_array($array)) {
                trigger_error(__FUNCTION__ . ' invalid input', E_USER_WARNING);
                return;
            }

            if (empty($array)) {
                continue;
            }

            foreach ($array as $key => $value) {
                if (is_string($key)) {
                    if (is_array($value) && array_key_exists($key, $merged) && is_array($merged[$key])) {
                        $merged[$key] = self::array_merge_recm($merged[$key], $value);
                    } else {
                        $merged[$key] = $value;
                    }
                } else {
                    $merged[] = $value;
                }
            }
        }
        return $merged;
    }

    /**
     * get_fileperms.
     */
    private static function get_fileperms($type)
    {
        static $perms = [];

        if (isset($perms[$type])) {
            return $perms[$type];
        }

        if ('dir' == $type) {

            if (defined('FS_CHMOD_DIR')) {
                $perms[$type] = FS_CHMOD_DIR;
            } else {
                clearstatcache();
                $perms[$type] = fileperms(ABSPATH) & 0777 | 0755;
            }

            return $perms[$type];

        }

        if ('file' == $type) {

            if (defined('FS_CHMOD_FILE')) {
                $perms[$type] = FS_CHMOD_FILE;
            } else {
                clearstatcache();
                $perms[$type] = fileperms(ABSPATH . 'index.php') & 0777 | 0644;
            }

            return $perms[$type];
        }

        return 0755;
    }

    /**
     * is_debugging.
     */
    private static function is_debugging()
    {
        return (defined('RUNCACHE_PURGER_DEBUG') && RUNCACHE_PURGER_DEBUG);
    }

    /**
     * debug.
     */
    private static function debug($caller, $data)
    {
        if (!self::is_debugging()) {
            return false;
        }

        $log = [
            'timestamp' => gmdate('Y-m-d H:i:s') . ' UTC',
            'caller'    => $caller,
        ];

        if (!empty($data) && is_array($data)) {
            $log = self::array_merge_recm($log, $data);
        } else {
            $log['data'] = $data;
        }

        self::debug_log($log);
    }

    /**
     * array_export.
     */
    public static function array_export($data)
    {
        $data_e = var_export($data, true);
        $data_e = str_replace('Requests_Utility_CaseInsensitiveDictionary::__set_state(', '', $data_e);

        $data_e = preg_replace('/^([ ]*)(.*)/m', '$1$1$2', $data_e);
        $data_r = preg_split("/\r\n|\n|\r/", $data_e);

        $data_r = preg_replace(['/\s*array\s\($/', '/\)(,)?$/', '/\s=>\s$/'], [null, ']$1', ' => ['], $data_r);
        return join(PHP_EOL, array_filter(['['] + $data_r));
    }

    /**
     * debug_log.
     */
    private static function debug_log($data, $filesave = '')
    {

        if (empty($filesave)) {
            $fname    = str_replace(' ', '_', self::$slug);
            $filesave = WP_CONTENT_DIR . '/' . $fname . '.log';
        }

        if (is_dir($filesave)) {
            return false;
        }

        $output = (is_array($data) || is_object($data) ? self::array_export($data) . ',' : $data) . PHP_EOL;

        $perm = self::get_fileperms('file');
        if (file_put_contents($filesave, $output, FILE_APPEND)) {
            @chmod($filesave, $perm);
            return true;
        }

        return false;
    }

    /**
     * cli_purge_all.
     */
    public static function cli_purge_all($type, $host = null)
    {
        $_res = [];

        // reset
        $host_url       = (!empty($host) ? $host : get_site_url());
        $proto          = parse_url($host_url, PHP_URL_SCHEME);
        self::$hostname = parse_url($host_url, PHP_URL_HOST);

        // maybe port 443 is open. Then check if we should use the https proto instead
        if ('https' !== $proto && fsockopen('tls://' . self::$hostname, 443)) {
            $proto = 'https';
        }

        self::request_purge_all($type, $proto);

        if (!self::is_debugging()) {
            $_res = [
                'code'   => self::$req_status['code'],
                'status' => self::$req_status['status'],
                //'host' => self::$req_status['request_host'],
            ];
        } else {
            $_res = self::$req_status;
        }

        //$_res['purge_type'] = $type;
        return $_res;
    }

    /**
     * attach.
     */
    public static function attach()
    {
        self::register_wpcli_hooks();
        if (self::register()) {
            self::register_init();
            self::register_plugin_hooks();
            self::register_admin_hooks();
            self::register_purge_hooks();
        }
    }
}

RunCache_Purger::attach();
