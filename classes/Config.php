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
        $cookieJar = new \Symfony\Component\BrowserKit\CookieJar();

        foreach((array)self::$config->cookie as $name => $value) {
            $cookieJar->set(new \Symfony\Component\BrowserKit\Cookie(
                $name,
                $value,
                null,
                null,
                '.exhentai.org'
            ));
        }

        return $cookieJar;
    }
}
