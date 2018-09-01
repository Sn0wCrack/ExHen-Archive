<?php

class ExPage_Archiver extends ExPage_Abstract
{
    public function getContinueUrl()
    {
        $elem = $this->findElement('#continue a');
        if (count($elem) >= 1) {
            return $elem->attr('href');
        } else {
            return false;
        }
    }
}
