<?php

class Cache {
    
    private static $instance;
    private $memcache;
    private $connected;

    protected function __construct() {
        $this->connected = false;

        if(class_exists('Memcache')) {
            $config = Config::get();
            if($config->memcache) {
                $memcache = new Memcache();
                $this->connected = $memcache->connect($config->memcache->host, $config->memcache->port);

                if($this->connected) {
                    $this->memcache = $memcache;
                }
            }
        }
    }

    public static function getInstance() {
        if(!(self::$instance instanceof self)) {
            self::$instance = new self();
        }
        
        return self::$instance;
    }

    public function cacheConnected() {
        return $this->connected;
    }

    public function getObject($objectType, $objectId) {
        if($this->connected) {
            $key = $this->createObjectKey($objectType, $objectId);
            $data = $this->memcache->get($key);
            if($data) {
                return $data;
            }
            else {
                return false;
            }
        }
    }

    public function setObject($objectType, $objectId, $data) {
        if($this->connected) {
            $key = $this->createObjectKey($objectType, $objectId);
            $this->memcache->set($key, $data, MEMCACHE_COMPRESSED, 0);
        }
    }

    public function deleteObject($objectType, $objectId) {
        if($this->connected) {
            $key = $this->createObjectKey($objectType, $objectId);
            $this->memcache->delete($key);
        }
    }

    public function flush() {
        if($this->connected) {
            return $this->memcache->flush();
        }
    }

    public function createObjectKey($objectType, $objectId) {
        return sprintf("%s_%d", $objectType, $objectId);
    }

    public function __destruct() {
        if($this->connected) {
            $this->memcache->close();
        }
    }
}

?>