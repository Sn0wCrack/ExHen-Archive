<?php

use PHPImageWorkshop\ImageWorkshop;

class Model_Gallery extends Model_Abstract {

	const SOURCE_EXHENTAI = 0;
	const SOURCE_MANUAL = 1;

	const THUMB_LARGE = 1;
	const THUMB_SMALL = 2;

	const GP_PER_MB = 41.9435018158;

	public static function search($page, $pagesize, $search, $order, $randomSeed = null, $unarchived = false) {
		$query = new QueryHelper();

		if($order === 'random') {
			$query->sql('select *, crc32(to_string(id + :seed)) as rnd from galleries');
			$query->addParams(array('seed' => $randomSeed));
		}
		elseif($order === 'weight') {
			$query->sql('select *, weight() as ranked_weight from galleries');
		}
		else {
			$query->sql('select * from galleries');
		}

		$query->sql('where deleted = 0');

        if(!$unarchived) {
            $query->sql('and archived = 1');
        }

		if($search) {
			$search = SphinxQL::halfEscapeMatch($search);
			
			$query->sql('and match(:match)')
				->addParams(array('match' =>  $search));
		}

		if($order === 'added') {
			$query->sql('order by added desc');
		}
		elseif($order === 'random') {
			$query->sql('order by rnd asc');
		}
		elseif($order === 'weight') {
			$query->sql('order by ranked_weight desc, posted desc');
		}
		else {
			$query->sql('order by posted desc');
		}

		if($page >= 0 && $pagesize) {
			$query->sql('limit :offset, :limit')
				->addParams(array(
					'offset' => (int)($page * $pagesize),
					'limit' => (int)$pagesize
				));
		}

		if($order === 'weight') {
			$query->sql('option ranker = wordcount,');
		}
		else {
			$query->sql('option ranker = none,');
		}

		$query->sql('max_matches = 500000');

		$result = SphinxQL::query($query->getSql(), $query->getParams());
		$meta = SphinxQL::getMeta();

		return array('result' => $result, 'meta' => $meta);
	}

	// this really needs redoing..
	public function getArchiveFilepath() {
		if($this->source == self::SOURCE_EXHENTAI) {
			return sprintf('%s/galleries/%d.zip', Config::get()->archiveDir, $this->exhenid);
		}
		else {
			return sprintf('%s/galleries/%d-%d.zip', Config::get()->archiveDir, $this->source, $this->id);
		}
	}

	public function getImageFilepath($index) {
		$zipPath = $this->getArchiveFilepath();
		if(file_exists($zipPath)) {
			$files = array();

			$zip = new PharData($zipPath);
			if($zip) {
				foreach($zip as $file) {
					$files[] = basename($file);
				}

				natcasesort($files);
				$files = array_values($files); //strip keys

				if(array_key_exists($index, $files)) {
					return sprintf('phar://%s/%s', $zipPath, $files[$index]);
				}
			}
		}

		return false;
	}

	public function getImageBean($index) {
		return R::findOne('galleryimage', 'gallery_id = ? and galleryimage.index = ?', array($this->id, $index));
	}

	public function getThumbnail($index, $type, $create = true) {
		if($this->archived) {
			$link = $this->unbox()->withCondition('gallery_thumb.index = ? and gallery_thumb.type = ?', array($index, $type))->via('gallery_thumb')->ownGalleryThumb;

			if(count($link) > 0) {
				$link = array_pop($link);

				return $link->image;
			}
			else if($create) {

				$inFile = $this->getImageFilepath($index);

				if(file_exists($inFile)) {
					$resizedFilename = sprintf('resized_gallery_%d_%d.jpg', $this->id, $index);

					$layer = ImageWorkshop::initFromPath($inFile);

					if($type == self::THUMB_LARGE) {
						$layer->resizeInPixel(350, null, true);
					}
					elseif($type == self::THUMB_SMALL) {
						$layer->resizeInPixel(140, null, true);
					}
					else {
						return false;
					}

					$tempDir = Config::get()->tempDir;
					$layer->save($tempDir, $resizedFilename, true, null, 95);

					$outFile = $tempDir.DS.$resizedFilename;
					$image = Model_Image::importFromFile($outFile);

					unlink($outFile);

					$link = $image->unbox()->link('gallery_thumb', array('index' => $index, 'type' => $type))->gallery = $this->unbox();
					R::store($image);

					return $image;
				}
			}
		}

		return false;
	}

