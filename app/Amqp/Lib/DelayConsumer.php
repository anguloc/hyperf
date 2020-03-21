<?php

declare(strict_types=1);

namespace App\Amqp\Lib;

use App\Util\DelayQueue;
use function GuzzleHttp\Psr7\_parse_request_uri;

/**
 * producer -> delay_ex -> delay_queue -> real_ex -> real_queue -> consumer
 * 延时队列消费者
 *
 * Class DelayConsumer
 * @package App\Amqp\Lib
 */
abstract class DelayConsumer extends BaseConsumer
{
    /**
     * 延时缓存队列配置index
     *
     * @var string
     */
    protected $delayQueue;

    public function getDelayQueue(): ?string
    {
        if (!$this->delayQueue) {
            return '';
        }
        return DelayQueue::getConfig($this->delayQueue)['delay_queue'] ?? '';
    }

    public function getDelayExchange(): ?string
    {
        return DelayQueue::getConfig($this->delayQueue)['delay_exchange'] ?? '';
    }

    public function getQueueTimeout(): ?int
    {
        return (int)DelayQueue::getConfig($this->delayQueue)['timeout'] ?? 0;
    }

    public function getDeadLetterExchange(): ?string
    {
        return $this->getExchange();
    }

    public function getDeadLetterRoutingKey(): ?string
    {
        return $this->getQueue();
    }

    public function getExchange(): string
    {
        if ($this->delayQueue) {
            return DelayQueue::getConfig($this->delayQueue)['dead_exchange'] ?? '';
        } else {
            return parent::getExchange();
        }
    }

    public function getQueue(): string
    {
        if ($this->delayQueue) {
            return DelayQueue::getConfig($this->delayQueue)['queue'] ?? '';
        } else {
            return parent::getQueue();
        }
    }

    public function getRoutingKey()
    {
        if ($this->delayQueue) {
            // 延时队列消息出缓存队列入死信ex，会带上路由，这里认为规定路由==处理的队列名
            return $this->getQueue();
        } else {
            return parent::getRoutingKey();
        }
    }

}
