<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use App\Amqp\Lib\BaseProducer;
use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\Type;

/**
 * 用于添加死信队列exchange
 * @Producer(exchange="dead_exchanger")
 */
class DeadExchangeProducer extends BaseProducer
{
    public function __construct($data)
    {
        $this->payload = $data;
    }
}
