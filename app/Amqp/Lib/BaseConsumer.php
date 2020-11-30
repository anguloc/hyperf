<?php

declare(strict_types=1);

namespace App\Amqp\Lib;

use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\Amqp\Message\Type;

abstract class BaseConsumer extends ConsumerMessage
{
    protected $qos = [
        'prefetch_size' => null,
        'prefetch_count' => 1, // 一次只拿一条数据
        'global' => null,
    ];

    protected $type = Type::DIRECT; // exchange 用 direct

    protected $coroutine = false;

    protected $nums = 1;


    public function isCoroutine()
    {
        return $this->coroutine;
    }

    public function getWorkerNum()
    {
        return $this->nums;
    }
}
