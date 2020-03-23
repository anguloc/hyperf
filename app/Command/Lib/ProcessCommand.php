<?php

declare(strict_types=1);

namespace App\Command\Lib;

use App\Amqp\Lib\BaseConsumer;
use App\Util\Logger;
use Co\Channel;
use Hyperf\Amqp\Consumer;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Process\AbstractProcess;
use Hyperf\Utils\ApplicationContext;
use Psr\Container\ContainerInterface;
use Swoole\Process;
use Swoole\Timer;
use Symfony\Component\Console\Input\InputArgument;
use xingwenge\canal_php\CanalConnectorFactory;
use xingwenge\canal_php\CanalClient;
use xingwenge\canal_php\Fmt;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Hyperf\Amqp\Annotation\Consumer as AnnotationConsumer;


abstract class ProcessCommand extends BaseCommand
{

    protected $masterPid;
    protected $masterData = [];
    protected $coroutine = false;

    protected static $daemon = true; // 是否守护进程

    protected $nums = 1; // 子进程数量

    protected $masterName = 'command process';

    /**
     * @var \Swoole\Process\Pool
     */
    protected $pool;


    public function handle()
    {
        try {
            if (static::$daemon) {
                Process::daemon();
            }
            $this->masterPid = getmypid();
            $this->masterData['start_time'] = time();

            $this->init();
            self::setProcessName($this->masterName . ':process:master');
            $nums = $this->getNumbers();

            $this->pool = new \Swoole\Process\Pool($nums, SWOOLE_IPC_UNIXSOCK, 0, true);
            $this->pool->on('WorkerStart', function (\Swoole\Process\Pool $pool, $worker_id) {
                self::setProcessName("{$this->masterName}:process:worker");
                $process = $pool->getProcess($worker_id);
                $pid = 0;
                if ($process instanceof Process) {
                    $pid = $process->pid;
                }
                self::log('Worker:id:' . $worker_id . ',pid: ' . $pid . ' is start...');
                $this->runProcess();
            });
            $this->pool->on('WorkerStop', function (\Swoole\Process\Pool $pool, $worker_id) {
                self::log("worker [{$worker_id}#] stop");
            });

            $this->pool->start();
            return true;
        } catch (\Throwable $e) {
            $this->warn("进程启动失败：{$e->getMessage()}");
            return false;
        }
    }

    /**
     * 初始化
     */
    protected function init()
    {

    }

    /**
     * 子进程
     *
     * @throws \Exception
     */
    protected function runProcess()
    {
        throw new \Exception("需要实现runProcess方法");
    }

    protected static function setProcessName($name)
    {
        if (PHP_OS != 'Darwin' && function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        }
    }

    protected function getNumbers()
    {
        return $this->nums > 0 ? $this->nums : 1;
    }

    protected static function log($msg, array $context = [], $log = false)
    {
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg);
        }
        if (static::$daemon || $log === true) {
            Logger::get()->info($msg, $context);
        } else {
            ApplicationContext::getContainer()->get(StdoutLoggerInterface::class)->info($msg, $context);
        }
    }

}
