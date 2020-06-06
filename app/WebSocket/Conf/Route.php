<?php

namespace App\WebSocket\Conf;

use App\WebSocket\Controller\Admin;
use App\WebSocket\Controller\Common;
use App\WebSocket\Controller\Home;
use App\WebSocket\Exception\LogicException;

class Route
{
    /**
     * WebSocket路由
     * @var array
     */
    protected static $map = [
        self::INIT => Home::class,
        self::ADMIN_SEND => [Admin::class, 'sendToUid'],
        self::PING => [Common::class, 'ping'],

    ];

    const INIT = 1;
    const LOGIN = 2;
    const PING = 1001;
    const ADMIN_SEND = 9001;

    public static function get($opcode)
    {
        if (!isset(self::$map[$opcode])) {
            throw new LogicException();
        }

        if (is_array(self::$map[$opcode]) && !is_callable(self::$map[$opcode])) {
            throw new LogicException();
        }

        return self::$map[$opcode];
    }
}
