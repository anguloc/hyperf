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

/**
 * 这个是手动实现进程管理
 * 进程重启那块有问题 没解决  先拿pool处理   后续在研究
 *
 * Class ProcessCommand
 * @package App\Command\Lib
 */
abstract class ProcessCommandBak extends BaseCommand
{

    protected $masterPid;
    protected $masterData = [];
    protected $workers = [];
    protected $workersData = [];
    protected $coroutine = false;

    protected static $daemon = true; // 是否守护进程

    protected $nums = 1; // 子进程数量

    protected $masterName = 'command process';

    /**
     * @var Process
     */
    protected $process;

    /**
     * @var int
     */
    protected $recvLength = 65535;

    /**
     * @var float
     */
    protected $recvTimeout = 10.0;

    const MASTER_PROCESS_RUNNING_STATUS = 1;
    const MASTER_PROCESS_STOP_STATUS = 2;
    const PROCESS_RUNNING_STATUS = 1;
    const PROCESS_STOP_STATUS = 2;


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
            for ($i = 0; $i < $nums; $i++) {
                $this->createChildProcess();
            }

            $this->registSignal();
            $this->registTimer();
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

    protected function createChildProcess()
    {
        $reserveProcess = new \Swoole\Process(function (\Swoole\Process $worker) {
            //$beginTime=microtime(true);
            try {
                $this->workers = [];
                $this->checkMpid($worker);
                // 设置子进程名称
                self::setProcessName("{$this->masterName}:process:worker");
                $this->process = $worker;
//                $this->processMessage($worker);
                $this->runProcess();

//                go(function () use ($worker) {
//                    Timer::tick(4000, function () use ($worker) {
//                        $sock = $worker->exportSocket();
//                        self::log('send');
//                        self::log("Worker[{$worker->pid}]进程向Master发送消息");
//                        $sock->send("Hello Master");
//                    });
//                });


            } catch (\Throwable $e) {
                self::log("worker process error - " . $e->getMessage());
            } finally {
//                Timer::clearAll();
            }
        }, false, SOCK_DGRAM, true);


        $pid = $reserveProcess->start();
        if ($pid <= 0) {
            return $pid;
        }
//        $this->masterProcessMessage($reserveProcess);
        $this->workers[$pid] = $reserveProcess;
        self::log('worker pid: ' . $pid . ' is start...');
        return $pid;
    }

    //主进程如果不存在了，子进程退出
    protected function checkMpid(&$worker)
    {
        if (!@Process::kill($this->masterPid, 0)) {
            /** @var $worker Process */
            $worker->exit();
            self::log("Master process exited, I [{$worker['pid']}] also quit");
        }
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

    protected function killWorkersAndExitMaster()
    {
        if (!empty($this->workers)) {
            foreach ($this->workers as $pid => $worker) {
                if (Process::kill($pid) == true) {
                    $this->clearWorkerData($worker);
                    self::log('子进程[' . $pid . ']收到强制退出信号,退出成功');
                } else {
                    self::log('子进程[' . $pid . ']收到强制退出信号,但退出失败');
                }

                self::log('Worker count: ' . count($this->workers));
            }
        }
        $this->exitMaster();
    }

    protected function registSignal()
    {
        Process::signal(SIGUSR1, function ($sig) {
            self::log('Master-USR1');
        });
        Process::signal(SIGUSR2, function ($sig) {
            self::log('Master-USR2');
        });

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
    }

    protected function registTimer()
    {
        go(function () {
            Timer::tick(3000, function ($timer_id) {
//                self::log('master-' . $timer_id);
                if (is_object($this->process)) {
                    print_r(get_class($this->process));
                }
//                print_r("worker count:" . count($this->workers));
//                echo PHP_EOL;

                /** @var $worker Process */
                foreach ($this->workers as $worker) {
//                    go(function()use($worker){
//                        $sock = $worker->exportSocket();
//                        $data = $sock->recv();
//                        self::log('zxcxzcz'.$data);
//                    });
//                    go(function()use($worker){
//                    $sock = $worker->exportSocket();
//                    self::log("Master向Worker[{$worker->pid}]进程发送消息");
//                    $sock->send("Hello worker socket");
//                    });

                }


            });
        });
    }

    protected function processMessage(Process $process)
    {
        go(function () use ($process) {
            while (true) {
                try {
                    $sock = $process->exportSocket();
                    $recv = $sock->recv();
                    if ($recv === '') {
                        self::log('Socket is closed 2#:' . $sock->errCode);
                    }

                    if ($recv === false && $sock->errCode !== SOCKET_ETIMEDOUT) {
                        self::log('Socket is closed 2#:' . $sock->errCode);
                    }
                    self::log("Worker[{$process->pid}]收到Master进程消息:{$recv}");
                } catch (\Throwable $e) {
                    self::log('Socket is error 2#:' . $e->getMessage());
                }
            }
        });

    }

    protected function masterProcessMessage($worker)
    {
        /** @var $worker Process */
        go(function () use ($worker) {
            while (true) {
                try {
                    $sock = $worker->exportSocket();
                    $recv = $sock->recv();
                    if ($recv === '') {
                        self::log('Socket is closed 1#:' . $sock->errCode);
                    }

                    if ($recv === false && $sock->errCode !== SOCKET_ETIMEDOUT) {
                        self::log('Socket is closed 1#:' . $sock->errCode);
                    }

                    self::log("Master收到Worker[{$worker->pid}]进程消息:{$recv}");
                } catch (\Throwable $e) {
                    self::log('Socket is error 1#:' . $e->getMessage());
                }
            }
        });
    }

    protected function isRestartWorker(Process $worker)
    {
        return true;
    }

    protected function clearWorkerData(Process $worker)
    {
        unset($this->workers[$worker->pid]);
    }

    protected function exitMaster()
    {

        exit(0);
    }

}
