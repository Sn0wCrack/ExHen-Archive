<?php

class Task_EditGallery extends Task_Abstract {

	const LOG_TAG = 'Task_EditGallery';

	public function run($options = array()) {

		if(count($options) < 2) {
			printf("Invalid options.\n");
			printf("Example: TaskRunner EditGallery id action params...\n");
			printf("Valid actions:\n");
			printf("\taddtag namespace:tag ...\n");
			printf("\n");
			return;
		}

		$galleryId = $options[0];
		$gallery = R::load('gallery', $galleryId);
		if(!$gallery->id) {
			Log::error(self::LOG_TAG, 'Failed to load gallery with id: %s', $galleryId);
			return;
		}

		$action = $options[1];
		$params = array_slice($options, 2);

		if($action === 'addtag') {
			if(count($params) < 1) {
				Log::error(self::LOG_TAG, 'Missing tag to add');
				return;
			}

			foreach($params as $tagParam) {
				$bits = explode(':', $tagParam);
				if(count($bits) !== 2) {
					Log::error(self::LOG_TAG, 'Missing tag namespace: %s', $tagParam);
					continue;
				}

				list($ns, $tag) = $bits;

				if($gallery->hasTag($ns, $tag)) {
					Log::error(self::LOG_TAG, 'Gallery already has tag: %s:%s', $ns, $tag);
					continue;
				}
				else {
					$gallery->addTags(array($ns => array($tag)));
				}
			}
		}
		else {
			Log::error(self::LOG_TAG, 'Invalid action: %s', $action);
			return;
		}

		R::store($gallery);

		$cache = Cache::getInstance();
		$cache->deleteObject('gallery', $gallery->id);

		if(isset(Config::get()->indexer->full)) {
			$command = Config::get()->indexer->full;
    		system($command);
    	}
	}
	
}


?>
