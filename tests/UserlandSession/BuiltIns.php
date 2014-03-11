<?php

namespace UserlandSession;

/**
 * This class works with the namespaced functions in tests/override_builtins.php and allows
 * "overriding" some built-in functions so we can capture their arguments or control
 * their behavior.
 *
 * @see tests/override_builtins.php
 */
class BuiltIns
{
    /**
     * This will be added to the normal return value of the native \time() call
     *
     * @var int
     */
    public $timeOffset = 0;

    /**
     * If non-zero, time() will always return this value
     *
     * @var int
     */
    public $fixedTime = 0;

    /**
     * Array of setcookie() parameters from each call
     *
     * @var array[]
     */
    public $cookiesSet = array();

    /**
     * Array of strings passed to heder()
     *
     * @var string[]
     */
    public $headersSet = array();

    /**
     * Controls the return value of headers_sent()
     *
     * @var bool
     */
    public $headers_sent = false;

    /**
     * If headers_sent() is called, the $file argument will be set to this value
     *
     * @var string
     */
    public $headers_sent_file;

    /**
     * If headers_sent() is called, the $line argument will be set to this value
     *
     * @var string
     */
    public $headers_sent_line;

    /**
     * An array of return values to mt_rand, where the keys are "$min|$max". This
     * makes mt_rand() deterministic depending on the keys set.
     *
     * @var int[]
     */
    public $rand_output = array();

    public function mt_rand($min = 0, $max = null) {
        if (array_key_exists("$min|$max", $this->rand_output)) {
            return $this->rand_output["$min|$max"];
        }
        return \mt_rand($min, $max);
    }

    protected static $instance;

    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new BuiltIns();
        }
        return self::$instance;
    }

    public static function reset() {
        self::$instance = null;
    }
}
