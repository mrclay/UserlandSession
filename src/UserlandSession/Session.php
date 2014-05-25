<?php

namespace UserlandSession;

use UserlandSession\Serializer\PhpSerializer;
use UserlandSession\Serializer\SerializerInterface;

/**
 * A PHP emulation of native session behavior. Other than HTTP IO (header() and
 * setcookie(), there's no global state in this implementation; you can have an active
 * session beside another instance or beside the native session.
 *
 * Note: You can set cache_limiter = '', meaning no headers (other than Set-Cookie) will
 * be sent at start(). This may be useful if you need to use this class in tandem with
 * native sessions.
 *
 * Also a tiny session fixation vulnerability has been prevented in start().
 *
 * @see https://github.com/php/php-src/blob/master/ext/session/session.c
 */
class Session
{
    const CACHE_LIMITER_NONE = '';
    const CACHE_LIMITER_PUBLIC = 'public';
    const CACHE_LIMITER_PRIVATE_NO_EXPIRE = 'private_no_expire';
    const CACHE_LIMITER_PRIVATE = 'private';
    const CACHE_LIMITER_NOCACHE = 'nocache';

    /**
     * The default session name if not provided
     */
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
     * Length of session IDs generated
     *
     * @var int
     */
    public $idLength = 40;

    /**
     * Get a value from the session
     *
     * @param string $key
     * @param mixed $default
     *
     * @return mixed
     *
     * @throws Exception
     */
    public function get($key, $default = null)
    {
        if (!$this->id) {
            throw new Exception('Cannot use get without active session.');
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
     *
     * @throws Exception
     */
    public function set($key, $value = null)
    {
        if (!$this->id) {
            throw new Exception('Cannot use set without active session.');
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
     * Create a session.
     *
     * @param \SessionHandlerInterface $handler    The storage handler
     * @param string                   $name       Session name.
     * @param string                   $save_path  Path sent to the handler. If not specified, session_save_path() is used.
     * @param SerializerInterface      $serializer Value serializer. Uses PhpSerializer if not given
     *
     * @throws \InvalidArgumentException
     */
    public function __construct(
        \SessionHandlerInterface $handler,
        $name = Session::DEFAULT_SESSION_NAME,
        $save_path = '',
        SerializerInterface $serializer = null
    ) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException('name may contain only a-zA-Z_');
        }
        $this->name = $name;

        if ($handler instanceof \SessionHandler) {
            // SessionHandler's operation depends on the native session
            throw new \InvalidArgumentException('Cannot use native SessionHandler. Use UserlandSession\Handler\FileHandler');
        }
        $this->handler = $handler;

        $this->savePath = $save_path;

        if (!$serializer) {
            $serializer = new PhpSerializer();
        }
        $this->serializer = $serializer;
    }

    /**
     * Get the session ID, or request an ID to be used when the session begins.
     *
     * @param string $id Requested session id. May contain only [a-zA-Z0-9-_]
     *
     * @return string ('' means there is no active session)
     *
     * @throws \InvalidArgumentException|Exception
     */
    public function id($id = null)
    {
        if ($id) {
            if (!$this->isValidId($id)) {
                throw new \InvalidArgumentException('$id may contain only [a-zA-Z0-9-_]');
            }
            if ($this->id) {
                throw new Exception('Cannot set id while session is active');
            }
            $this->requestedId = $id;
        }
        return $this->id;
    }

    /**
     * Get the session name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return \SessionHandlerInterface
     */
    public function getHandler()
    {
        return $this->handler;
    }

    /**
     * @return string
     */
    public function getSavePath()
    {
        return $this->savePath;
    }

    /**
     * Get a session ID from the client. (Do not use unless data exists for this ID!)
     *
     * @return string
     */
    public function getIdFromCookie()
    {
        if (empty($_COOKIE[$this->name])) {
            return false;
        }
        $id = $_COOKIE[$this->name];
        if (!is_string($id) || !$this->isValidId($id)) {
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
            $this->handler->open($this->savePath, $this->name);
        }
        $ret = (bool)$this->handler->read($id);
        if (!$this->id) {
            $this->handler->close();
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
     * @throws Exception
     */
    public function start()
    {
        if (headers_sent() || $this->id) {
            return false;
        }

        if ($this->data !== null) {
            throw new Exception('The property "data" cannot be set until the session is started.');
        }
        $this->data = array();

        if ($this->requestedId) {
            $this->setCookie($this->name, $this->requestedId);
            $this->id = $this->requestedId;
        } else {
            $id = $this->getIdFromCookie();
            $this->id = $id ? $id : IdGenerator::generateSessionId($this->idLength);
        }

        // open storage (reqd for GC)
        $this->handler->open($this->savePath, $this->name);

        // should we call GC?
        $rand = mt_rand(1, $this->gc_divisor);
        if ($rand <= $this->gc_probability) {
            $this->handler->gc($this->gc_maxlifetime);
        }

        if (!$this->requestedId) {
            // try data fetch
            if (!$this->loadData()) {
                // unlike the native PHP session, we don't let users choose their own
                // session IDs if there's no data. This prevents session fixation through
                // cookies (very hard for an attacker, but why leave this door open?).
                $this->id = IdGenerator::generateSessionId($this->idLength);
                $this->setCookie($this->name, $this->id);
            }
        }
        $this->requestedId = null;

        $this->sendStartHeaders();

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
        if (!$this->id) {
            return false;
        }
        // allow session to be closed, even if write fails. Otherwise destructor will try again.
        $wasSaved = $this->saveData();
        $this->handler->close();
        $this->id = '';
        $this->data = null;
        return $wasSaved;
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
            $this->handler->destroy($this->id);
            $this->handler->close();
            $this->id = '';
            $this->data = null;
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
        if (headers_sent() || !$this->id) {
            return false;
        }
        $oldId = $this->id;
        $this->id = IdGenerator::generateSessionId($this->idLength);
        $this->setCookie($this->name, $this->id);
        if ($oldId && $deleteOldSession) {
            $this->handler->destroy($oldId);
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
        return setcookie(
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
        $serialization = $this->handler->read($this->id);
        if (is_string($serialization)) {
            $this->data = @$this->serializer->unserialize($serialization);
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
        $strData = $this->serializer->serialize($this->data);
        return $this->handler->write($this->id, $strData);
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
        return setcookie(
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
     * Send headers based on cache_limiter and cache_expire properties
     */
    protected function sendStartHeaders()
    {
        // send optional cache limiter
        // this is actual session behavior rather than what's documented.
        $lastModified = self::formatAsGmt(filemtime($_SERVER['SCRIPT_FILENAME']));

        $ce = $this->cache_expire;
        switch ($this->cache_limiter) {
            case self::CACHE_LIMITER_PUBLIC:
                header('Expires: ' . self::formatAsGmt(time() + $ce));
                header("Cache-Control: public, max-age=$ce");
                header('Last-Modified: ' . $lastModified);
                break;
            case self::CACHE_LIMITER_PRIVATE_NO_EXPIRE:
                header("Cache-Control: private, max-age=$ce, pre-check=$ce");
                header('Last-Modified: ' . $lastModified);
                break;
            case self::CACHE_LIMITER_PRIVATE:
                header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
                header("Cache-Control: private, max-age=$ce, pre-check=$ce");
                header('Last-Modified: ' . $lastModified);
                break;
            case self::CACHE_LIMITER_NOCACHE:
                header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
                header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
                header('Pragma: no-cache');
                break;
            case self::CACHE_LIMITER_NONE:
                // send no cache headers, please
                break;
        }
    }

    /**
     * @param string $id
     *
     * @return bool
     */
    protected function isValidId($id)
    {
        return (bool)preg_match('/^[a-zA-Z0-9\\-\\_]+$/', $id);
    }

    /**
     * @var \SessionHandlerInterface
     */
    protected $handler;

    /**
     * @var SerializerInterface
     */
    protected $serializer;

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

    /**
     * Path sent to the handler when opening
     *
     * @var string
     */
    protected $savePath;
}