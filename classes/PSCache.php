<?php
class PSCache
{
    private $_cache;
    
    public function __construct()
    {
        if (DRIVER === 'prestashop')
            $this->_cache = Cache::getInstance();
        else
            $this->_cache = new CacheFilesystem();
    }
    
    public function store($key, $value, $ttl = 0)
    {
        ignore_user_abort(true);
        if ($ttl < 0)
            $ttl = 0;
        
        return $this->_cache->set($key, $value, $ttl);
    }

    public function retrieve($key)
    {
        $data = $this->_cache->get($key);
        if (!empty($data))
            return $data;
        else
            return false;
    }
    
    public function delete($key)
    {
        $this->_cache->clean($key);
    }

    public function flush()
    {
        $this->_cache->flush();
    }
}

// based on Evert Pot code
// https://evertpot.com/107/ 9.4.2017
class CacheFilesystem {
    
    private $path;

    public function set(&$key, &$data, $ttl = 0)
    {
        $cacheFile = $this->getCachePath($key);
        $dir = dirname($cacheFile);
        if (!is_dir($dir))
            mkdir($dir, DEFAULT_CHMOD, true);

        // Opening the file in read/write mode
        $h = fopen($cacheFile, 'w');
        if (!$h)
            return false;

        // exclusive lock, add LOCK_NB as a bitmask if you don't want flock() to block while locking
        flock($h, LOCK_EX | LOCK_NB);
        
        if (fwrite($h, $data) === false)
        {
            flock($h, LOCK_UN); 
            fclose($h);
            return false;
        }
        flock($h, LOCK_UN); // avoid waiting for system to unlock file, it takes system a few seconds
        fclose($h);
        return true;
    }

    public function get(&$key)
    {
        $cacheFile = $this->getCachePath($key);

        if (!file_exists($cacheFile) /*&& filesize($cacheFile) < 1*/)
            return false;
        
        if(time() - CACHE_TTL < filemtime($cacheFile))
        {
            //ob_clean();
            if (@readfile($cacheFile) === false) // send file directly to browser
                return false; // if reading is unsuccessful, continue 
            exit();
        }
        else // cache expired
        {
            @unlink($cacheFile);
            return false;
        }
    }

    public function flush($deleteAll = false)
    {
        $this->cleanDir($this->getCachePath(null, false), $deleteAll);
    }
    
    public function clean($key)
    {
        $cacheFile = $this->getCachePath($key);
        
        if (file_exists($cacheFile))
            return @unlink($cacheFile);
        else
            return false;
    }
    
    private function getCachePath($key = null, $use_host = true)
    {
        if ($use_host)
            $host = isset($_SERVER['HTTP_HOST']) ? trim(strtolower($_SERVER['HTTP_HOST'])) . DS : 'nohost' . DS;
        else
            $host = '';
        
        if ($key !== null)
            $key = $key[0] . DS . $key;
        else
            $key = '';
        
        return __DIR__ . DS . '..' . DS . CACHE_DIR . DS . $host . $key;
    }
    
    private function cleanDir($dir, $deleteFolders = false)
    {
        if (is_dir($dir))
        {
            $objects = scandir($dir);
            foreach ($objects as $object)
            {
                if ($object !== '.' && $object !== '..' && $object !== '.htaccess')
                {
                    if (filetype($dir . DS . $object) === 'dir')
                        $this->cleanDir($dir . DS . $object, $deleteFolders);
                    else
                        @unlink ($dir . DS . $object);
                }
            }
            reset($objects);
            if ($deleteFolders)
                @rmdir($dir);
        }
    }
}
