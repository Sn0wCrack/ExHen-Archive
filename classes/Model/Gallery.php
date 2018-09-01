<?php

use Intervention\Image\ImageManagerStatic as Image;

class Model_Gallery extends Model_Abstract
{
    const LOG_TAG = 'Model_Gallery';

    const SOURCE_EXHENTAI = 0;
    const SOURCE_MANUAL = 1;
    const SOURCE_NHENTAI = 2;

    const THUMB_LARGE = 1;
    const THUMB_SMALL = 2;

    const GP_PER_MB = 41.9435018158;

    /**
     * @var ZipArchive
     */
    private $zipResource;

    public static function search($page, $pagesize, $search, $order, $randomSeed = null, $unarchived = false, $read = false, $color = false)
    {
        $query = new QueryHelper();

        if ($order === 'random') {
            $query->sql('select *, crc32(to_string(id + :seed)) as rnd from galleries');
            $query->addParams(array('seed' => $randomSeed));
        } elseif ($order === 'weight') {
            $query->sql('select *, weight() as ranked_weight from galleries');
        } else {
            $query->sql('select * from galleries');
        }

        $query->sql('where deleted = 0');

        if (!$unarchived) {
            $query->sql('and archived = 1');
        }
        
        if ($read !== false) {
            $query->sql('and read = :read')
                ->addParams(array('read' => $read));
        }
        
        if ($color !== false) {
            $query->sql('and color = :color')
                ->addParams(array('color' => $color));
        }

        if ($search) {
            $search = SphinxQL::halfEscapeMatch($search);
            
            $query->sql('and match(:match)')
                ->addParams(array('match' =>  $search));
        }

        if ($order === 'added') {
            $query->sql('order by added desc');
        } elseif ($order === 'random') {
            $query->sql('order by rnd asc');
        } elseif ($order === 'weight') {
            $query->sql('order by ranked_weight desc, posted desc');
        } else {
            $query->sql('order by posted desc');
        }

        if ($page >= 0 && $pagesize) {
            $query->sql('limit :offset, :limit')
                ->addParams(array(
                    'offset' => (int)($page * $pagesize),
                    'limit' => (int)$pagesize
                ));
        }

        if ($order === 'weight') {
            $query->sql('option ranker = wordcount,');
        } else {
            $query->sql('option ranker = none,');
        }

        $query->sql('max_matches = 500000');

        $result = SphinxQL::query($query->getSql(), $query->getParams());
        $meta = SphinxQL::getMeta();

        return array('result' => $result, 'meta' => $meta);
    }

    // this really needs redoing..
    public function getArchiveFilepath()
    {
        if ($this->source == self::SOURCE_EXHENTAI) {
            return sprintf('%s/galleries/%d.zip', Config::get()->archiveDir, $this->exhenid);
        } else {
            return sprintf('%s/galleries/%d-%d.zip', Config::get()->archiveDir, $this->source, $this->id);
        }
    }

    public function getArchivedImage($index)
    {
        if(!$this->zipResource) {
            $this->zipResource = new ZipArchive();
            $openstate = $this->zipResource->open($this->getArchiveFilepath());
            if($openstate !== TRUE) {
                throw new Exceptions_ExHentaiException('Zip could not be opened');
            }
        }

        $content = $this->zipResource->getFromIndex($index);

        if(($index+1) >= $this->zipResource->numFiles) {
            $this->zipResource->close();
        }

        return $content;
    }

    public function getImageBean($index)
    {
        return R::findOne('galleryimage', 'gallery_id = ? and galleryimage.index = ?', array($this->id, $index));
    }

    public function getThumbnail($index, $type, $create = true)
    {
        if ($this->archived) {
            $link = $this->unbox()->withCondition('gallery_thumb.index = ? and gallery_thumb.type = ?', array($index, $type))->via('gallery_thumb')->ownGalleryThumb;

            if (count($link) > 0) {
                $link = array_pop($link);

                return $link->image;
            } elseif ($create) {

                $fileContent = $this->getArchivedImage($index);

                $resizedFilename = sprintf('resized_gallery_%d_%d.jpg', $this->id, $index);

                try {
                    if ($type == self::THUMB_LARGE) {
                        $width = 350;
                    } elseif ($type == self::THUMB_SMALL) {
                        $width = 140;
                    } else {
                        return false;
                    }
                    $image = Image::make($fileContent)->resize($width, null, function ($constraint) {
                        $constraint->aspectRatio();
                    });
                } catch (Exception $exception) {
                    throw new Exceptions_ExHentaiException(sprintf(
                        'Error decoding image. Message: %s. File: %s (%s)',
                        $exception->getMessage(),
                        $this->getArchiveFilepath(),
                        $this->zipResource->getNameIndex($index)
                        ));
                }

                $tempDir = Config::get()->tempDir;

                $outFile = $tempDir.DS.$resizedFilename;
                $image->save($outFile, 95);
                Log::debug(self::LOG_TAG, 'Saved image to %s', $outFile);
                $image = Model_Image::importFromFile($outFile);
                unlink($outFile);

                $image->unbox()->link('gallery_thumb', array('index' => $index, 'type' => $type))->gallery = $this->unbox();
                R::store($image);

                return $image;

            }
        } else {
            Log::debug(self::LOG_TAG, 'Gallery not archived, no thumbs');
        }

        return false;
    }

