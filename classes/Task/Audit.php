<?php

class Task_Audit extends Task_Abstract {

    const LOG_TAG = 'Task_Audit';

    protected $client;

    public function run($options = array()) {
        $this->client = new ExClient();

        while(true) {
            $galleries = R::find('gallery',
                'archived = 1 and deleted = 0 and
                added <= date_sub(date(now()), interval 3 day) and
                (lastaudit is null or lastaudit <= date_sub(date(now()), interval 7 day))
                order by posted desc limit 100');

            if(count($galleries) === 0) {
                break;
            }

            foreach($galleries as $gallery) {
                $this->audit($gallery);
            }
        }
    }

    protected function audit($gallery) {
        Log::debug(self::LOG_TAG, 'Auditing gallery: #%d - %s', $gallery->exhenid, $gallery->name);

        $galleryHtml = $this->client->gallery($gallery->exhenid, $gallery->hash);
        if(!$galleryHtml) {
            Log::error(self::LOG_TAG, 'Failed to retrieve page from server');
            return;
        }

        $galleryPage = new ExPage_Gallery($galleryHtml);

        $childGallery = $galleryPage->getNewestVersion();
        if($childGallery) {
            Log::debug(self::LOG_TAG, 'New gallery found for gallery: #%d - %s', $gallery->exhenid, $gallery->name);

            Model_Gallery::addGallery($childGallery->exhenid, $childGallery->hash);

            $gallery->deleted = true;
            
        }
        else {
            $newTags = $galleryPage->getTags();
            $oldTags = $gallery->exportTags();

            $diff = self::md_array_diff($oldTags, $newTags);

            if(count($diff) > 0) {
                $humanDiff = array();
                foreach($diff as $ns => $tags) {
                    foreach($tags as $tag) {
                        $humanDiff[] = $ns.':'.$tag;
                    }
                }

                $humanDiff = implode(', ', $humanDiff);

                Log::debug(self::LOG_TAG, 'Different tags found for gallery: #%d - %s (%s)', $gallery->exhenid, $gallery->name, $humanDiff);
                $gallery->ownGalleryTag = array();
                $gallery->addTags($newTags);

                $cache = Cache::getInstance();
                $cache->deleteObject('gallery', $gallery->id);
            }
        }

        $gallery->lastaudit = date('Y-m-d H:i:s');
        R::store($gallery);
    }

    static function md_array_diff($a1, $a2){
        $diff = array();

        foreach($a1 as $k => $v){
            $dv = null;
            if(is_int($k)){
                // Compare values
                if(array_search($v,$a2)===false) {
                    $dv = $v;
                }
                else if(is_array($v)) {
                    $dv = self::md_array_diff($v, $a2[$k]);
                }
                
                if($dv) {
                    $diff[] = $dv;
                }
            }
            else {
                // Compare noninteger keys
                if(!array_key_exists($k, $a2)) {
                    $dv = $v;
                }
                else if(is_array($v)) {
                    $dv = self::md_array_diff($v, $a2[$k]);
                }

                if($dv) {
                    $diff[$k] = $dv;
                }
            }    
        }

        return $diff;
    }
    
}


?>
