<?php
use Symfony\Component\DomCrawler\Crawler;

abstract class ExPage_Abstract
{
    /**
     * @var Crawler
     */
    protected $crawler;

    public function __construct($html)
    {
        $this->crawler = new Crawler($html);
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
