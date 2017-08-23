<?php

class Auth
{
    public static function doAuth()
    {
        $setCookie = true;
        if (array_key_exists('PHP_AUTH_PW', $_SERVER)) {
            $hash = sha1($_SERVER['PHP_AUTH_PW']);
        } elseif (array_key_exists('baka', $_COOKIE)) {
            $setCookie = false;
            $hash = $_COOKIE['baka'];
        } elseif (array_key_exists('key', $_REQUEST)) {
            $hash = sha1($_REQUEST['key']);
        } else {
            header('WWW-Authenticate: Basic realm=""');
            http_response_code(401);
            exit;
        }

        if ($hash === sha1(Config::get()->accessKey)) {
            if ($setCookie) {
                setcookie('baka', $hash, strtotime('+1 year'));
            }
        } else {
            http_response_code(401);
            exit;
        }
    }
}