    public function dispense()
    {
        $this->added = date('Y-m-d H:i:s');
    }

    public function update()
    {
        $this->updated = date('Y-m-d H:i:s');
    }

    public function getRoundedId()
    {
        return round(($this->exhenid - 500), -4);
    }

    public static function getStats()
    {
        $sql =
            'select
				count(id) as count_total,
				sum(filesize) as filesize,
				round(sum(filesize) / pow(1024, 3), 2) as filesize_gb,
				(select count(id) from gallery where archived = 1) as count_archived,
				(select count(id) from gallery where deleted >= 1) as count_deleted,
				round((sum(filesize) / (select count(id) from gallery where archived = 1)) / pow(1024, 2), 2) as filesize_average_mb,
				sum(round(round(filesize / pow(1024, 2), 2) * 41.9435018158)) as gp_total,
				round(sum(round(filesize / pow(1024, 2), 2) * 41.9435018158) / (select count(id) from gallery where archived = 1)) as gp_average
			from gallery
			limit 1';

        $stats = R::getRow($sql);

        return $stats;
    }

    public function hasTag($ns, $tag)
    {
        $tagBean = R::findOne('tag', 'name = ?', array($tag));
        $nsBean = R::findOne('tagnamespace', 'name = ?', array($ns));

        if (!$tagBean || !$nsBean) {
            return false;
        }

        $result = $this->unbox()->withCondition('tag_id = ? and namespace_id = ?', array($tagBean->id, $nsBean->id))->ownGalleryTag;
        return (count($result) > 0);
    }

    public function addTags($tagList)
    {
        foreach ($tagList as $tagNamespace => $tags) {
            $ns = R::findOne('tagnamespace', 'name = ?', array($tagNamespace));
            if (!$ns) {
                $ns = R::dispense('tagnamespace');
                $ns->name = $tagNamespace;
                R::store($ns);
            }

            foreach ($tags as $tagName) {
                $tag = R::findOne('tag', 'name = ?', array($tagName));
                if (!$tag) {
                    $tag = R::dispense('tag');
                    $tag->name = $tagName;
                    R::store($tag);
                }

                $this->unbox()->link('gallery_tag', array('namespace' => $ns))->tag = $tag;
            }
        }
    }

    public function addProperties($props)
    {
        foreach ($props as $name => $value) {
            $galleryAttr = R::dispense('galleryproperty');
            $galleryAttr->name = $name;
            $galleryAttr->value = $value;
            $galleryAttr->gallery = $this->unbox();
            R::store($galleryAttr);

            if ($name === 'Posted') {
                $this->unbox()->posted = $value;
            }
        }
    }

    public static function addGallery($gid, $hash)
    {
        $gallery = R::findOne('gallery', 'exhenid = ?', array($gid));
        if (!$gallery) {
            $gallery = R::dispense('gallery');
            $gallery->exhenid = $gid;
            $gallery->hash = $hash;
            R::store($gallery);
        } elseif (!$gallery->download) {
            $gallery->download = true;
            $gallery->added = date('Y-m-d H:i:s');
            R::store($gallery);
        }

        return $gallery;
    }

    public function exportTags()
    {
        $tagLinks = $this->ownGalleryTag;

        R::preload($tagLinks, array('namespace' => 'tagnamespace'));

        $export = array();
        foreach ($tagLinks as $link) {
            if (!array_key_exists($link->namespace->name, $export)) {
                $export[$link->namespace->name] = array();
            }

            $export[$link->namespace->name][] = $link->tag->name;
        }

        return $export;
    }
    
    public function exportSource()
    {
        return $this->source;
    }
    
    public function exportFeed()
    {
        return $this->feed_id;
    }
}
