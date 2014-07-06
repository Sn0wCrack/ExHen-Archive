<?php

class Task_Thumbnails extends Task_Abstract {

	const LOG_TAG = 'Task_Thumbnails';

	public function run($options = array()) {
		$galleries = R::find('gallery', 'numfiles != (SELECT count(*) FROM exhen.gallery_thumb where gallery_thumb.type = 2 and gallery_id = gallery.id)');

		$count = 0;

		foreach($galleries as $gallery) {
			$success = true;

        	for($i = 0; $i < $gallery->numfiles; $i++) {
                try {
                	$gallery->getThumbnail($i, 2, true);
				}
                catch(Exception $e) {
					$success = false;
                }
        	}

			if($success) {
				$count++;
			}
		}

		Log::debug(self::LOG_TAG, 'Processed thumbnails for %d galleries', $count);
	}

}


?>
