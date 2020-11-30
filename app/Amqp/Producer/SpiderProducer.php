<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use App\Amqp\Lib\BaseProducer;
use App\Util\DelayQueue;
use Hyperf\Amqp\Annotation\Producer;

/**
 * @Producer()
 */
class SpiderProducer extends BaseProducer
{

    protected $exchange = 'spider';

    protected $routingKey = 'spider';

}
