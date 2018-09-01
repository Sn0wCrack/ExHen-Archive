<?php

class Task_Audit extends Task_Abstract {

    const LOG_TAG = 'Task_Audit';

    /**
     * @var ExClient
     */
    protected $client;

    public function run($options = array()) {
        $this->client = new ExClient();

        while(true) {

            $interval = isset($options[0]) ? $options[0] : "1 year";

            $galleries = R::find('gallery',
                'archived = 1 and deleted = 0 and source = 0 and'.
                '((added <= date_sub(date(now()), interval 3 day) and'. // if added more than 3 days ago...
                '(posted >= date_sub(date(now()), interval ' . $interval . ') or (lastaudit is null)) and'. // and if the gallery is more than input old but NOT if it's unaudited
                '(lastaudit is null or lastaudit <= date_sub(date(now()), interval 7 day))) or'. // ...and not yet audited, or audited more than 7 days ago
				'((added >= date_sub(date(now()), interval 7 day) and added <= date_sub(date(now()), interval 1 day)) and'. // OR, added less than 7 days ago (but more than 24 hours ago)...
                '(lastaudit is null or lastaudit <= date_sub(date(now()), interval 1 day))))'. // ...and not yet audited, or audited more than 1 day ago
                'order by posted desc limit 100');

            if(count($galleries) === 0) {
                break;
            }

            Log::debug(self::LOG_TAG, 'Loaded %d galleries for audit', count($galleries));
            foreach($galleries as $gallery) {
                $this->audit($gallery);
            }
        }

        if(isset(Config::get()->indexer->full)) {
            $command = Config::get()->indexer->full;
            system($command);
        }
    }

    protected function audit($gallery) {
        Log::debug(self::LOG_TAG, 'Auditing gallery: #%d - %s', $gallery->exhenid, $gallery->name);

        try {
            $galleryHtml = $this->client->gallery($gallery->exhenid, $gallery->hash);
            Log::debug(self::LOG_TAG, 'Html loaded');

            $galleryPage = new ExPage_Gallery($galleryHtml);
            Log::debug(self::LOG_TAG, 'Checking gallery validity');
            if (!$galleryPage->isValid()) {
                $gallery->lastaudit = date('Y-m-d H:i:s'); //gallery was probably deleted, so mark it as audited for now
                R::store($gallery);

                Log::error(self::LOG_TAG, 'Gallery was either removed or was invalid.');
                return;
            }

            Log::debug(self::LOG_TAG, 'Getting latest version of gallery');
            $childGallery = $galleryPage->getNewestVersion();
            if ($childGallery) {
                Log::debug(self::LOG_TAG, 'Getting last version');
                $childHtml = $this->client->gallery($childGallery->exhenid, $childGallery->hash);
                $childPage = new ExPage_Gallery($childHtml);

                if ($childPage->isValid()) {
                    Log::debug(self::LOG_TAG, 'New gallery found for gallery (%d): #%d - %s', $childGallery->exhenid, $gallery->exhenid, $gallery->name);

                    Model_Gallery::addGallery($childGallery->exhenid, $childGallery->hash);

                    $gallery->deleted = 1;
                }

            } else {
                Log::debug(self::LOG_TAG, 'Already on last version');
                $newTags = $galleryPage->getTags();
                $oldTags = $gallery->exportTags();

                if (count($newTags) > 0) {
                    $diff = self::tagsDiff($oldTags, $newTags);

                    if (count($diff) > 0) {
                        $humanDiff = array();
                        foreach ($diff as $ns => $tags) {
                            foreach ($tags as $tag) {
                                $humanDiff[] = $ns . ':' . $tag;
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
        } catch (\GuzzleHttp\Exception\ClientException $exception) {
            // Galleries that are removed now return a 404, so we have to leave them as audited completely for now.
            if(!$exception->getResponse()->getStatusCode() == 404) {
                Log::error(self::LOG_TAG, 'Gallery was either removed or was invalid.');
            } else {
                Log::error(self::LOG_TAG, 'Unknown error occured. Code: %d. Message: %s', $exception->getResponse()->getStatusCode(), $exception->getResponse()->getReasonPhrase());
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
