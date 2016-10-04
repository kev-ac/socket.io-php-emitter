<?php

namespace SocketIO;

class Emitter
{
    const EVENT = 2;
    const BINARY_EVENT = 5;

    private $uid = 'emitter';

    public function __construct($redis = false, $opts = [])
    {
        if (is_array($redis)) {
            $opts = $redis;
            $redis = false;
        }

        // Apply default arguments
        $opts = array_merge(['host' => 'localhost', 'port' => 6379], $opts);

        if (!$redis) {
            // Default to phpredis
            if (extension_loaded('redis')) {
                if (!isset($opts['socket']) && !isset($opts['host'])) {
                    throw new \Exception('Host should be provided when not providing a redis instance');
                }
                if (!isset($opts['socket']) && !isset($opts['port'])) {
                    throw new \Exception('Port should be provided when not providing a redis instance');
                }

                $redis = new \Redis();
                if (isset($opts['socket'])) {
                    $redis->connect($opts['socket']);
                } else {
                    $redis->connect($opts['host'], $opts['port']);
                }
            } else {
                $redis = new \TinyRedisClient($opts['host'] . ':' . $opts['port']);
            }
        }

        if (!is_callable([$redis, 'publish'])) {
            throw new \Exception('The Redis client provided is invalid. The client needs to implement the publish method. Try using the default client.');
        }

        $this->redis = $redis;
        $this->prefix = isset($opts['key']) ? $opts['key'] : 'socket.io';

        $this->_rooms = [];
        $this->_flags = [];
    }

    /*
     * Flags
     */

    public function __get($flag)
    {
        $this->_flags[$flag] = true;
        return $this;
    }

    private function readFlag($flag)
    {
        return isset($this->_flags[$flag]) ? $this->_flags[$flag] : false;
    }

    /*
     * Broadcasting
     */

    public function in($room)
    {
        if (!in_array($room, $this->_rooms)) {
            $this->_rooms[] = $room;
        }

        return $this;
    }

    // Alias for in
    public function to($room)
    {
        return $this->in($room);
    }

    /*
     * Namespace
     */

    public function of($nsp)
    {
        $this->_flags['nsp'] = $nsp;
        return $this;
    }

    /*
     * Emitting
     */

    public function emit()
    {
        $args = func_get_args();
        $packet = [];

        $packet['type'] = self::EVENT;
        // handle binary wrapper args
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if ($arg instanceof Binary) {
                $args[$i] = strval($arg);
                $this->binary;
            }
        }

        if ($this->readFlag('binary')) {
            $packet['type'] = self::BINARY_EVENT;
        }

        $packet['data'] = $args;

        // set namespace
        if (isset($this->_flags['nsp'])) {
            $packet['nsp'] = $this->_flags['nsp'];
            unset($this->_flags['nsp']);
        } else {
            $packet['nsp'] = '/';
        }

        $opts = [
            'rooms' => $this->_rooms,
            'flags' => $this->_flags
        ];
        $chn = $this->prefix . '#' . $packet['nsp'] . '#';
        $packed = msgpack_pack([$this->uid, $packet, $opts]);

        // hack buffer extensions for msgpack with binary
        if ($packet['type'] == self::BINARY_EVENT) {
            $packed = str_replace(pack('c', 0xda), pack('c', 0xd8), $packed);
            $packed = str_replace(pack('c', 0xdb), pack('c', 0xd9), $packed);
        }

        // publish
        if (is_array($this->_rooms) && count($this->_rooms) > 0) {
            foreach ($this->_rooms as $room) {
                $chnRoom = $chn . $room . '#';
                $this->redis->publish($chnRoom, $packed);
            }
        } else {
            $this->redis->publish($chn, $packed);
        }

        // reset state
        $this->_rooms = [];
        $this->_flags = [];

        return $this;
    }
}
