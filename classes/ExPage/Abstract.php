<?php

abstract class ExPage_Abstract
{

    protected $doc;

    public function __construct($html)
    {
        $this->doc = phpQuery::newDocumentHTML($html);
    }

    public function getDocument()
    {
        return $this->doc;
    }

    public function find($selector)
    {
        return $this->doc->find($selector);
    }
}
