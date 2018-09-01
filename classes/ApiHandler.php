<?php

use Intervention\Image\ImageManagerStatic as Image;

class ApiHandler
{

    protected $params;
    protected $start;

    protected function galleriesAction()
    {
        $data = array();

        $page = $this->getParam('page', 0);
        $pagesize = $this->getParam('pagesize', 24);
        $search = $this->getParam('search');
        $order = $this->getParam('order', 'posted');
        $seed = $this->getParam('seed', 0);
        $unarchived = $this->getParam('unarchived', false);
        $read = $this->getParam('read', false);
        $color = $this->getParam('color', false);

        $seed = (int)base_convert($seed, 36, 10);

        $search = trim($search);

        $ret = Model_Gallery::search($page, $pagesize, $search, $order, $seed, $unarchived, $read, $color);
        $result = $ret['result'];
        $data['meta'] = $ret['meta'];
        $data['galleries'] = array();

        $data['end'] = false;
        if (($page + 1) * $pagesize >= $ret['meta']->total) {
            $data['end'] = true;
        }

        $config = Config::get();
        $cache = Cache::getInstance();

        foreach ($result as $row) {
            $galleryId = $row['id'];

            $export = null;

            $export = $cache->getObject('gallery', $galleryId);
            if ($export) {
                $export['fromcache'] = true;
            }
            
            if (!$export) {
                $gallery = R::load('gallery', $galleryId);
                $export = $gallery->export();

                $thumb = $gallery->getThumbnail(0, Model_Gallery::THUMB_LARGE, false); //don't generate a thumb now
                if ($thumb) {
                    $export['thumb'] = array(
                        'url' => $thumb->getUrl(),
                        'landscape' => ($thumb->width > $thumb->height)
                    );
                }

                $tagLinks = $gallery->ownGalleryTag;

                R::preload($tagLinks, array('namespace' => 'tagnamespace'));

                $export['tags'] = $gallery->exportTags();

                $export['posted_formatted'] = date('d/m/Y', strtotime($gallery->posted));

                $export['fromcache'] = false;
                
                $export['source'] = $gallery->exportSource();

                $cache->setObject('gallery', $galleryId, $export);
            }

            if (array_key_exists('ranked_weight', $row)) {
                $export['ranked_weight'] = $row['ranked_weight'];
            }

            $data['galleries'][] = $export;
        }

        $this->sendSuccess($data);
    }

    protected function galleryAction()
    {
        $id = $this->getParam('id');

        if ($id) {
            $gallery = R::load('gallery', $id);
            if (!$gallery) {
                $this->sendFail('Invalid gallery');
            }

            $data = $gallery->export();

            $data['tags'] = $gallery->exportTags();
            
            $data['source'] = $gallery->exportSource();

            $this->sendSuccess($data);
        } else {
            $this->sendFail('Invalid params');
        }
    }

