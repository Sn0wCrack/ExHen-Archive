<?php

class Task_ForceAudit extends Task_Abstract {

    const LOG_TAG = 'Task_ForceAudit';

    protected $client;

    public function run($options = array()) {
        $this->client = new ExClient();

        if (isset($options[0])) {
            $id = $options[0];
        } else {
            exit;
        }

        if ($id == "all") {
            $galleries = R::findAll('gallery',
                'archived = 1 and deleted = 0 and source = 0');
        } else {
            $galleries = R::findAll('gallery',
                'archived = 1 and deleted = 0 and source = 0'.
                ' and id = ?', [(int)$id]);
        }

        if (count($galleries) == 0) {
            exit;
        }

        foreach($galleries as $gallery) {
            $this->audit($gallery);
        }

        if(isset(Config::get()->indexer->full)) {
            $command = Config::get()->indexer->full;
            system($command);
        }
    }

    protected function audit($gallery) {
        Log::debug(self::LOG_TAG, 'Auditing gallery: #%d - %s', $gallery->exhenid, $gallery->name);

        $galleryHtml = $this->client->gallery($gallery->exhenid, $gallery->hash);

        // Galleries that are removed now return a 404, so we have to leave them as audited completely for now.
        if(!$galleryHtml) {
            $gallery->lastaudit = date('Y-m-d H:i:s'); //gallery was probably deleted, so mark it as audited for now
            R::store($gallery);

            Log::error(self::LOG_TAG, 'Gallery was either removed or was invalid.');
            return;
        }

        $galleryPage = new ExPage_Gallery($galleryHtml);
        if(!$galleryPage->isValid()) {
            $gallery->lastaudit = date('Y-m-d H:i:s'); //gallery was probably deleted, so mark it as audited for now
            R::store($gallery);

            Log::error(self::LOG_TAG, 'Gallery was either removed or was invalid.');
            return;
        }

        $childGallery = $galleryPage->getNewestVersion();
        if($childGallery) {

            $childHtml = $this->client->gallery($childGallery->exhenid, $childGallery->hash);
            $childPage = new ExPage_Gallery($childHtml);

            if ($childPage->isValid()) {
                Log::debug(self::LOG_TAG, 'New gallery found for gallery (%d): #%d - %s', $childGallery->exhenid, $gallery->exhenid, $gallery->name);

                Model_Gallery::addGallery($childGallery->exhenid, $childGallery->hash);

                $gallery->deleted = 1;
            }
            
        }
        else {
            $newTags = $galleryPage->getTags();
            $oldTags = $gallery->exportTags();

            if(count($newTags) > 0) {
                $diff = self::tagsDiff($oldTags, $newTags);

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
        }

        $gallery->lastaudit = date('Y-m-d H:i:s');

        if ($gallery->deleted == 1) {
            $gallery->lastaudit = null;
        }

        R::store($gallery);
    }

    static function tagsDiff($tags1, $tags2){
        $ret = array();

        foreach ($tags1 as $ns => $tags) {
            if(!array_key_exists($ns, $tags2)) {
                $ret[$ns] = $tags;
                continue;
            }

            foreach($tags as $index => $tag) {
                if(!in_array($tag, $tags2[$ns])) {
                    if(!array_key_exists($ns, $ret) || !is_array($ret[$ns])) {
                        $ret[$ns] = array();
                    }

                    $ret[$ns][] = $tag;
                }
                else {
                    $pos = array_search($tag, $tags2[$ns]);
                    unset($tags2[$ns][$pos]);
                }
            }

            if(count($tags2[$ns]) > 0) {
                if(!array_key_exists($ns, $ret) || !is_array($ret[$ns])) {
                    $ret[$ns] = array();
                }

                $ret[$ns] = array_merge($ret[$ns], $tags2[$ns]);
            }

            unset($tags2[$ns]);
        }

        $ret = array_merge($ret, $tags2);

        return $ret;
    }

}


?>
