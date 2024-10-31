<?php
/**
 * Contains class for WP-CLI command.
 */

if (!defined('RUNCACHE_PURGER_PATH')) {
    exit;
}

class RunCache_Purger_CLI extends \WP_CLI_Command
{
    public function purgeall($args, $assoc_args)
    {

        $__varfunc_do_purge = function ($host = null) {
            $ok     = false;
            $ret    = 0;
            $_types = ['fastcgi', 'proxy'];

            foreach ($_types as $type) {
                $_statuses = RunCache_Purger::cli_purge_all($type, $host);
                if (!empty($_statuses) && is_array($_statuses)) {
                    $ok = (200 === (int) $_statuses['code'] ? true : false);

                    $message = RunCache_Purger::array_export($_statuses);
                    if ($ok) {
                        WP_CLI::success($message, false);
                    } else {
                        WP_CLI::error($message, false);
                        $ret = 1;
                    }
                }
            }
            return $ret;
        };

        $ret = 0;
        if (is_multisite()) {
            $sites = get_sites();
            foreach ($sites as $site) {
                switch_to_blog($site->blog_id);
                $host = get_site_url();
                $ret = $__varfunc_do_purge($host);
                restore_current_blog();
            }
        } else {
            $ret = $__varfunc_do_purge();
        }

        // proper exit
        WP_CLI::halt($ret);
    }

    public function purgeredis()
    {
        $res     = RunCache_Purger::flush_object(true);
        $message = RunCache_Purger::array_export($res);
        WP_CLI::success($message, false);
        WP_CLI::halt(0);
    }

    public function reinstall($args, $assoc_args)
    {
        $ok = false;
        if (!empty($args) && is_array($args)) {
            $arg = $args[0];
            if ('options' === $arg) {
                RunCache_Purger::reinstall_options();
                WP_CLI::success('Reinstall options success', false);
                $ok = true;
            } elseif ('dropin' === $arg) {
                if (RunCache_Purger::reinstall_dropin()) {
                    WP_CLI::success('Reinstall dropin success', false);
                    $ok = true;
                } else {
                    WP_CLI::error('Failed to reinstall dropin', false);
                    WP_CLI::halt(1);
                }
            }
        }

        if ($ok) {
            WP_CLI::halt(0);
        }

        WP_CLI::error('Invalid parameter. Usage: reinstall [options, dropin]', false);
        WP_CLI::halt(1);
    }
}

WP_CLI::add_command('runcache-purger', 'RunCache_Purger_CLI', ['shortdesc' => 'Purge NGINX fastcgi, Proxy cache and Redis Object Cache on RunCloud.io.']);
