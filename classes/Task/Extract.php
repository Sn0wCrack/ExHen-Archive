<?php

class Task_Extract extends Task_Abstract {

	const LOG_TAG = 'Task_Extract';

	public function run($options = array()) {
		$page = 0;
		while(true) {
			$galleries = R::find('gallery', 'archived = 1 and deleted = 0 limit ?, 100', array($page * 100));
			if(count($galleries) == 0) {
				break;
			}

			Log::debug(self::LOG_TAG, 'Extracting galleries %d to %d', ($page * 100), (($page + 1) * 100));

			$index = 0;
			foreach($galleries as $gallery) {
				printf("\r\r\r%d", $index);

				$zipPath = $gallery->getArchiveFilepath();
				if(!file_exists($zipPath)) {
					Log::error(self::LOG_TAG, 'Zip file doesn\'t exist: %s', $zipPath);
				}

				$convertedPath = preg_replace('/.zip$/', '.tar', $zipPath);

				if(file_exists($convertedPath)) {
					unlink($convertedPath);
				}

				$zip = new PharData($zipPath);
				$tar = $zip->convertToData(Phar::TAR);
				unset($tar);

				$tarFolder = sprintf('%s/archives/%s', Config::get()->archiveDir, $gallery->getRoundedId());
				if(!file_exists($tarFolder)) {
					mkdir($tarFolder);
				}

				$tarPath = sprintf('%s/%d.tar', $tarFolder, $gallery->exhenid);
				rename($convertedPath, $tarPath);

				$index++;

				exit;
			}

			$page++;
		}
	}
}

?>
