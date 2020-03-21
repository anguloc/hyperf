<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use App\Amqp\Lib\DelayProducer;
use App\Util\DelayQueue;
use Hyperf\Amqp\Annotation\Producer;

/**
 *
 */
class MinuteProducer extends DelayProducer
{

    protected $delayQueue = DelayQueue::MINUTE_TIMEOUT;

    public function __construct($data)
    {
        $this->payload = $data;
    }
}
