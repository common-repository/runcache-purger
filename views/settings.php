<?php 
defined( 'RUNCACHE_PURGER_INIT' ) || exit; 
?>
<!-- runcachepurger-wrap -->
<div class="runcachepurger-wrap wrap" id="runcachepurger-wrap">
    <h1 class="screen-reader-text">
        <?php printf( __( '%s Settings', self::$slug ), self::$name ); ?>
    </h1>

    <!-- runcachepurger-body -->
    <div class="runcachepurger-body">

        <!-- runcachepurger-content -->
        <section class="runcachepurger-content">

            <div class="runcachepurger-section-header">
                <h2 class="runcachepurger-title1"><img src="<?php echo self::$plugin_url_logo;?>" width="57" alt="RunCloud Logo" class="runcachepurger-header-logo-big"> RunCache Purger</h2>
            </div>

            <div class="runcachepurger-page-row">
                <div class="runcachepurger-page-col">

                    <form action="options.php" method="POST" id="<?php echo esc_attr( self::$slug ); ?>-options">
                    <?php settings_fields( self::$slug ); ?>
                    <!-- purge-homepage -->
                    <div class="runcachepurger-field-container">
                        <div class="runcachepurger-optionheader">
                            <h3 class="runcachepurger-title2"><?php _e( 'Purge Homepage Setting', self::$textdomain ); ?></h3>
                        </div>

                        <fieldset class="runcachepurger-field-container-fieldset">
                            <div class="runcachepurger-field runcachepurger-field-checkbox">
                                <div class="runcachepurger-checkbox">
                                    <input type="checkbox" id="homepage_post_onn" name="<?php self::view_fname('homepage_post_onn');?>" value="1"<?php self::view_checked('homepage_post_onn');?>>
                                    <label for="homepage_post_onn"><?php _e( 'New or Updated Post', self::$textdomain ); ?></label>
                                </div>

                                <div class="runcachepurger-field-description">
                                    <?php _e( 'Automatically clean cache of homepage when post is edited or has a new post.', self::$textdomain ); ?>
                                </div>
                            </div>

                            <div class="runcachepurger-field runcachepurger-field-checkbox">
                                <div class="runcachepurger-checkbox">
                                    <input type="checkbox" id="homepage_removed_onn" name="<?php self::view_fname('homepage_removed_onn');?>" value="1"<?php self::view_checked('homepage_removed_onn');?>>
                                    <label for="homepage_removed_onn"><?php _e( 'Post Removed', self::$textdomain ); ?></label>
                                </div>

                                <div class="runcachepurger-field-description">
                                    <?php _e( 'Automatically clean cache of homepage when post removed.', self::$textdomain ); ?>
                                </div>
                            </div>

                        </fieldset>
                    </div>
                    <!-- /purge-homepage -->

                    <!-- purge-content -->
                    <div class="runcachepurger-field-container">
                        <div class="runcachepurger-optionheader">
                            <h3 class="runcachepurger-title2"><?php _e( 'Purge Content Setting', self::$textdomain ); ?></h3>
                        </div>

                        <fieldset class="runcachepurger-field-container-fieldset">
                            <div class="runcachepurger-field runcachepurger-field-checkbox">
                                <div class="runcachepurger-checkbox">
                                    <input type="checkbox" id="content_publish_onn" name="<?php self::view_fname('content_publish_onn');?>" value="1"<?php self::view_checked('content_publish_onn');?>>
                                    <label for="content_publish_onn"><?php _e( 'Published Content', self::$textdomain ); ?></label>
                                </div>

                                <div class="runcachepurger-field-description">
                                    <?php _e( 'Automatically clean cache of content when published.', self::$textdomain ); ?>
                                </div>
                            </div>

                            <div class="runcachepurger-field runcachepurger-field-checkbox">
                                <div class="runcachepurger-checkbox">
                                    <input type="checkbox" id="content_comment_approved_onn" name="<?php self::view_fname('content_comment_approved_onn');?>" value="1"<?php self::view_checked('content_comment_approved_onn');?>>
                                    <label for="content_comment_approved_onn"><?php _e( 'Comment Approved', self::$textdomain ); ?></label>
                                </div>

                                <div class="runcachepurger-field-description">
                                    <?php _e( 'Automatically clean cache of content when comment approved and published.', self::$textdomain ); ?>
                                </div>
                            </div>

                            <div class="runcachepurger-field runcachepurger-field-checkbox">
                                <div class="runcachepurger-checkbox">
                                    <input type="checkbox" id="content_comment_removed_onn" name="<?php self::view_fname('content_comment_removed_onn');?>" value="1"<?php self::view_checked('content_comment_removed_onn');?>>
                                    <label for="content_comment_removed_onn"><?php _e( 'Comment Removed', self::$textdomain ); ?></label>
                                </div>

                                <div class="runcachepurger-field-description">
                                    <?php _e( 'Automatically clean cache of content when comment removed.', self::$textdomain ); ?>
                                </div>
                            </div>

                        </fieldset>
                    </div>
                    <!-- /purge-content -->

                    <!-- purge-archives -->
                    <div class="runcachepurger-field-container">
                        <div class="runcachepurger-optionheader">
                            <h3 class="runcachepurger-title2"><?php _e( 'Purge Archives Setting', self::$textdomain ); ?></h3>
                        </div>

                        <fieldset class="runcachepurger-field-container-fieldset">
                            <div class="runcachepurger-field runcachepurger-field-checkbox">
                                <div class="runcachepurger-checkbox">
                                    <input type="checkbox" id="archives_homepage_onn" name="<?php self::view_fname('archives_homepage_onn');?>" value="1"<?php self::view_checked('archives_homepage_onn');?>>
                                    <label for="archives_homepage_onn"><?php _e( 'Homepage Purged', self::$textdomain ); ?></label>
                                </div>

                                <div class="runcachepurger-field-description">
                                    <?php _e( 'Automatically clean cache of archives when any action at homepage option was purged.', self::$textdomain ); ?>
                                </div>
                            </div>

                            <div class="runcachepurger-field runcachepurger-field-checkbox">
                                <div class="runcachepurger-checkbox">
                                    <input type="checkbox" id="archives_content_onn" name="<?php self::view_fname('archives_content_onn');?>" value="1"<?php self::view_checked('archives_content_onn');?>>
                                    <label for="archives_content_onn"><?php _e( 'Content Purged', self::$textdomain ); ?></label>
                                </div>

                                <div class="runcachepurger-field-description">
                                    <?php _e( 'Automatically clean cache of archives when any action at content option was purged.', self::$textdomain ); ?>
                                </div>
                            </div>
                        </fieldset>
                    </div>
                    <!-- /purge-archives -->

                    <!-- purge-redis -->
                    <div class="runcachepurger-field-container">
                        <div class="runcachepurger-optionheader">
                            <h3 class="runcachepurger-title2"><?php _e( 'Redis Object Cache', self::$textdomain ); ?></h3>
                        </div>

                        <fieldset class="runcachepurger-field-container-fieldset">
                            <div class="runcachepurger-field cache-redis">
                                <h4>Redis Server</h4>
                                <label for="redis_prefix"><?php _e( 'Status', self::$textdomain ); ?></label>
                                <?php 
                                    $redis_stat = self::is_redis_connect(self::$value['redis_host'], self::$value['redis_port']);
                                    $redis_stat_text = ( $redis_stat ? 'Connected' : ( function_exists('fsockopen') ? 'Not connected' : 'fsockopen disabled' ) );
                                ?>
                                <div class='redis-status<?php echo ( $redis_stat ? ' redis-status-ok' : ' redis-status-ko');?>'><?php echo $redis_stat_text;?></div>
                                <br>
                                <label for="redis_host"><?php _e( 'Hostname', self::$textdomain ); ?></label>
                                <input type="text" id="redis_host" name="<?php self::view_fname('redis_host');?>" value="<?php self::view_fvalue('redis_host');?>">
                                <br>
                                <label for="redis_port"><?php _e( 'Port', self::$textdomain ); ?></label>
                                <input type="number" min=1 id="redis_port" name="<?php self::view_fname('redis_port');?>" value="<?php self::view_fvalue('redis_port');?>">
                                <br>
                                <label for="redis_prefix"><?php _e( 'Key Prefix', self::$textdomain ); ?></label>
                                <input type="text" id="redis_prefix" name="<?php self::view_fname('redis_prefix');?>" value="<?php self::view_fvalue('redis_prefix');?>">
                            </div>

                            <div class="runcachepurger-field runcachepurger-field-checkbox">
                                <div class="runcachepurger-checkbox">
                                    <input type="checkbox" id="redis_purge_onn" name="<?php self::view_fname('redis_purge_onn');?>" value="1"<?php self::view_checked('redis_purge_onn');?>>
                                    <label for="redis_purge_onn"><?php _e( 'Purge Object Cache', self::$textdomain ); ?></label>
                                </div>

                                <div class="runcachepurger-field-description">
                                    <?php _e( 'Automatically clean redis object cache when any purge action was triggered.', self::$textdomain ); ?>
                                </div>
                            </div>

                            <div class="runcachepurger-field runcachepurger-field-checkbox">
                                <div class="runcachepurger-checkbox">
                                    <input type="checkbox" id="redis_cache_onn" name="<?php self::view_fname('redis_cache_onn');?>" value="1"<?php self::view_checked('redis_cache_onn');?>>
                                    <label for="redis_cache_onn"><?php _e( 'Enable Object Cache', self::$textdomain ); ?></label>
                                </div>

                                <div class="runcachepurger-field-description">
                                    <?php _e( 'Enable this option to allow this plugin handle redis object cache.', self::$textdomain ); ?>
                                </div>
                            </div>

                        </fieldset>
                    </div>
                    <!-- /purge-redis -->

                    <input type="submit" class="runcachepurger-button runcachepurger-button-submit" id="runcachepurger-options-submit" value="<?php esc_attr_e( 'Save Settings', self::$textdomain ); ?>">
                </form>
                </div> <!-- /col -->

                <div class="runcachepurger-page-col runcachepurger-page-col-fixed">
                    <div class="runcachepurger-optionheader">
                        <h3 class="runcachepurger-title2"><?php _e( 'Purge Actions', self::$textdomain ); ?></h3>
                    </div>

                    <fieldset class="runcachepurger-field-container-fieldset">
                        <div class="runcachepurger-field runcachepurger-field-purge">
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=flushcache&type=all'.self::append_wp_http_referer() ), 'purge_cache_all' );?>" class="runcachepurger-button runcachepurger-button-clearcache"><?php _e( 'Clear All Cache', self::$textdomain ); ?></a>
                        </div>

                        <div class="runcachepurger-field runcachepurger-field-purge">
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=flushcache&type=homepage'.self::append_wp_http_referer() ), 'purge_cache_homepage' );?>" class="runcachepurger-button runcachepurger-button-clearcache"><?php _e( 'Clear Homepage Cache', self::$textdomain ); ?></a>
                        </div>

                        <div class="runcachepurger-field runcachepurger-field-purge">
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=flushcache&type=content'.self::append_wp_http_referer() ), 'purge_cache_content' );?>" class="runcachepurger-button runcachepurger-button-clearcache"><?php _e( 'Clear Content Cache', self::$textdomain ); ?></a>
                        </div>

                        <div class="runcachepurger-field runcachepurger-field-purge">
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=flushcache&type=archives'.self::append_wp_http_referer() ), 'purge_cache_archives' );?>" class="runcachepurger-button runcachepurger-button-clearcache"><?php _e( 'Clear Archives Cache', self::$textdomain ); ?></a>
                        </div>

                        <div class="runcachepurger-field runcachepurger-field-purge">
                            <a href="<?php echo wp_nonce_url( admin_url( 'admin-post.php?action=flushcache&type=redis'.self::append_wp_http_referer() ), 'purge_cache_redis' );?>" class="runcachepurger-button runcachepurger-button-clearcache"><?php _e( 'Clear Redis Cache', self::$textdomain ); ?></a>
                        </div>

                    </fieldset>

                </div> <!-- /col -->


            </div> <!-- /row -->

        </section>
        <!-- /runcachepurger-content -->
    </div>
    <!-- /runcachepurger-body -->

<!-- close notice -->
<script>
(function($) {
    "use strict";
    var $selector = $(document);
    var close_notice = function() {
        $selector.find( "div.notice.is-dismissible" )
            .find( "button.notice-dismiss" )
            .trigger( "click.wp-dismiss-notice" );
    };
    window.setTimeout(function() {
        close_notice();
    }, 10000);

    window.setTimeout( function() {
        $selector.scroll( function() {
            close_notice();
        } );
    }, 1000 );

})(jQuery);
</script>
<!-- /close notice -->

</div>
<!-- /runcachepurger-wrap -->

