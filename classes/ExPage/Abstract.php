<?php
use Symfony\Component\DomCrawler\Crawler;

abstract class ExPage_Abstract
{

    /**
     * @var phpQueryObject|QueryTemplatesParse|QueryTemplatesSource|QueryTemplatesSourceQuery
     * @deprecated
     */
    protected $doc;
    /**
     * @var Crawler
     */
    protected $crawler;

    public function __construct($html)
    {
        $this->crawler = new Crawler($html);
        $this->doc = phpQuery::newDocumentHTML($html);
    }

    /**
     * @return phpQueryObject|QueryTemplatesParse|QueryTemplatesSource|QueryTemplatesSourceQuery
     * @deprecated
     */
    public function getDocument()
    {
        return $this->doc;
    }

    /**
     * @param $selector
     * @return phpQueryObject|QueryTemplatesParse|QueryTemplatesSource|QueryTemplatesSourceQuery
     * @deprecated
     */
    public function find($selector)
    {
        return $this->doc->find($selector);
    }

    /**
     * @param $selector
     * @return Crawler
     */
    public function findElement($selector)
    {
        return $this->crawler->filter($selector);
    }
}
