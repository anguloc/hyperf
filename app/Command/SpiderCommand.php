<?php

declare(strict_types=1);

namespace App\Command;

use App\Command\Lib\BaseCommand;
use App\Spider\Lib\Spider;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Input\InputArgument;

/**
 * @Command
 */
class SpiderCommand extends BaseCommand
{

    /**
     * @var ContainerInterface
     */
    protected $container;
    protected $coroutine = false;

    protected $masterName = 'spider';
    protected $baseConsumerPath = 'App\Spider\\';

    /**
     * @var Spider
     */
    protected $consumer;


    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
        $this->setName('process:spider');
    }

    public function configure()
    {
        $this->setDescription('spider自定义进程');
        $this->addArgument('spider', InputArgument::REQUIRED, 'spider 类名');
    }

    public function handle()
    {
        try {
            $this->init();
            $this->consumer->run();
        } catch (\Exception $e) {
            $this->warn("启动失败：{$e->getMessage()}");
        }
    }

    /**
     * @throws \Exception
     */
    protected function init()
    {
        $spider = $this->input->getArgument('spider');
        $this->masterName = $spider . ':custom_spider';

        if (!class_exists($spider)) {
            $spider = $this->baseConsumerPath . $spider;
            if (!class_exists($spider)) {
                throw new \Exception('错误的consumer');
            }
        }

        $this->consumer = new $spider();
        if (!$this->consumer instanceof Spider) {
            throw new \Exception('错误的consumer!');
        }

    }


}
