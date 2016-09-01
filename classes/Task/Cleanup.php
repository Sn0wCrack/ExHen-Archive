<?php

class Task_Cleanup extends Task_Abstract {
	
	const LOG_TAG = 'Task_Cleanup';
	
	public function run($options = array()) {
		$config = Config::get();
        $cache = Cache::getInstance();
        
		Log::debug(self::LOG_TAG, 'Checking for NULL galleryproperty entries');
		
		$entries = R::getAll('SELECT * FROM galleryproperty WHERE gallery_id IS NULL');
		if (count($entries) > 0) {
			$count = 0;
			Log::debug(self::LOG_TAG, 'NULL entries found, deleting them now.');
			foreach($entries as $entry) {
				$book = R::load('galleryproperty', $entry["id"]);
				R::trash($book);
				$count++;
			}
			Log::debug(self::LOG_TAG, 'All NULL entries deleted. Total deleted was %d', $count);
		}
		else {
			Log::debug(self::LOG_TAG, 'No NULL galleryproperty entries found.');
		}
		
        Log::debug(self::LOG_TAG, 'Checking for NULL gallery_tag entries');
        $entries = R::getAll('SELECT * FROM gallery_tag WHERE gallery_id IS NULL');
        if (count($entries) > 0) {
            $count = 0;
            Log::debug(self::LOG_TAG, 'NULL entries found, deleting them now.');
            foreach($entries as $entry) {
                $book = R::load('gallery_tag', $entry["id"]);
                R::trash($book);
                $count++;
            }
            Log::debug(self::LOG_TAG, 'All NULL entries deleted. Total deleted was %d', $count);
        } else {
            Log::debug(self::LOG_TAG, 'No NULL gallery_tag entries found.');
        }
        
		Log::debug(self::LOG_TAG, 'Checking for any galleries marked as deleted.');
		
		$entries = R::getAll('SELECT * FROM gallery WHERE deleted = 1');
		if (count($entries) > 0) {
			$count = 0;
			Log::debug(Self::LOG_TAG, 'Deleting galleries now. This may take a while.');
			foreach ($entries as $entry) {
				$gallery = R::load('gallery', $entry["id"]);
                    
                Log::debug(Self::LOG_TAG, 'Deleting Gallery ' . $entry["name"] . ' (' . $entry["id"] . ')');
                $galleryproperty = R::getAll('SELECT * FROM galleryproperty WHERE gallery_id = ?', array($entry["id"]));
                $gallery_tag = R::getAll('SELECT * FROM gallery_tag WHERE gallery_id = ?', array($entry["id"]));
                $gallery_thumb = R::getAll('SELECT * FROM gallery_thumb WHERE gallery_id = ?', array($entry["id"]));
                
                // Get all image entries associated with the gallery_thumb then delete those, then delete the gallery_thumb entry
                foreach ($gallery_thumb as $thumb) {
                    $images = R::getAll('SELECT * FROM image WHERE id = ?', array($thumb["image_id"]));
                    // Delete all associated images and the thumbnails
                    foreach ($images as $image) {
                        $book = R::load('image', $image["id"]);
                        $imageName = str_pad($book["id"], 6, '0', STR_PAD_LEFT) . '.jpeg';
                        unlink($config->imagesDir . '/' . $imageName);
                        R::trash($book);
                    }
                    $book = R::load('gallery_thumb', $thumb["id"]);
                    R::trash($book);
                }
                
                // Delete all associated tags (just the references, not the actual tag entries)
                foreach ($gallery_tag as $tag) {
                    $book = R::load("gallery_tag", $tag["id"]);
                    R::trash($book);
                }
                
                // Delete all associated properties.
                foreach ($galleryproperty as $prop) {
                    $book = R::load('galleryproperty', $prop["id"]);
                    R::trash($book);
                }
                
                // Delete archive
                $archiveName = $gallery["exhenid"] . '.zip';
                unlink($config->archiveDir . '/galleries/' . $archiveName);
                
                if ($gallery["feed_id"] == null) {
                    R::trash($gallery);
                } else {
                    $gallery["deleted"] = 2;
                    R::store($gallery);
                }
                
                $cache->deleteObject('gallery', $gallery->id);
                $count++;
			}
			Log::debug(self::LOG_TAG, 'All galleries marked as deleted were deleted. Total deleted was %d', $count);
            if(isset($config->indexer->full)) {
                $command = Config::get()->indexer->full;
                system($command);
            }
		}
		else {
			Log::debug(self::LOG_TAG, 'No galleries found that were marked as deleted.');
		}
	}
	
}

?>