<?php

namespace UserlandSession\Tests;

use UserlandSession\IdGenerator;

class IdGeneratorTest extends \PHPUnit_Framework_TestCase {

    const NUM_IDS_TO_CHECK = 40;

    function testNewIds() {
        $ids = array();

        for ($i = 0; $i < self::NUM_IDS_TO_CHECK; $i++) {
            $id = IdGenerator::generateSessionId();

            $this->assertEquals(1, preg_match('~^[0-9a-z]{40}$~', $id));
            $ids[$id] = true;
        }

        $this->assertSame(self::NUM_IDS_TO_CHECK, count($ids));
    }
}
 