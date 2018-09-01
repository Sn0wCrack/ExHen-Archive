<?php

class ExPage_Gallery extends ExPage_Abstract
{
    public function isValid()
    {
        $result = $this->findElement('h1#gn, div#taglist, div#gdd');
        return count($result) >= 3;
    }

    public function getName()
    {
        return $this->findElement('h1#gn')->text();
    }

    public function getOriginalName()
    {
        return $this->findElement('h1#gj')->text();
    }

    public function getType()
    {
        return $this->findElement('div#gdc img')->attr('alt');
    }

    public function getThumbnailUrl()
    {
        return $this->findElement('div#gd1 img')->attr('src');
    }

    public function getTags()
    {
        $ret = array();

        $tagRows = $this->findElement('#taglist tr');
        foreach ($tagRows as $i => $tagRowElem) {
            $tagRow = $tagRows->eq($i);

            $tagNamespace = $tagRow->filter('td:first-child')->text();
            $tagNamespace = trim($tagNamespace, ':');

            $tags = array();
            $tagLinks = $tagRow->filter('a');
            foreach ($tagLinks as $x => $tagLinkElem) {
                $tagLink = $tagLinks->eq($x);
                $tags[] = $tagLink->text();
            }

            $ret[$tagNamespace] = $tags;
        }

        return $ret;
    }

    public function getProperties()
    {
        $ret = array();

        $attrs = $this->findElement('td.gdt1');
        foreach ($attrs as $i => $attrElem) {
            $attr = $attrs->eq($i);
            $propName = trim($attr->text(), ':');
            $propValue = $attr->nextAll()->text();

            $ret[$propName] = $propValue;
        }

        return $ret;
    }

    public function getArchiverUrl()
    {
        $elem = $this->findElement('a[onclick*="archiver.php"]');
        if (count($elem) > 0) {
            preg_match("~(https://exhentai.org/archiver.php.[^\']+)'~", $elem->attr('onclick'), $matches);
            if (count($matches) >= 2) {
                return $matches[1];
            }
        }

        return false;
    }

    public function getNewestVersion()
    {
        $elem = $this->findElement('div#gnd a:last-child');
        if ($elem->count() === 1) {
            preg_match("~https://exhentai.org/g/(\d*)/(\w*)/~", $elem->attr('href'), $matches);

            $ret =new stdClass();
            $ret->exhenid = $matches[1];
            $ret->hash = $matches[2];

            return $ret;
        }

        return false;
    }
}
