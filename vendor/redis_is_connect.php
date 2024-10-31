<?php
if ( !function_exists('__redis_is_connect') ) {
    function __redis_is_connect($host, $port, &$error = '') {
        $host = ( !empty($host) ? $host : '127.0.0.1' );
        $port = ( !empty($port) ? (int)$port : 6379 );

        $ret = false;
        if ( function_exists('fsockopen') ) {
            $fp = @fsockopen('tcp://'.$host, $port, $errno, $errstr);
            $ret = ( $fp ? true : false );
            @fclose($fp);

            if ( !$ret ) {
                $error = $errno.' '.$errstr;
            }
        } else {
            $error = 'fsockopen disabled';
        }

        return $ret;
    }
}
