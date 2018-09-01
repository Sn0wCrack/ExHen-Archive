<?php
class Exceptions_CrawlException extends Exceptions_ExHentaiException
{
    /**
     * @var string
     */
    private $html;

    public function __construct($html, $message = "", $code = 0, Throwable $previous = null)
    {

        $this->html = $html;
        parent::__construct($message, $code, $previous);
    }

    /**
     * @return string
     */
    public function getHtml()
    {
        return $this->html;
    }
}
