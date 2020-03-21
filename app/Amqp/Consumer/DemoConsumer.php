<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Amqp\Lib\BaseConsumer;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;

/**
 * @Consumer(exchange="hyperf", queue="hyperf", routingKey="hyperf", nums=1,enable=false)
 */
class DemoConsumer extends BaseConsumer
{
    public function consume($data): string
    {
        print_r(date('Y-m-d H:i:s'));
        print_r($data);
        echo PHP_EOL;
        return Result::ACK;
    }
}
