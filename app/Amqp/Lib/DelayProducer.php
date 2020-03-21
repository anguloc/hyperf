<?php

declare(strict_types=1);

namespace App\Amqp\Lib;


use App\Util\DelayQueue;

abstract class DelayProducer extends BaseProducer
{

    /**
     * 延时缓存队列配置index
     *
     * @var string
     */
    protected $delayQueue;

    public function getExchange(): string
    {
        if($this->delayQueue) {
            return DelayQueue::getConfig($this->delayQueue)['delay_exchange'] ?? '';
        }else{
            return parent::getExchange();
        }
    }

}
