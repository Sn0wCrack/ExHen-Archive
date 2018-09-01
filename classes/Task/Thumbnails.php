<?php

class Task_Thumbnails extends Task_Abstract {

	const LOG_TAG = 'Task_Thumbnails';

	public function run($options = array()) {
		$galleries = R::find('gallery', 'numfiles != (SELECT count(*) FROM exhen.gallery_thumb where gallery_thumb.type = 2 and gallery_id = gallery.id)');

		$galleryCount = count($galleries);
		Log::debug(self::LOG_TAG, 'Found %d galleries for thumbnails', $galleryCount);

		$successCount = 0;
		$count = 0;

		/** @var Model_Gallery $gallery */
        foreach($galleries as $gallery) {
			Log::debug(self::LOG_TAG, '[%d/%d][%d img] %s', ($count+1), $galleryCount, $gallery->numfiles, $gallery->name);
			$success = true;

        	for($i = 0; $i < $gallery->numfiles; $i++) {
                try {
                	$gallery->getThumbnail($i, 2, true);
				}
                catch(Exception $e) {
                    Log::error(self::LOG_TAG, 'Error occured. %s', $e->getMessage());
					$success = false;
                }
        	}

			if($success) {
				$successCount++;
			}

			$count++;
		}

		Log::debug(self::LOG_TAG, 'Processed thumbnails for %d galleries', $count);
	}

}


?>
