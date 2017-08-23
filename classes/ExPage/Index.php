<?php

class ExPage_Index extends ExPage_Abstract
{
    public function isLastPage()
    {
        return (count($this->find('td.ptds + td.ptdd')) > 0);
    }

    public function getGalleries()
    {
        $ret = array();

        $links = $this->find('td.itd .it5 a');
        foreach ($links as $linkElem) {
            $gallery = new stdClass();

            $link = pq($linkElem);
            $gallery->name = $link->text();

            preg_match("~https://exhentai.org/g/(\d*)/(\w*)/~", $link->attr('href'), $matches);

            if (isset($matches[1]) && isset($matches[2])) {
                $gallery->exhenid = $matches[1];
                $gallery->hash = $matches[2];

                $ret[] = $gallery;
            }
        }

        return $ret;
    }
}