    protected function archiveimageAction()
    {
        list($id, $index) = $this->getParams('id', 'index');
        $resize = $this->getParam('resize', false);

        if ($id && $index >= 0) {
            $gallery = R::load('gallery', $id);
            if ($gallery) {
                $imagePath = $gallery->getImageFilepath($index);
                if (file_exists($imagePath)) {
                    header('Pragma: public');
                    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($imagePath)) . ' GMT');
                    header('Expires: ' . gmdate('D, d M Y H:i:s', time() + 31536000) . ' GMT');
                    header('Cache-Control: max-age=31536000');

                    if ($resize && is_numeric($resize) && (int)$resize > 0) {
                        $ext = pathinfo($imagePath, PATHINFO_EXTENSION);
                        $image = Image::make($imagePath);

                        if ($image->getWidth() < $resize) {
                            $resize = $image->getWidth();
                        }

                        $image->resize($resize, null, function($constraint) {
                            $constraint->aspectRatio();
                        });

                        $quality = null;
                        switch ($ext) {
                            case 'jpg':
                            case 'jpeg':
                                $quality = 80;
                                break;
                            case 'png':
                                $quality = 8;
                                break;
                            case 'gif':
                            case 'webp':
                                $quality = null;
                                break;
                            default:
                                throw new Exception('Unsupported extension');
                        }
                        $stream = $image->stream($ext, $quality);
                        $stream->rewind();
                        echo $stream->getContents();
                    } else {
                        $ext = pathinfo($imagePath, PATHINFO_EXTENSION);

                        switch ($ext) {
                            default:
                            case 'jpg':
                            case 'jpeg':
                                header('Content-Type: image/jpeg');
                                break;
                            case 'png':
                                header('Content-Type: image/png');
                                break;
                            case 'gif':
                                header('Content-Type: image/gif');
                                break;
                        }
                        
                        readfile($imagePath);
                    }
                } else {
                    http_response_code(404);
                }
            } else {
                http_response_code(404);
            }
        } else {
            http_response_code(400);
        }

        exit;
    }

    protected function gallerythumbAction()
    {
        list($id, $index, $type) = $this->getParams('id', 'index', 'type');

        if ($id && $index !== null && in_array($type, array(Model_Gallery::THUMB_LARGE, Model_Gallery::THUMB_SMALL))) {
            /** @var Model_Gallery $gallery */
            $gallery = R::load('gallery', $id);
            if ($gallery) {
                $thumb = $gallery->getThumbnail($index, $type, true);
                if ($thumb) {
                    if ($index == 0) {
                        $cache = Cache::getInstance();
                        $cache->deleteObject('gallery', $gallery->id);
                    }

                    header('Location: '.$thumb->getUrl());
                } else {
                    http_response_code(404);
                }
            } else {
                http_response_code(404);
            }
        } else {
            http_response_code(400);
        }

        exit;
    }

    protected function exgallerythumbAction()
    {
        $id = $this->getParam('id');

        if ($id) {
            $gallery = R::load('gallery', $id);
            if ($gallery) {
                $config = Config::get();
                $pageFile = sprintf('%s/pages/%d.html', $config->archiveDir, $gallery->exhenid);
                $cachedImagePath = sprintf('%s/%s.jpeg', $config->imageDir, $gallery->exhenid);

                if (file_exists($pageFile) || file_exists($cachedImagePath)) {
                    header('Content-Type: image/jpeg');
                    header('Last-Modified: '.gmdate('D, d M Y H:i:s', strtotime($gallery->posted)).' GMT');
                    header('Expires: '.gmdate('D, d M Y H:i:s', strtotime('+1 year')).' GMT');
                    header('Cache-Control: public');

                    if(!file_exists($cachedImagePath)) {
                        $html = file_get_contents($pageFile);
                        $page = new ExPage_Gallery($html);

                        $url = $page->getThumbnailUrl();

                        // Get gallery thumbnail from exhentai
                        $exClient = new ExClient();
                        $imageString = $exClient->get($url);

                        // Save thumbnail to file
                        if($handle = fopen($cachedImagePath, 'x')) {
                            fwrite($handle, $imageString);
                            fclose($handle);
                        }
                        $im = imagecreatefromstring($imageString);
                        if ($im) {
                            imagejpeg($im);
                            imagedestroy($im);
                        }
                    } else {
                        readfile($cachedImagePath);
                    }
                } else {
                    http_response_code(404);
                }
            } else {
                http_response_code(404);
            }
        } else {
            http_response_code(400);
        }

        exit;
    }

    protected function addgalleryAction()
    {
        list($gid, $hash) = $this->getParams('gid', 'token');

        if ($gid && $hash) {
            $gallery = Model_Gallery::addGallery($gid, $hash);
            $this->sendSuccess($gallery->export());
        } else {
            $this->sendFail('Invalid params');
        }
    }

    public function hasgalleryAction()
    {
        $gid = $this->getParam('gid');
        if ($gid) {
            $gallery = R::findOne('gallery', 'download = 1 and exhenid = ?', array($gid));
            if ($gallery) {
                $this->sendSuccess(array('exists' => true, 'id' => $gallery->id, 'archived' => !!$gallery->archived, 'deleted' => $gallery->deleted));
            } else {
                $this->sendSuccess(array('exists' => false));
            }
        } else {
            $this->sendFail('Invalid params');
        }
    }

    public function hasgalleriesAction()
    {
        $gids = $this->getParam('gids');
        if ($gids && is_array($gids)) {
            $result = R::getAll('select exhenid, archived, deleted from gallery where download = 1 and exhenid in ('.R::genSlots($gids).')', $gids);

            $this->sendSuccess($result);
        } else {
            $this->sendFail('Invalid params');
        }
    }

    public function deletegalleryAction()
    {
        list($id, $key) = $this->getParams('id', 'key');

        if ($key != Config::get()->accessKey) {
            $this->sendFail('Invalid access key');
        } else {
            $gallery = R::load('gallery', $id);
            if (!$gallery || $gallery->deleted >= 1) {
                $this->sendFail('Gallery not found or already deleted');
            } else {
                $gallery->deleted = 1;
                R::store($gallery);

                $this->sendSuccess(true);
            }
        }
    }

    public function downloadAction()
    {
        $id = $this->getParam('id');
        if ($id) {
            $gallery = R::load('gallery', $id);
            if ($gallery) {
                $archive = $gallery->getArchiveFilepath();
                if (file_exists($archive)) {
                    header('Content-Type: application/zip');
                    header('Content-Disposition: attachment; filename="'.$gallery->name.'.zip"');
                    header('Content-Length: '.filesize($archive));
                    
                    readfile($archive);
                    exit;
                } else {
                    http_response_code(404);
                }
            } else {
                http_response_code(404);
            }
        } else {
            http_response_code(400);
        }
    }

    public function suggestedAction()
    {
        $term = $this->getParam('term');
        $result = Suggested::search($term);
        $this->sendSuccess($result);
    }

    public function flushAction()
    {
        $config = Config::get();
        $cache = Cache::getInstance();

        if ($cache->cacheConnected()) {
            $ret = $cache->flush();
            
            sleep(1);
            
            if ($ret) {
                $this->sendSuccess('Cache flushed');
            } else {
                $this->sendFail('Failed to flush cache');
            }
        } else {
            $this->sendFail('Cache not connected');
        }
    }

    public function indexerAction()
    {
        header('Content-Type: text/plain');

        $command = Config::get()->indexer->full;
        system($command);
        exit;
    }

    public function statsAction()
    {
        $stats = Model_Gallery::getStats();
        
        $this->sendSuccess($stats);
    }

    public function testAction()
    {
        $galleries = R::find('gallery', 'archived = 1');

        foreach ($galleries as $gallery) {
            $gallery->updateSearch();
        }

        die();
    }
    
    public function updateAction()
    {
        $id = $this->getParam('id');
        $status = $this->getParam('readStatus');
        
        $gallery = R::load('gallery', $id);
        
        if ($gallery) {
            $gallery->read = $status;
            R::store($gallery);
            
            $cache = Cache::getInstance();
            $cache->deleteObject('gallery', $gallery->id);

            if (isset(Config::get()->indexer->full)) {
                $command = Config::get()->indexer->full;
                system($command);
            }

            $this->sendSuccess($gallery->read);
        } else {
            $this->sendFail();
        }
    }
    
    public function updateColorAction()
    {
        $id = $this->getParam('id');
        $color = $this->getParam('color');
        
        $gallery = R::load('gallery', $id);
        
        if ($gallery) {
            $gallery->color = $color;
            R::store($gallery);
            
            $cache = Cache::getInstance();
            $cache->deleteObject('gallery', $gallery->id);

            if (isset(Config::get()->indexer->full)) {
                $command = Config::get()->indexer->full;
                system($command);
            }

            $this->sendSuccess($gallery->color);
        } else {
            $this->sendFail();
        }
    }

    protected function sendSuccess($data)
    {
        $data = array(
            'ret' => true,
            'data' => $data
        );

        $data['time'] = microtime(true) - $this->start;

        $this->sendResponse($data);
    }

    protected function sendFail($message)
    {
        $data = array(
            'ret' => false,
            'message' => $message
        );

        $this->sendResponse($data);
    }

    protected function sendResponse($resp)
    {
        $body = json_encode($resp);

        @ob_end_clean();

        if (function_exists('ob_gzhandler')) {
            ob_start('ob_gzhandler');
        }
        
        header('Access-Control-Allow-Origin: *');
        header('Content-Type: application/json');
        echo $body;

        ob_end_flush();

        exit;
    }

    protected function getParam($param, $default = null)
    {
        if (array_key_exists($param, $this->params)) {
            return $this->params[$param];
        } else {
            return $default;
        }
    }

    protected function getParams()
    {
        $params = func_get_args();
        $values = array();

        foreach ($params as $param) {
            $values[] = $this->getParam($param);
        }

        return $values;
    }

    public function handle($params)
    {
        if (!is_array($params) || !array_key_exists('action', $params)) {
            $this->sendFail('Invalid request');
        }

        $this->params = $params;

        $methodName = strtolower($params['action']).'Action';

        if (!method_exists($this, $methodName)) {
            $this->sendFail('Invalid action');
        }

        $this->start = microtime(true);

        $this->$methodName();

        $this->sendFail('Action fell through without sending a response');
    }
}
