<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Amqp\Lib\BaseConsumer;
use App\Util\Logger;
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
        if (is_array($data) || is_object($data)) {
            $data = json_encode($data);
        }
        Logger::get()->info($data);
        return Result::ACK;
    }
}
