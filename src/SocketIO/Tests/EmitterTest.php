<?php

namespace SocketIO\Tests;

use SocketIO\Binary;
use SocketIO\Emitter;

class EmitterTest extends \PHPUnit_Framework_TestCase
{
    public function testEmitCreatesARedisPublish()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter(null, ['host' => '127.0.0.1', 'port' => '6379']);
        $emitter->emit('so', 'yo');

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(stripos($contents, 'publish') !== false);
    }

    public function testDefaultsToLocalHostAndDefaultPort()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter();
        $emitter->emit('so', 'yo');

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(stripos($contents, 'publish') !== false);
    }


    public function testCanProvideRedisInstance()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $redis = new \TinyRedisClient('127.0.0.1:6379');
        $emitter = new Emitter($redis);
        $emitter->emit('so', 'yo');

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(stripos($contents, 'publish') !== false);
    }

    public function testPublishContainsExpectedAttributes()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter(['host' => '127.0.0.1', 'port' => '6379']);
        $emitter->emit('so', 'yo');

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(strpos($contents, 'so') !== false);
        $this->assertTrue(strpos($contents, 'yo') !== false);
        $this->assertTrue(strpos($contents, 'rooms') !== false);
        $this->assertTrue(strpos($contents, 'flags') !== false);
        // Should not broadcast by default
        $this->assertFalse(strpos($contents, 'broadcast') !== false);
        // Should have the default namespace
        $this->assertTrue(strpos($contents, '/') !== false);
    }

    public function testPublishContainsBroadcastWhenBroadcasting()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter(null, ['host' => '127.0.0.1', 'port' => '6379']);
        $emitter->broadcast->emit('so', 'yo');

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(strpos($contents, 'so') !== false);
        $this->assertTrue(strpos($contents, 'yo') !== false);
        $this->assertTrue(strpos($contents, 'rooms') !== false);
        $this->assertTrue(strpos($contents, 'flags') !== false);
        $this->assertTrue(strpos($contents, 'broadcast') !== false);
    }

    public function testPublishContainsExpectedDataWhenEmittingBinary()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter(null, ['host' => '127.0.0.1', 'port' => '6379']);
        $binarydata = pack('CCCCC', 0, 1, 2, 3, 4);
        $emitter->emit('binary event', $binarydata);

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(strpos($contents, '\x00\x01\x02\x03\x04') !== false);
    }

    public function testPublishContainsExpectedDataWhenEmittingBinaryWithWrapper()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter(null, ['host' => '127.0.0.1', 'port' => '6379']);
        $binarydata = pack('CCCCC', 0, 1, 2, 3, 4);
        $emitter->emit('binary event', new Binary($binarydata));

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(strpos($contents, '\x00\x01\x02\x03\x04') !== false);
    }

    public function testPublishContainsNamespaceWhenEmittingWithNamespaceSet()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter(null, ['host' => '127.0.0.1', 'port' => '6379']);
        $emitter->of('/nsp')->emit('yolo', 'data');

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(strpos($contents, '/nsp') !== false);
    }

    public function testPublishKeyNameWithNamespaceSet()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter(null, ['host' => '127.0.0.1', 'port' => '6379']);
        $emitter->of('/nsp')->emit('yolo', 'data');

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(strpos($contents, 'socket.io#/nsp#') !== false);
    }

    public function testPublishKeyNameWithRoomSet()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter(null, ['host' => '127.0.0.1', 'port' => '6379']);
        $emitter->to('rm')->emit('yolo', 'data');

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(strpos($contents, 'socket.io#/#rm#') !== false);
    }

    public function testPublishKeyNameWithNamespaceAndRoomSet()
    {
        $p = new Process('redis-cli monitor > redis.log');

        sleep(1);
        // Running this should produce something that's visible in `redis-cli monitor`
        $emitter = new Emitter(null, ['host' => '127.0.0.1', 'port' => '6379']);
        $emitter->of('/nsp')->to('rm')->emit('yolo', 'data');

        $p->stop();
        $contents = file_get_contents('redis.log');
        unlink('redis.log');

        $this->assertTrue(strpos($contents, 'socket.io#/nsp#rm#') !== false);
    }
}
