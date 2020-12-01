<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
return [
    'default' => [
        'host' => 'www.gkfk5.cn',
        'port' => defined('PORT_3') ? PORT_3 : 5672,
        'user' => defined('USER_1') ? USER_1 : 'root',
        'password' => defined('PWD_1') ? PWD_1 : 'root',
        'vhost' => '/',
        'pool' => [
            'min_connections' => 1,
            'max_connections' => 10,
            'connect_timeout' => 10.0,
            'wait_timeout' => 3.0,
            'heartbeat' => -1,
        ],
        'params' => [
            'insist' => false,
            'login_method' => 'AMQPLAIN',
            'login_response' => null,
            'locale' => 'en_US',
            'connection_timeout' => 6.0,
            'read_write_timeout' => 16.0,
            'context' => null,
            'keepalive' => false,
            'heartbeat' => 8,
        ],
    ],
];
