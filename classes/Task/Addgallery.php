<?php

class Task_Addgallery extends Task_Abstract {

	const LOG_TAG = 'Task_Addgallery';

	public function run($options = array()) {

		if(count($options) !== 2) {
			printf("Invalid options.\n");
			printf("Example: TaskRunner addgallery input.zip galleryname\n\n");
			return;
		}

		list($inputFile, $galleryName) = $options;

		if(!file_exists($inputFile)) {
			Log::error(self::LOG_TAG, 'Failed to open input file');
			return;
		}

		$gallery = R::dispense('gallery');
		$gallery->name = $galleryName;
		$gallery->archived = 1;
		$gallery->added = date('Y-m-d H:i:s');
		$gallery->posted = date('Y-m-d H:i:s');
		$gallery->source = Model_Gallery::SOURCE_MANUAL;
		
		$archive = new ZipArchive();
		$ret = $archive->open($inputFile);
		if($ret === true && $archive->status == ZipArchive::ER_OK) {
			$gallery->numfiles = $archive->numFiles;
			$archive->close();

			$gallery->filesize = filesize($inputFile);
			$gallery->archived = true;
		}
		else {
			Log::error(self::LOG_TAG, 'Input file is not a valid zip');
		}

		R::store($gallery);

		$dest = $gallery->getArchiveFilepath();
		$ret = copy($inputFile, $dest);

		if(!$ret) {
			Log::error(self::LOG_TAG, 'Failed to copy file to dest: %s', $dest);
			R::trash($gallery);
			return;
		}
	}
	
}


?>
