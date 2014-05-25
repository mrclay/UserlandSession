<?php

namespace UserlandSession\Handler;

/**
 * File session storage.
 */
class FileHandler implements \SessionHandlerInterface
{
    /**
     * @param bool $lockFiles Lock files for read/write (true by default)
     */
    public function __construct($lockFiles = true)
    {
        $this->setLocking($lockFiles);
    }

    /**
     * @param bool $lockFiles Lock files for read/write (true by default)
     */
    public function setLocking($lockFiles = true)
    {
        $this->locking = (bool)$lockFiles;
    }

    /**
     * @return bool
     */
    public function getLocking()
    {
        return $this->locking;
    }

    /**
     * @param string $save_path
     *   '/path', 'N;/path', or 'N;octal-mode;/path
     *
     * @param string $name
     *
     * @return bool
     *
     * @throws \InvalidArgumentException
     */
    public function open($save_path, $name)
    {
        $this->name = $name;

        if (!$save_path) {
            throw new \InvalidArgumentException("Invalid argument \$save_path '$save_path'");
        }

        $count = substr_count($save_path, ';');
        if ($count > 2) {
            throw new \InvalidArgumentException("Invalid argument \$save_path '$save_path'");
        }

        if ($count) {
            $pieces = explode(';', $save_path);
            $save_path = array_pop($pieces);
        }

        if (!is_dir($save_path)) {
            mkdir($save_path, 0777, true);
        }

        $this->path = rtrim($save_path, '/\\');
        if (!is_writable($this->path)) {
            throw new \InvalidArgumentException("\$save_path is not writable '$save_path'");
        }

        return true;
    }

    /**
     * @return bool
     */
    public function close()
    {
        return true;
    }

    /**
     * @param string $id
     * @return string|false
     */
    public function read($id)
    {
        $file = $this->getFilePath($id);
        if (is_file($file) && is_readable($file)) {
            if ($this->locking) {
                $fp = fopen($file, 'rb');
                flock($fp, LOCK_SH);
                $ret = stream_get_contents($fp);
                flock($fp, LOCK_UN);
                fclose($fp);
                return $ret;
            } else {
                return file_get_contents($file);
            }
        }
        return false;
    }

    /**
     * @param string $id
     * @param string $data
     * @return bool
     */
    public function write($id, $data)
    {
        $file = $this->getFilePath($id);
        if (is_file($file) && !is_writable($file)) {
            return false;
        }
        $flag = $this->locking ? LOCK_EX : null;
        return (bool)file_put_contents($file, $data, $flag);
    }

    /**
     * @param string $id
     * @return bool
     */
    public function destroy($id)
    {
        $file = $this->getFilePath($id);
        if (is_file($file) && is_writable($file)) {
            return unlink($file);
        }
        return false;
    }

    /**
     * @param int $maxLifetime
     * @return bool
     */
    public function gc($maxLifetime)
    {
        $d = dir($this->path);
        //echo "Path: " . $d->path . "\n";
        $t = time();
        while (false !== ($entry = $d->read())) {
            if (0 === strpos($entry, $this->name . '_')) {
                $file = $this->path . DIRECTORY_SEPARATOR . $entry;
                $mtime = filemtime($file);
                if (false !== $mtime) {
                    $lifetime = $t - $mtime;
                    if ($lifetime > $maxLifetime && is_writable($file)) {
                        unlink($file);
                    }
                }
            }
        }
        $d->close();
        return true;
    }

    /**
     * @param string $id
     * @return string
     */
    protected function getFilePath($id)
    {
        return $this->path . DIRECTORY_SEPARATOR . $this->name . '_' . $id;
    }

    /**
     * @var string
     */
    protected $path = null;

    /**
     * @var bool
     */
    protected $locking;

    /**
     * @var string
     */
    protected $name;
}