	public function dispense() {
		$this->added = date('Y-m-d H:i:s');
	}

	public function update() {
		$this->updated = date('Y-m-d H:i:s');
	}

	public function getRoundedId() {
		return round(($this->exhenid - 500), -4);
	}

	public static function getStats() {
		$sql =
			'select
				count(id) as count_total,
				sum(filesize) as filesize,
				round(sum(filesize) / pow(1024, 3), 2) as filesize_gb,
				(select count(id) from gallery where archived = 1) as count_archived,
				(select count(id) from gallery where deleted = 1) as count_deleted,
				round((sum(filesize) / (select count(id) from gallery where archived = 1)) / pow(1024, 2), 2) as filesize_average_mb,
				sum(round(round(filesize / pow(1024, 2), 2) * 41.9435018158)) as gp_total,
				round(sum(round(filesize / pow(1024, 2), 2) * 41.9435018158) / (select count(id) from gallery where archived = 1)) as gp_average
			from gallery
			limit 1';

		$stats = R::getRow($sql);

		return $stats;
	}

	public function hasTag($ns, $tag) {
		$tagBean = R::findOne('tag', 'name = ?', array($tag));
		$nsBean = R::findOne('tagnamespace', 'name = ?', array($ns));

		if(!$tagBean || !$nsBean) {
			return false;
		}

		$result = $this->unbox()->withCondition('tag_id = ? and namespace_id = ?', array($tagBean->id, $nsBean->id))->ownGalleryTag;
		return (count($result) > 0);
	}

	public function addTags($tagList) {
		foreach($tagList as $tagNamespace => $tags) {
			$ns = R::findOne('tagnamespace', 'name = ?', array($tagNamespace));
			if(!$ns) {
				$ns = R::dispense('tagnamespace');
				$ns->name = $tagNamespace;
				R::store($ns);
			}

			foreach($tags as $tagName) {
				$tag = R::findOne('tag', 'name = ?', array($tagName));
				if(!$tag) {
					$tag = R::dispense('tag');
					$tag->name = $tagName;
					R::store($tag);
				}

				$this->unbox()->link('gallery_tag', array('namespace' => $ns))->tag = $tag;
			}
		}
	}

	public function addProperties($props) {
		foreach($props as $name => $value) {
			$galleryAttr = R::dispense('galleryproperty');
			$galleryAttr->name = $name;
			$galleryAttr->value = $value;
			$galleryAttr->gallery = $this->unbox();
			R::store($galleryAttr);

			if($name === 'Posted') {
				$this->unbox()->posted = $value;
			}
		}
	}

    public static function addGallery($gid, $hash) {
        $gallery = R::findOne('gallery', 'exhenid = ?', array($gid));
        if(!$gallery) {
            $gallery = R::dispense('gallery');
            $gallery->exhenid = $gid;
            $gallery->hash = $hash;
            R::store($gallery);
        }
        elseif(!$gallery->download) {
            $gallery->download = true;
            $gallery->added = date('Y-m-d H:i:s');
            R::store($gallery);
        }

        return $gallery;
    }

    public function exportTags() {
        $tagLinks = $this->ownGalleryTag;

        R::preload($tagLinks, array('namespace' => 'tagnamespace'));

        $export = array();
        foreach($tagLinks as $link) {
            if(!array_key_exists($link->namespace->name, $export)) {
                $export[$link->namespace->name] = array();
            }

            $export[$link->namespace->name][] = $link->tag->name;
        }

        return $export;
    }
	
	public function exportSource() {
		return $this->source;
	}
}

?>
