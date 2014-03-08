<?php

namespace UserlandSession;

use UserlandSession\Storage\FileStorage;
use UserlandSession\Storage\StorageInterface;

/**
 * A PHP emulation of native session behavior. Other than HTTP IO (header() and
 * setcookie(), there's no global state in this implementation; you can have an active
 * session beside another instance or beside the native session.
 *
 * Only id has a get/setter function. The other options are public properties.
 *
 * There's no set_handler/module because one has to inject a storage handler into the
 * constructor. This also moved the save_path option to the Files handler.
 *
 * Session name is set in the storage handler, which prevents the user from mistakenly
 * re-using a storage handler for multiple sessions.
 *
 * The biggest usage difference is that you can set cache_limiter = '', meaning no headers
 * (other than Set-Cookie) will be sent at start(). This may be useful if you need to use
 * this class in tandem with native sessions.
 *
 * Also a tiny session fixation vulnerability has been prevented in start().
 *
 * @see http://svn.php.net/viewvc/php/php-src/trunk/ext/session/session.c?view=markup
 */
class Session
{
    const CACHE_LIMITER_NONE = '';
    const CACHE_LIMITER_PUBLIC = 'public';
    const CACHE_LIMITER_PRIVATE_NO_EXPIRE = 'private_no_expire';
    const CACHE_LIMITER_PRIVATE = 'private';
    const CACHE_LIMITER_NOCACHE = 'nocache';

    const DEFAULT_SESSION_NAME = 'ULSESS';

    /**
     * Time until cookie expires in seconds (0 = session cookie)
     *
     * @var int
     */
    public $cookie_lifetime = 0;

    /**
     * URL path in which cookie is available
     *
     * @var string
     */
    public $cookie_path = '/';

    /**
     * Cookie domain
     *
     * @var string
     */
    public $cookie_domain = '';

    /**
     * Will cookies be sent only over HTTPS?
     *
     * @var bool
     */
    public $cookie_secure = false;

    /**
     * Will cookies be available only at the server?
     *
     * @var bool
     */
    public $cookie_httponly = false;

    /**
     * Time in seconds until the session data will be considered abandoned by the garbage collector
     *
     * @var int
     */
    public $gc_maxlifetime = 1400;

    /**
     * Numerator of the probability the garbage collector will be run on this request
     *
     * @var int
     */
    public $gc_probability = 1;

    /**
     * Denominator of the probability the garbage collector will be run on this request
     *
     * @var int
     */
    public $gc_divisor = 100;

    /**
     * Length of session IDs generated
     *
     * @var int
     */
    public $idLength = 40;

    /**
     * Determines what headers are sent when the session is started. If you're using UserlandSession
     * alongside existing sessions, you may want to set this to CACHE_LIMITER_NONE.
     *
     * @var string
     */
    public $cache_limiter = self::CACHE_LIMITER_NOCACHE;

    /**
     * If cache_limiter is not "nocache", this tells clients how long they can used cached versions
     * of pages (Expires header sent with pages).
     *
     * @var int
     */
    public $cache_expire = 180;

    /**
     * Persisted session data. Alter this after start()-ing the session
     *
     * @var array
     */
    public $data = null;

    /**
     * @return StorageInterface
     */
    public function getStorage()
    {
        return $this->storage;
    }

    /**
     * Get a value from the session
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     */
    public function get($key, $default = null)
    {
        if (!$this->id) {
            return $default;
        }
        return array_key_exists($key, $this->data) ? $this->data[$key] : $default;
    }

    /**
     * Set a value (or multiple values if you pass in an associative array) in the session
     *
     * @param string|array $key
     * @param mixed $value
     *
     * @return bool returns true if the session has been started
     */
    public function set($key, $value = null)
    {
        if (!$this->id) {
            return false;
        }
        if (is_array($key)) {
            foreach ($key as $k => $value) {
                $this->data[$k] = $value;
            }
        } else {
            $this->data[$key] = $value;
        }
        return true;
    }

