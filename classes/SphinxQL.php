<?php

class SphinxQL
{
    private static $instance = false;
    
    //private constructr
    private function __construct()
    {
        $config = Config::get();

        R::addDatabase('sphinx', $config->sphinxql->dsn, $config->sphinxql->user, $config->sphinxql->pass, true);
    }
    
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }
    
    public static function query($sql, $params = array())
    {
        self::getInstance();
    
        R::selectDatabase('sphinx');
        
        $result = R::getAll($sql, $params);
        
        R::selectDatabase('default');
        
        return $result;
    }

    public static function getMeta()
    {
        self::getInstance();
    
        R::selectDatabase('sphinx');
        
        $result = R::getAll('show meta');

        $obj = new stdClass();
        foreach ($result as $param) {
            $name = $param['Variable_name'];
            $value = $param['Value'];

            if (preg_match('/^(.*)\[\d*\]$/', $name, $matches) === 1) {
                $name = $matches[1];

                if (!isset($obj->$name) || !is_array($obj->$name)) {
                    $obj->$name = array();
                }

                array_push($obj->$name, $value);
            } else {
                $obj->$name = $value;
            }
        }

        R::selectDatabase('default');
        
        return $obj;
    }
    
    public static function getIds($result)
    {
        $ids = array();
        foreach ($result as $row) {
            $ids[] = $row['id'];
        }
        
        return $ids;
    }

    /**
     * Escapes the query for the MATCH() function
     * Allows some of the control characters to pass through for use with a search field: -, |, "
     * It also does some tricks to wrap/unwrap within " the string and prevents errors
     *
     * @param string $string The string to escape for the MATCH
     *
     * @return string The escaped string
     */
    public static function halfEscapeMatch($string)
    {
        $from_to = array(
            '\\' => '\\\\',
            '(' => '\(',
            ')' => '\)',
            '!' => '\!',
            '@' => '\@',
            '~' => '\~',
            '&' => '\&',
            '/' => '\/',
            '^' => '\^',
            '$' => '\$',
            '=' => '\=',
        );

        $string = str_replace(array_keys($from_to), array_values($from_to), $string);

        // this manages to lower the error rate by a lot
        if (substr_count($string, '"') % 2 !== 0) {
            $string .= '"';
        }

        $from_to_preg = array(
            "'\"([^\s]+)-([^\s]*)\"'" => "\\1\-\\2",
            "'([^\s]+)-([^\s]*)'" => "\"\\1\-\\2\""
        );

        $string = mb_strtolower(preg_replace(array_keys($from_to_preg), array_values($from_to_preg), $string));

        return $string;
    }
}
