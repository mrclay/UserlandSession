<?php
/**
 * class used by tests/mock_globals.php
 */

namespace UserlandSession;

class Testing
{
    public $timeOffset = 0;
    public $fixedTime = 0;

    public $cookiesSet = array();

    public $headersSet = array();

    public $headers_sent = false;
    public $headers_sent_file;
    public $headers_sent_line;

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
            self::$instance = new Testing();
        }
        return self::$instance;
    }

    public static function reset() {
        self::$instance = null;
    }
}
