<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;


class DemoConsumer extends ConsumerMessage
{
    public function consume($data): string
    {
        return Result::NACK;
    }
}
