<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Command\Annotation\Command;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Psr\Container\ContainerInterface;
use Swoole\Process;
use xingwenge\canal_php\CanalConnectorFactory;
use xingwenge\canal_php\CanalClient;
use xingwenge\canal_php\Fmt;

/**
 * @Command
 */
class TestCommand extends HyperfCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var \Hyperf\Logger\Logger
     */
    protected static $logger;

    protected $masterPid;
    protected $masterData = [];

    public function __construct(ContainerInterface $container, LoggerFactory $logger)
    {
        $this->container = $container;
        parent::__construct();
        $this->setName("test");

        // log
        self::$logger = $logger->get('hy-process');
    }

    public function configure()
    {
        $this->setDescription('test');
    }

    public function handle()
    {
        Process::daemon();
        self::setProcessName("Hy-Process-Master");

        $this->masterPid = getmypid();
        $this->masterData['start_time'] = time();

        Process::signal(SIGUSR1, function ($sig) {
            self::log('Master-USR1');
        });
        Process::signal(SIGUSR2, function ($sig) {
            self::log('Master-USR2');
        });





        return '';
        try {
            $client = CanalConnectorFactory::createClient(CanalClient::TYPE_SOCKET_CLUE);
            # $client = CanalConnectorFactory::createClient(CanalClient::TYPE_SWOOLE);

            $client->connect('www.gkfk5.cn', 11111);
            $client->checkValid();
            $client->subscribe("1001", "example", ".*\\..*");
            # $client->subscribe("1001", "example", "db_name.tb_name"); # 设置过滤

            while (true) {
                $message = $client->get(100);
                if ($entries = $message->getEntries()) {
                    foreach ($entries as $entry) {
                        Fmt::println($entry);
                    }
                }
                sleep(1);
            }

            $client->disConnect();
        } catch (\Exception $e) {
            echo $e->getMessage(), PHP_EOL;
        }
//        $this->line('Hello Hyperf!', 'info');
    }

    protected static function setProcessName($name)
    {
        if (PHP_OS != 'Darwin' && function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        }
    }

    protected static function log($msg, array $context)
    {
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg);
        }
        self::$logger->info($msg, $context);
    }
}
