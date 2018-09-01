<?php

class Log
{
    const LOG_ERROR = 'error';
    const LOG_DEBUG = 'debug';
    
    public static function add($level, $tag, $message)
    {
        $vargs = array_slice(func_get_args(), 3);
        if (count($vargs) > 0) {
            $params = array_merge(array($message), $vargs);
            $message = call_user_func_array('sprintf', $params);
        }
        
        $fmt = "%s: [%s][%s] %s\n";

        if (substr(PHP_SAPI, 0, 3) == 'cli') {
            printf($fmt, strtoupper($level), date('Y-m-d H:i:s'), $tag, $message);
        }

    }
    
    public static function debug($tag, $message)
    {
        $args = array_merge(array(self::LOG_DEBUG), func_get_args());
        call_user_func_array('Log::add', $args);
    }
    
    public static function error($tag, $message)
    {
        $args = array_merge(array(self::LOG_ERROR), func_get_args());
        call_user_func_array('Log::add', $args);
    }
}