    /**
     * Users should consider using factory() to prevent cookie/storage name collisions.
     *
     * @param StorageInterface $storage
     * @param Http $http
     *
     * @throws Exception
     */
    public function __construct(StorageInterface $storage, Http $http = null)
    {
        $this->storage = $storage;
        if (!$http) {
            $http = new Http();
        }
        $this->http = $http;
        $this->name = $storage->getName();
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $this->name)) {
            throw new Exception('UserlandSession name may contain only a-zA-Z_');
        }
    }

    /**
     * More safely create a session. This function will only let you create sessions with
     * names that are unique (case-insensitively) to avoid creating cookie/storage
     * collisions. It also forbids using a name that matches the global setting session.name.
     *
     * @param StorageInterface $storage (will use Files if not specified)
     *
     * @return Session
     *
     * @throws Exception
     */
    public static function factory(StorageInterface $storage = null)
    {
        static $activeNames = array();
        static $i = 0;

        if (null === $storage) {
            $name = self::DEFAULT_SESSION_NAME;
            if ($i) {
                $name .= $i;
            }
            $storage = new FileStorage($name);
            $i++;
        }
        $activeNames[strtoupper(ini_get('session.name'))] = true;
        $name = strtoupper($storage->getName());
        if (isset($activeNames[$name])) {
            throw new Exception('UserlandSession name already used');
        }
        $activeNames[$name] = true;

        return new self($storage);
    }

    /**
     * Get the session ID, or set an ID to be used when the session
     * begins. When setting, the format is validated by the storage handler.
     *
     * @param string $id
     *
     * @return string ('' means there is no active session)
     */
    public function id($id = null)
    {
        if (!$this->id && is_string($id) && $this->storage->idIsValid($id)) {
            $this->requestedId = $id;
        }
        return $this->id;
    }

    /**
     * Get a session ID from the client
     *
     * @return string
     */
    public function getIdFromCookie()
    {
        $id = $this->http->getCookie($this->name);
        if (empty($id) || !$this->storage->idIsValid($id)) {
            return false;
        }
        return $id;
    }

    /**
     * Does the storage handler have data under this ID?
     *
     * @param string $id
     *
     * @return bool
     */
    public function persistedDataExists($id)
    {
        if (!$this->id) {
            $this->storage->open();
        }
        $ret = (bool)$this->storage->read($id);
        if (!$this->id) {
            $this->storage->close();
        }
        return $ret;
    }

    /**
     * Is the client's ID valid and pointing to existing session data? You might want to
     * call this if you don't want to start sessions for every visitor.
     *
     * @return bool
     */
    public function sessionLikelyExists()
    {
        $id = $this->getIdFromCookie();
        return $id && $this->persistedDataExists($id);
    }

    /**
     * Start the session.
     *
     * @return bool success
     */
    public function start()
    {
        if ($this->http->headers_sent() || $this->id) {
            return false;
        }
        $this->data = array();
        if ($this->requestedId) {
            $this->setCookie($this->name, $this->requestedId);
            $this->id = $this->requestedId;
            $this->requestedId = null;
        } else {
            $id = $this->getIdFromCookie();
            $this->id = $id ? $id : IdGenerator::generateSessionId($this->idLength);
        }

        // open storage (reqd for GC)
        $this->storage->open();

        // should we call GC?
        $rand = mt_rand(1, $this->gc_divisor);
        if ($rand <= $this->gc_probability) {
            $this->storage->gc($this->gc_maxlifetime);
        }

        // try data fetch
        if (!$this->loadData()) {
            // unlike the native PHP session, we don't let users choose their own
            // session IDs if there's no data. This prevents session fixation through 
            // cookies (very hard for an attacker, but why leave this door open?).
            $this->id = IdGenerator::generateSessionId($this->idLength);
            $this->setCookie($this->name, $this->id);
        }
        // send optional cache limiter
        // this is actual session behavior rather than what's documented.
        $lastModified = self::formatAsGmt(filemtime($_SERVER['SCRIPT_FILENAME']));

        $ce = $this->cache_expire;
        switch ($this->cache_limiter) {
            case self::CACHE_LIMITER_PUBLIC:
                $this->http->header('Expires: ' . self::formatAsGmt(time() + $ce));
                $this->http->header("Cache-Control: public, max-age=$ce");
                $this->http->header('Last-Modified: ' . $lastModified);
                break;
            case self::CACHE_LIMITER_PRIVATE_NO_EXPIRE:
                $this->http->header("Cache-Control: private, max-age=$ce, pre-check=$ce");
                $this->http->header('Last-Modified: ' . $lastModified);
                break;
            case self::CACHE_LIMITER_PRIVATE:
                $this->http->header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
                $this->http->header("Cache-Control: private, max-age=$ce, pre-check=$ce");
                $this->http->header('Last-Modified: ' . $lastModified);
                break;
            case self::CACHE_LIMITER_NOCACHE:
                $this->http->header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
                $this->http->header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                $this->http->header('Pragma: no-cache');
                break;
            case self::CACHE_LIMITER_NONE:
                // send no cache headers, please
                break;
        }
        return true;
    }

    /**
     * Write data and close the session. (This is called automatically by the destructor,
     * but for the sake of proper serialization, you should call it explicitly)
     *
     * @return bool success
     */
    public function writeClose()
    {
        if (!$this->id || !$this->saveData()) {
            return false;
        }
        $this->storage->close();
        $this->id = '';
        return true;
    }

    public function __destruct()
    {
        if ($this->id) {
            $this->writeClose();
        }
    }

    /**
     * Stop the session and destroy its persisted data.
     *
     * @param bool $removeCookie Remove the session cookie, too?
     *
     * @return bool success
     */
    public function destroy($removeCookie = false)
    {
        if ($this->id) {
            if ($removeCookie) {
                $this->removeCookie();
            }
            $this->storage->destroy($this->id);
            $this->storage->close();
            $this->id = '';
            return true;
        }
        return false;
    }

    /**
     * Regenerate the session ID, update the browser's cookie, and optionally remove the
     * previous ID's session storage.
     *
     * @param bool $deleteOldSession
     *
     * @return bool success
     */
    public function regenerateId($deleteOldSession = false)
    {
        if ($this->http->headers_sent() || !$this->id) {
            return false;
        }
        $this->removeCookie();
        $oldId = $this->id;
        $this->id = IdGenerator::generateSessionId($this->idLength);
        $this->setCookie($this->name, $this->id);
        if ($oldId && $deleteOldSession) {
            $this->storage->destroy($oldId);
        }
        return true;
    }

    /**
     * Remove the session cookie
     *
     * @return bool success
     */
    public function removeCookie()
    {
        return $this->http->setcookie(
            $this->name,
            '',
            time() - 86400,
            $this->cookie_path,
            $this->cookie_domain,
            (bool)$this->cookie_secure,
            (bool)$this->cookie_httponly
        );
    }

    /**
     * Get a GMT formatted date for use in HTTP headers
     *
     * @param int $time unix timestamp
     *
     * @return string
     */
    public static function formatAsGmt($time)
    {
        return gmdate('D, d M Y H:i:s \G\M\T', $time);
    }

    /**
     * @return bool
     */
    protected function loadData()
    {
        $serialization = $this->storage->read($this->id);
        if (is_string($serialization)) {
            $this->data = unserialize($serialization);
            if (is_array($this->data)) {
                return true;
            }
        }
        $this->data = array();
        return false;
    }

    /**
     * @return bool
     */
    protected function saveData()
    {
        $strData = serialize($this->data);
        return $this->storage->write($this->id, $strData);
    }

    /**
     * @param string $name
     * @param string $id
     *
     * @return bool
     */
    protected function setCookie($name, $id)
    {
        $expire = $this->cookie_lifetime ? time() + (int)$this->cookie_lifetime : 0;
        return $this->http->setcookie(
            $name,
            $id,
            $expire,
            $this->cookie_path,
            $this->cookie_domain,
            (bool)$this->cookie_secure,
            (bool)$this->cookie_httponly
        );
    }

    /**
     * @var StorageInterface
     */
    protected $storage;

    /**
     * PHP's global header state
     *
     * @var Http
     */
    protected $http;

    /**
     * Active session ID, or empty if inactive
     *
     * @var string
     */
    protected $id = '';

    /**
     * User has requested this be used as ID for the next session
     *
     * @var string
     */
    protected $requestedId = '';

    /**
     * Copy of session name from storage handler
     *
     * @var string
     */
    protected $name;
}