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
	\Hyperf\Amqp\Consumer::class => \App\Amqp\Lib\ReConsumer::class,
    \Psr\Log\LoggerInterface::class => function(){
        return \App\Util\Logger::get();
    },
];
