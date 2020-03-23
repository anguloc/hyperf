<?php

declare(strict_types=1);

namespace App\Command;

use App\Amqp\Producer\MinuteProducer;
use App\Command\Lib\BaseCommand;
use App\Command\Lib\ProcessCommand;
use App\Util\DelayQueue;
use App\Util\Logger;
use Hyperf\Amqp\Producer;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;
use Swoole\Process;
use Swoole\Timer;
use xingwenge\canal_php\CanalConnectorFactory;
use xingwenge\canal_php\CanalClient;
use xingwenge\canal_php\Fmt;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Psr\Log\LoggerInterface;

/**
 * @Command
 */
class TestCommand extends ProcessCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;


    protected $masterPid;
    protected $masterData = [];
    protected $workers = [];
    protected $coroutine = false;

    protected static $daemon = true;

    const BASE_URL = 'http://www.sis001.com/forum/';

    protected $nums = 1;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
        $this->setName("test");
    }

    public function configure()
    {
        $this->setDescription('test');
    }

    public function handle3()
    {
        Process::daemon();
        $this->masterPid = getmypid();
        $this->init();
        self::setProcessName("{$this->masterName}:test:master");

        $pool = new \Swoole\Process\Pool(1);

        set_exception_handler(function(){
            echo 'ex';
            print_r(func_get_args());
        });
        set_error_handler(function(){
            echo 'er';
            print_r(func_get_args());
        });

        $pool->on('WorkerStart', function(\Swoole\Process\Pool $pool, $worker_id){
            self::setProcessName("{$this->masterName}:test:worker {$worker_id}#");
            while(1){
                sleep(3);
            }
        });

        $pool->on('WorkerStop', function(\Swoole\Process\Pool $pool, $worker_id){
            echo "worker id {$worker_id} stop\r";
        });

        $pool->start();

    }

    public function handle2()
    {
        Process::daemon();
        $this->masterPid = getmypid();
        $this->init();
        self::setProcessName("{$this->masterName}:test:master");

        Process::signal(SIGTERM, function ($sig) {
            $this->killWorkersAndExitMaster();
        });
        Process::signal(SIGKILL, function ($sig) {
            $this->killWorkersAndExitMaster();
        });
        Process::signal(SIGCHLD, function ($sig) {
            while (true) {
                $ret = Process::wait(false);
                if ($ret) {
                    $pid = $ret['pid'];
//                    Process::kill($this->masterPid, SIGTERM);
                    self::log($this->masterName . '子进程[' . $pid . ']wait');
                    $worker = $this->workers[$pid];
                    $this->clearWorkerData($worker);
                    if ($this->isRestartWorker($worker)) {
                        $new_pid = $worker->start();
//                        $new_pid = $this->createChildProcess();
                        if (isset($new_pid) && $new_pid > 0) {
                            // 为了循环重启做准备
                            $this->workers[$new_pid] = $worker;
                        } else {
                            self::log($this->masterName . "原子进程[{$pid}]，重启失败");
                        }
                    }

                    if (empty($this->workers) && false) {
                        $this->exitMaster();
                    }
                    self::log("新pid为{$new_pid},当前进程".getmypid()."");
                }
            }
        });

        $process = new Process(function(Process $worker){
            self::setProcessName("{$this->masterName}:test:worker");
            go(function(){
                Timer::tick(1000, function(){

                });
            });
            while(1){};
        });
        $process->start();
        $this->workers[$process->pid] = $process;

        go(function(){
            Timer::tick(3000, function(){

            });
        });
    }

    public function handle1()
    {

//        $rt = Db::select("show tables;");
//        print_r($rt);
//
//        return '';




//        $this->container->get(Producer::class)->produce($message);


        MinuteProducer::addMessage([1]);

        return '';

        $this->container->get(LoggerInterface::class);
        Logger::get();

        echo 123123;
        echo "\n";
        die;

        $a = '';
        try {
            $client = make(ClientFactory::class)->create([
                'base_uri' => self::BASE_URL,
            ]);
            $res = $client->get('http://www.sis001.com/forum/forum-279-1.html');
//            $res = $client->get('http://www.gkfk5.cn');
            $a = $res->getBody()->getContents();
        }catch (\Exception $e){
            $a = $e->getMessage();
        }



        $b = file_put_contents(BASE_PATH . '/runtime/logs/log.log', $a);
        var_dump($b);



        echo PHP_EOL;
        return '';

        echo 1;

        return '';
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

        Process::signal(SIGTERM, function($sig){

        });
        Process::signal(SIGKILL, function($sig){

        });
        Process::signal(SIGCHLD, function($sig){
            while($ret = Process::wait(false)){
                Process::kill($this->masterPid, SIGTERM);
            }
        });


        $this->createChildProcess();


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

    protected function init()
    {
        $this->masterName = 'test:process';
    }

    public function runProcess()
    {
        self::log('worker start');
        Timer::tick(10000, function(){
//            $redis = $this->container->get(Redis::class);

//            $b = $redis->exists('aaa');

//            \Redis::


            self::log("aaaaaa{1}");
        });

    }

    protected static function getMemoryUsage() {
        // 类型是MB,获取时需要手动加上
        return round(memory_get_usage() / (1024 * 1024), 2);
    }
}
