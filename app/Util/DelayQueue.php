<?php

namespace App\Util;

use App\Amqp\Lib\BaseProducer;
use App\Amqp\Lib\DelayProducer;
use Hyperf\Amqp\Producer;
use Hyperf\Utils\ApplicationContext;

class DelayQueue
{
    const MINUTE_TIMEOUT = 'minute_timeout';
    const TEN_MINUTE_TIMEOUT = 'ten_minute_timeout';
    const HOURS_TIMEOUT = 'hours_timeout';
    const DAY_TIMEOUT = 'day_timeout';

    const DEAD_EXCHANGE = 'dead_exchanger';

    protected static $delayConfig = [
        self::MINUTE_TIMEOUT => [
            'dead_exchange' => self::DEAD_EXCHANGE,
            'queue' => 'minute_timeout_queue',
            'timeout' => 60000,
            'delay_queue' => 'minute_timeout_cache_queue',
            'delay_exchange' => 'minute_timeout_exchange',
        ],
        self::TEN_MINUTE_TIMEOUT => [
            'dead_exchange' => self::DEAD_EXCHANGE,
            'queue' => 'ten_minute_timeout_queue',
            'timeout' => 600000,
            'delay_queue' => 'ten_minute_timeout_cache_queue',
            'delay_exchange' => 'ten_minute_timeout_exchange',
        ],
        self::HOURS_TIMEOUT => [
            'dead_exchange' => self::DEAD_EXCHANGE,
            'queue' => 'hours_timeout_queue',
            'timeout' => 3600000,
            'delay_queue' => 'hours_timeout_cache_queue',
            'delay_exchange' => 'hours_timeout_exchange',
        ],
        self::DAY_TIMEOUT => [
            'dead_exchange' => self::DEAD_EXCHANGE,
            'queue' => 'day_timeout_queue',
            'timeout' => 86400000,
            'delay_queue' => 'day_timeout_cache_queue',
            'delay_exchange' => 'day_timeout_exchange',
        ],
    ];

    public static function addTask(BaseProducer $message, int $expire): BaseProducer
    {
        if ($expire <= 0 && $message instanceof DelayProducer) {
            return $message;
        }

        list($exchange, $routing_key) = self::selectExchangeAndReduceTime($expire);
        return (new class extends DelayProducer
        {
        })->setPayload([
            'real_data' => $message->getPayload(),
            'expire' => $expire,
            'real_exchange' => $message->getExchange(),
            'real_routing_key' => $message->getRoutingKey(),
        ])->setExchange($exchange)->setRoutingKey($routing_key);
    }

    public static function loopDelay($data)
    {
        $tmp = $data;
        if (is_array($tmp) || is_object($tmp)) {
            $tmp = json_encode($tmp);
        }
        Logger::get()->info($tmp);
        if ($data['expire'] > 0) {
            list($exchange, $routing_key) = self::selectExchangeAndReduceTime($data['expire']);
            $message = (new class extends DelayProducer
            {
            })->setPayload($data)->setExchange($exchange)->setRoutingKey($routing_key);
        } else {
            $message = (new class extends BaseProducer
            {
            })->setPayload($data['real_data'] ?? [])
                ->setExchange($data['real_exchange'] ?? '')
                ->setRoutingKey($data['real_routing_key'] ?? null);
        }
        /** @var $message BaseProducer */
        ApplicationContext::getContainer()->get(Producer::class)->produce($message);
        return true;
    }

    public static function getConfig($index)
    {
        return self::$delayConfig[$index] ?? null;
    }

    private static function selectExchangeAndReduceTime(&$expire)
    {
        if ($expire / 86400 >= 1) {                                              // 倒计时超过一天
            $expire -= 86400;
            $exchange = self::getConfig(self::DAY_TIMEOUT)['delay_exchange'] ?? '';
            $routing_key = self::getConfig(self::DAY_TIMEOUT)['queue'] ?? '';
        } elseif ($expire / 3600 >= 1) {                                         // 倒计时超过一小时
            $expire -= 3600;
            $exchange = self::getConfig(self::HOURS_TIMEOUT)['delay_exchange'] ?? '';
            $routing_key = self::getConfig(self::HOURS_TIMEOUT)['queue'] ?? '';
        } elseif ($expire / 600 >= 1) {                                          // 倒计时超过10分钟
            $expire -= 600;
            $exchange = self::getConfig(self::TEN_MINUTE_TIMEOUT)['delay_exchange'] ?? '';
            $routing_key = self::getConfig(self::TEN_MINUTE_TIMEOUT)['queue'] ?? '';
        } else {
            $expire = $expire > 60 ? $expire - 60 : 0;
            $exchange = self::getConfig(self::MINUTE_TIMEOUT)['delay_exchange'] ?? '';
            $routing_key = self::getConfig(self::MINUTE_TIMEOUT)['queue'] ?? '';
        }
        return [$exchange, $routing_key];
    }


}