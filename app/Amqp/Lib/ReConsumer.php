<?php
/**
 * Created by PhpStorm.
 * User: gk
 * Date: 2020/3/17
 * Time: 22:51
 */

namespace App\Amqp\Lib;

use App\Util\Logger;
use Hyperf\Amqp\Consumer;
use Hyperf\Amqp\Message\MessageInterface;
use Hyperf\Amqp\Message\ConsumerMessageInterface;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Wire\AMQPTable;

class ReConsumer extends Consumer
{

    public function declare(MessageInterface $message, ?AMQPChannel $channel = null, bool $release = false): void
    {
        parent::declare($message, $channel);

        $this->runDelayQueue($message, $channel, $release);
    }

    /**
     * 添加延时队列
     *
     * @param MessageInterface $message
     * @param null|AMQPChannel $channel
     * @param bool $release
     * @return bool
     */
    private function runDelayQueue(MessageInterface $message, ?AMQPChannel $channel = null, bool $release = false): bool
    {
        if (!$this->validateDelayConfig($message)) {
            return false;
        }
        /** @var $message ConsumerMessageInterface|DelayConsumer */

        if (!$channel) {
            $pool = $this->getConnectionPool($message->getPoolName());
            /** @var \Hyperf\Amqp\Connection $connection */
            $connection = $pool->get();
            $channel = $connection->getChannel();
        }

        // delay exchange
        $builder = $message->getExchangeBuilder();
        $channel->exchange_declare($message->getDelayExchange(), $builder->getType(), $builder->isPassive(), $builder->isDurable(), $builder->isAutoDelete(), $builder->isInternal(), $builder->isNowait(), $builder->getArguments(), $builder->getTicket());

        // delay queue
        $builder = $message->getQueueBuilder();
        $queue_args = new AMQPTable([
            'x-message-ttl' => $message->getQueueTimeout(),
            'x-dead-letter-exchange' => $message->getDeadLetterExchange(),
            'x-dead-letter-routing-key' => $message->getDeadLetterRoutingKey()
        ]);
        $channel->queue_declare($message->getDelayQueue(), $builder->isPassive(), $builder->isDurable(), $builder->isExclusive(), $builder->isAutoDelete(), $builder->isNowait(), $queue_args, $builder->getTicket());
        $channel->queue_bind($message->getDelayQueue(), $message->getDelayExchange(), $message->getRoutingKey());

        if (isset($connection) && $release) {
            $connection->release();
        }

        return true;
    }

    /**
     *
     *
     * @param MessageInterface $message
     * @return bool
     */
    private function validateDelayConfig(MessageInterface $message)
    {
        if (
            !$message instanceof DelayConsumer ||
            !$message instanceof ConsumerMessageInterface
        ) {
            return false;
        }

        if (
            !$message->getDelayQueue() ||
            !$message->getDelayExchange() ||
            $message->getQueueTimeout() <= 0 ||
            !$message->getDeadLetterExchange() ||
            !$message->getDeadLetterRoutingKey()
        ) {
            Logger::get()->error('延时队列出错', ['class' => get_class($message)]);
            return false;
        }

        return true;
    }
}