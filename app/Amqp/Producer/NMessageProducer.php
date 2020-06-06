<?php

declare(strict_types=1);

namespace App\Amqp\Producer;

use App\Amqp\Lib\BaseProducer;
use App\Util\DelayQueue;
use Hyperf\Amqp\Annotation\Producer;

/**
 * @Producer()
 */
class NMessageProducer extends BaseProducer
{

    protected $exchange = 'n_message';

    protected $routingKey = 'n_message';

}
