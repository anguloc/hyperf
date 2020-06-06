<?php

namespace App\WebSocket\Lib;

use Swoole\Coroutine\Channel;

/**
 * 用于处理open  close顺序问题
 *
 * Class Coordinator
 * @package App\WebSocket\Lib
 */
class Coordinator
{
    /**
     * @var array
     */
    protected static $container = [];

    public static function init($fd)
    {
        self::$container[$fd] = new Channel(1);
    }

    public static function sleep($fd,$timeout = 2): bool
    {
        if (!isset(self::$container[$fd])) {
            return false;
        }

        if (!self::$container[$fd] instanceof Channel) {
            self::clear($fd);
            return false;
        }

        $chan = self::$container[$fd];
        $chan->pop($timeout);
        return $chan->errCode === SWOOLE_CHANNEL_CLOSED;
    }

    public static function clear($fd)
    {
        if (isset(self::$container[$fd]) && self::$container[$fd] instanceof Channel) {
            self::$container[$fd]->close();
        }
        unset(self::$container[$fd]);
    }

    public static function all()
    {
        return self::$container;
    }
}
