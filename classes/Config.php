<?php

class Config
{
    protected static $config;

    protected function __construct()
    {
    }

    public static function get()
    {
        if (!self::$config) {
            $data = json_decode(file_get_contents('config.json'));

            $config = $data->base;

            $host = gethostname();
            if (!self::processEntry($config, $data, $host)) {
                self::processEntry($config, $data, 'default');
            }

            self::$config = $config;
        }

        return self::$config;
    }

    protected static function processEntry(&$config, $data, $key)
    {
        if (property_exists($data, $key)) {
            if (property_exists($data->$key, 'inherits')) {
                self::processEntry($config, $data, $data->$key->inherits);
            }

            foreach ($data->$key as $key => $value) {
                $config->$key = $value;
            }

            return true;
        } else {
            return false;
        }
    }

    public static function buildCookie()
    {
        $cookie = array();
        foreach (self::$config->cookie as $var => $value) {
            $cookie[] = $var.'='.$value;
        }

        return implode('; ', $cookie);
    }

    public static function buildCookieJar()
    {
        return \GuzzleHttp\Cookie\CookieJar::fromArray((array)self::$config->cookie, '.exhentai.org');
    }
}
