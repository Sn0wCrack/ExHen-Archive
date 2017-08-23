<?php

class QueryHelper
{
    private $params = array();
    private $sql = array();

    public function sql($sql)
    {
        $this->sql[] = $sql;
    
        return $this;
    }

    public function addParams($params)
    {
        $this->params = array_merge($this->params, $params);

        return $this;
    }

    public function getSql()
    {
        return implode(' ', $this->sql);
    }

    public function getParams()
    {
        return $this->params;
    }
}
