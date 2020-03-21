<?php

declare(strict_types=1);

namespace App\Command\Lib;

use App\Amqp\Lib\BaseConsumer;
use App\Util\Logger;
use Hyperf\Amqp\Consumer;
use Hyperf\Command\Annotation\Command;
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
    protected $workers = [];
    protected $coroutine = false;

    protected $daemon = true; // 是否守护进程

    protected $nums = 1; // 子进程数量

    protected $masterName = 'command process';


    public function handle()
    {
        try {
            if ($this->daemon) {
                Process::daemon();
            }
            $this->masterPid = getmypid();
            $this->masterData['start_time'] = time();

            $this->init();
            self::setProcessName($this->masterName . ':rabbitmq:process:master');
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
                $this->checkMpid($worker);
                // 设置子进程名称
                self::setProcessName("{$this->masterName}:child process");
                go(function () {
                    $this->runProcess();
                });
            } catch (\Throwable $e) {
                self::log("child process error - " . $e->getMessage());
            }
            $worker->exit(0);
        });

        $pid = $reserveProcess->start();
        $this->workers[$pid] = $reserveProcess;
        self::log('worker pid: ' . $pid . ' is start...');
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

    protected static function log($msg, array $context = [])
    {
        if (is_array($msg) || is_object($msg)) {
            $msg = json_encode($msg);
        }
        Logger::get()->info($msg, $context);
    }

    protected function killWorkersAndExitMaster()
    {
        if (!empty($this->workers)) {
            foreach ($this->workers as $pid => $worker) {
                if (Process::kill($pid) == true) {
                    unset($this->workers[$pid]);
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
                    self::log($this->masterName.'子进程[' . $pid . ']wait');
                    unset($this->workers[$pid]);

                    if (empty($this->workers)) {
                        $this->exitMaster();
                    }
                }
            }
        });
    }

    protected function registTimer()
    {
        go(function () {
            Timer::tick(1000, function ($timer_id) {
//                self::log('master-' . $timer_id);
            });
        });
    }

    protected function exitMaster()
    {

        exit(0);
    }

}
