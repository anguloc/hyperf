<?php

declare(strict_types=1);

namespace App\Amqp\Lib;

use App\Util\DelayQueue;
use App\Util\Logger;
use Hyperf\Amqp\Message\ProducerMessage;
use Hyperf\Amqp\Message\Type;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Amqp\Producer;
use Hyperf\Amqp\Annotation\Producer as AnnotationProducer;

abstract class BaseProducer extends ProducerMessage
{
    protected $type = Type::DIRECT; // exchange 用 direct

    public static function addMessage($message = [], int $expire = 0): bool
    {
        try {
            if (!$message instanceof BaseProducer) {
                if (!is_array($message) && !is_object($message)) {
                    return false;
                }
                $producerAnnotation = AnnotationCollector::getClassAnnotation(static::class, AnnotationProducer::class);
                if (!$producerAnnotation instanceof AnnotationProducer) {
                    return false;
                }
                $message = (new static())->setPayload($message);
                if ($producerAnnotation->exchange) {
                    $message->setExchange($producerAnnotation->exchange);
                }
                if ($producerAnnotation->routingKey) {
                    $message->setRoutingKey($producerAnnotation->routingKey);
                }
            }
            if ($expire > 0) {
                // 延时队列
                $message = DelayQueue::addTask($message, $expire);
            }

            return ApplicationContext::getContainer()->get(Producer::class)->produce($message, true);
        } catch (\Throwable $e) {
            Logger::get()->error('rabbitMQ error', [
                'class' => get_class($e),
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
            ]);
            return false;
        }
    }

    public function getPayload()
    {
        return $this->payload;
    }

    public function send($message = [])
    {
        empty($message) && $message instanceof BaseProducer && $message = $message->getPayload();
        return self::addMessage($message);
    }
}
