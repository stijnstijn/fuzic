<?php
/**
 * Cache handler
 *
 * @package Fuzic
 */
namespace Fuzic\Lib;

use Fuzic;


/**
 * Cache handler
 */
class Cache
{
    /**
     * @var  string  Name of this cache instance
     */
    private $name;
    /**
     * @var  resource  Instance of the memcache server used by this cache
     */
    private $memcached;
    /**
     * @var boolean  If memcached is not present, don't crash
     */
    private $memcached_available;

    /**
     * Set up memcache connection
     *
     * @param  string $name Name of this cache instance
     *
     * @throws  \ErrorException  Throws an exception if no connection to the cache server could be established.
     */
    public function __construct($name) {
        $this->name = $name;
        if (class_exists('Memcached')) {
            $this->memcached_available = true;
            $this->memcached = new \Memcached($this->name);

            $servers = $this->memcached->getServerList();
            if (is_array($servers)) {
                foreach ($servers as $server) {
                    if ($server['host'] == Fuzic\Config::MEMCACHED_SERVER && $server['port'] == Fuzic\Config::MEMCACHED_PORT) {
                        return true;
                    }
                }
            }

            $connection = $this->memcached->addServer(Fuzic\Config::MEMCACHED_SERVER, Fuzic\Config::MEMCACHED_PORT);
            if (!$connection) {
                throw new \ErrorException('Could not connect to memcached server');
            }
        } else {
            $this->memcached_available = false;
        }
    }

    /**
     * Add item to cache
     *
     * @param  string  $name  Name of the item
     * @param  mixed   $value Value of the item
     * @param  integer $ttl   Time to live; 0 for infinite
     *
     * @return boolean  Success
     *
     * @throws  \ErrorException  Throws exception if item could not be stored in cache
     */
    public function set($name, $value, $ttl = 0) {
        $h = fopen(ROOT.'/cache/'.sha1($name), 'w');
        fwrite($h, serialize($value));
        fclose($h);
        return true;

        $cached = $this->memcached->replace($name, $value, $ttl);

        if ($cached === false) {
            $cached = $this->memcached->set($name, $value, $ttl);
        }

        if (!$cached) {
            throw new \ErrorException('Memcache storage failed');
        }

        return true;
    }

    /**
     * Get cache item
     *
     * @param  string $name Name of the item
     *
     * @return  mixed  Value of the item, or false if item does not exist
     */
    public function get($name) {
        $result = ($this->memcached_available) ? $this->memcached->get($name) : false;
        if (!$result || !$this->memcached_available) {
            if (is_readable(ROOT.'/cache/'.sha1($name))) {
                $result = unserialize(file_get_contents(ROOT.'/cache/'.sha1($name)));
            } else {
                $result = false;
            }
        }
        return $result;
    }

    /**
     * List cache contents
     *
     * For debugging purposes
     */
    public function dump() {
        if (!$this->memcached_available) {
            return false;
        }

        $list = array();
        $allSlabs = $this->memcached->getExtendedStats('slabs');
        $items = $this->memcached->getExtendedStats('items');
        foreach ($allSlabs as $server => $slabs) {
            foreach ($slabs AS $slabId => $slabMeta) {
                $cdump = $this->memcached->getExtendedStats('cachedump', (int)$slabId);
                foreach ($cdump AS $keys => $arrVal) {
                    if (!is_array($arrVal)) {
                        continue;
                    }
                    foreach ($arrVal AS $k => $v) {
                        echo $k.'<br>';
                    }
                }
            }
        }
    }
}