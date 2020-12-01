<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use App\Amqp\Lib\BaseProducer;
use Hyperf\Amqp\Annotation\Producer;
use Hyperf\Amqp\Message\Type;

/**
 * @Producer(exchange="hyperf", routingKey="hyperf")
 */
class DemoProducer extends BaseProducer
{
    protected $type = Type::TOPIC; // exchange 用 direct
    public function __construct()
    {
    }
}
