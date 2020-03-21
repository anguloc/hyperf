<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use App\Amqp\Lib\BaseProducer;
use Hyperf\Amqp\Annotation\Producer;

/**
 * @Producer(exchange="hyperf", routingKey="hyperf")
 */
class DemoProducer extends BaseProducer
{
    public function __construct()
    {
    }
}
