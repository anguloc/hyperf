<?php

declare(strict_types=1);

namespace App\Command;

use App\Amqp\Lib\BaseConsumer;
use App\Command\Lib\ProcessCommand;
use Hyperf\Amqp\Consumer;
use Hyperf\Command\Annotation\Command;
use Hyperf\Di\Annotation\AnnotationCollector;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;
use Hyperf\Amqp\Annotation\Consumer as AnnotationConsumer;

/**
 * @Command
 */
class RabbitMQCommand extends ProcessCommand
{

    /**
     * @var ContainerInterface
     */
    protected $container;
    protected $coroutine = false;

    protected $masterName = 'rabbitMQ';
    protected $baseConsumerPath = 'App\Amqp\Consumer\\';

    protected $consumerAnnotation;

    /**
     * @var BaseConsumer
     */
    protected $consumer;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
        $this->setName('process:rabbit');
    }

    public function configure()
    {
        $this->setDescription('rabbit自定义进程');
        $this->addArgument('rabbit', InputArgument::REQUIRED, 'rabbit consumer类名');
    }

    /**
     * @throws \Exception
     */
    protected function init()
    {
        $rabbit = $this->input->getArgument('rabbit');
        $this->masterName = $rabbit . ':custom_rabbit';
        if (!class_exists($rabbit)) {
            $rabbit = $this->baseConsumerPath . $rabbit;
            if (!class_exists($rabbit)) {
                throw new \Exception('错误的consumer');
            }
        }

        if (!$this->consumerAnnotation = AnnotationCollector::getClassAnnotation($rabbit, AnnotationConsumer::class)) {
            throw new \Exception('错误的consumer!');
        }

        $this->consumer = new $rabbit();
        if (!$this->consumer instanceof BaseConsumer) {
            throw new \Exception('consumer必须继承BaseConsumer', 0);
        }
        if ($this->consumerAnnotation->exchange) {
            $this->consumer->setExchange($this->consumerAnnotation->exchange);
        }
        if ($this->consumerAnnotation->routingKey) {
            $this->consumer->setRoutingKey($this->consumerAnnotation->routingKey);
        }
        if ($this->consumerAnnotation->queue) {
            $this->consumer->setQueue($this->consumerAnnotation->queue);
        }
        $this->nums = $this->consumerAnnotation->nums ?: 1;
    }

    protected function runProcess()
    {
        if ($this->consumer->isCoroutine()) {
            $this->container->get(Consumer::class)->consume($this->consumer);
        }else{
            go(function(){
                $this->container->get(Consumer::class)->consume($this->consumer);
            });
        }

    }


}
