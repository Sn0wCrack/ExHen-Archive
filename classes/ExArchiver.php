<?php

class ExArchiver {

	const LOG_TAG = 'ExArchiver';

	protected $client;
	protected $archiveDir;
	protected $config;
    protected $cache;

	public function __construct() {
		$this->client = new ExClient();

		$this->config = Config::get();
        $this->cache = Cache::getInstance();

		$this->archiveDir = $this->config->archiveDir.'/galleries';
		if(!is_dir($this->archiveDir)) {
			mkdir($this->archiveDir, 0777, true);
		}

		$pagesDir = $this->config->archiveDir.'/pages';
		if(!is_dir($pagesDir)) {
			mkdir($pagesDir, 0777, true);
		}
	}

	public function start() {
		$archivedCount = 0;

		//archive unarchived galleries
		$unarchived = R::find('gallery', '((archived = 0 and download = 1) or hasmeta = 0) and deleted = 0 and source = 0');
		foreach($unarchived as $gallery) {
			
			$this->archiveGallery($gallery);
			
			if($gallery->archived) {
				$archivedCount++;
			}
		}

		//run through feeds and add new galleries
		$feeds = R::find('feed', 'disabled = 0');
		foreach($feeds as $feed) {
			$page = 0;

			while(true) {
				Log::debug(self::LOG_TAG, 'Crawling feed "%s", page %d', $feed->term, $page);

				$params = array();

				if($feed->expunged) {
					$params['f_sh'] = 1;
				}
				
				$indexHtml = $this->client->index($feed->term, $page, $params);
				$indexPage = new ExPage_Index($indexHtml);
				
                $isLastPage = $indexPage->isLastPage();
				$hasGallery = false;

				$galleries = $indexPage->getGalleries();
				if(count($galleries) == 0) {
					break;
				}
				else {
					foreach($galleries as $exGallery) {
						$gallery = R::findOne('gallery', 'exhenid = ?', array($exGallery->exhenid));
						if(!$gallery) {
							$gallery = R::dispense('gallery');
							$gallery->exhenid = $exGallery->exhenid;
							$gallery->hash = $exGallery->hash;
							$gallery->name = $exGallery->name;
							$gallery->archived = false;
							$gallery->feed = $feed;
                            $gallery->download = $feed->download;
							R::store($gallery);
						}
						else {
							if($gallery->feed == $feed && $feed->archived) {
								$hasGallery = true;
							}

							if($feed->download && !$gallery->deleted) {
								$gallery->download = true;
								R::store($gallery);
							}
						}

						if((!$gallery->archived && $gallery->download) || !$gallery->hasmeta) {
							$this->archiveGallery($gallery);

                            if($gallery->archived) {
                                $archivedCount++;
                            }
						}
					}
				}

                if($isLastPage) {
                    $feed->archived = true;
                    R::store($feed);
                }

				if($isLastPage || $hasGallery) {
					break;
				}

				$page++;
			}
		}

		Log::debug(self::LOG_TAG, 'Archive complete. Archived %d galleries.', $archivedCount);

		if($archivedCount > 0) {
			if(isset(Config::get()->indexer->full)) {
				$command = Config::get()->indexer->full;
        		system($command);
        	}
		}
	}

	protected function archiveGallery($gallery) {
		Log::debug(self::LOG_TAG, 'Archiving gallery: #%d', $gallery->exhenid);

        $this->cache->deleteObject('gallery', $gallery->id);

		$galleryHtml = $this->client->gallery($gallery->exhenid, $gallery->hash);
        if(!$galleryHtml) {
            Log::error(self::LOG_TAG, 'Failed to retrieve page from server');
            return;
        }
        
		$galleryPage = new ExPage_Gallery($galleryHtml);

		// save html
		$pagesFile = sprintf('%s/pages/%d.html', $this->config->archiveDir, $gallery->exhenid);
		file_put_contents($pagesFile, $galleryHtml);

		//name, jap name, type
		$gallery->name = $galleryPage->getName();
		$gallery->origtitle = $galleryPage->getOriginalName();
		$gallery->type = $galleryPage->getType();

		//tags
		$gallery->ownGalleryTag = array(); //remove tags
		$tags = $galleryPage->getTags();
		$gallery->addTags($tags);

		//properties
		$gallery->ownGalleryproperty = array(); //remove properties
		$props = $galleryPage->getProperties();
		$gallery->addProperties($props);

		$gallery->hasmeta = true;

		if($gallery->download) {
			//delete if gallery zip exists (it shouldn't)
			$targetFile = $gallery->getArchiveFilepath();
			if(file_exists($targetFile)) {
				unlink($targetFile);
			}

			Log::debug(self::LOG_TAG, 'Downloading gallery archive');

			//download archive
			$archiverUrl = $galleryPage->getArchiverUrl();
			if(!$archiverUrl) {
				Log::error(self::LOG_TAG, 'Failed to find archiver link for gallery: %s (#%d)', $gallery->name, $gallery->exhenid);
				return;
			}
            
            $archiverUrl = str_replace("--", "-", $archiverUrl);
            
            /*
            $invalidateForm = $this->client->invalidateForm($archiverUrl);
            
            if (strcmp($invalidateForm, "invalidate_form") === 0) {
                Log::error(self::LOG_TAG, 'Failed to invalidate session.');
				exit;
            }
            */
            
			$buttonPress = $this->client->buttonPress($archiverUrl);

			if(strpos($buttonPress, "continue") === false) {
				Log::error(self::LOG_TAG, 'Download check not submitted.');
				exit;
			}
			
			$archiverHtml = $buttonPress;
			$archiverPage = new ExPage_Archiver($archiverHtml);

	        if(strpos($archiverHtml, 'Insufficient Credits.') !== false) {
	            Log::error(self::LOG_TAG, 'Insufficient Credits');
	            exit;
	        }
            
			$continueUrl = $archiverPage->getContinueUrl();
			if($continueUrl) {
				$archiveDownloadUrl = $continueUrl.'?start=1';

				$ret = @copy($archiveDownloadUrl, $targetFile);
				if($ret) {
					$archive = new ZipArchive();
					$ret = $archive->open($targetFile);
					if($ret === true && $archive->status == ZipArchive::ER_OK) {
						$gallery->numfiles = $archive->numFiles;
						$archive->close();

						$gallery->filesize = filesize($targetFile);
						$gallery->archived = true;
					}
					else {
						Log::error(self::LOG_TAG, 'Downloaded file is not an archive for gallery: %s (#%d)', $gallery->name, $gallery->exhenid);
					}
				}
				else {
					Log::error(self::LOG_TAG, 'Failed to download archive for gallery: %s (#%d)', $gallery->name, $gallery->exhenid);
				}
			}
			else {
				Log::error(self::LOG_TAG, 'Failed to find archive link for gallery: %s (#%d) - low GP?', $gallery->name, $gallery->exhenid);
			}
		}

		R::store($gallery);
	}
}

?>
