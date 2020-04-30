<?php

declare(strict_types=1);

namespace App\Command\Lib;

use App\Util\Logger;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Utils\ApplicationContext;
use Swoole\Process;


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

    protected $workerId;


    public function handle()
    {
        try {
            $this->init();
            if (static::$daemon) {
                Process::daemon();
            }
            $this->masterPid = getmypid();
            $this->masterData['start_time'] = time();

            self::setProcessName($this->masterName . ':process:master');
            $nums = $this->getNumbers();

            $this->pool = new \Swoole\Process\Pool($nums, SWOOLE_IPC_UNIXSOCK, 0, true);
            $this->pool->on('WorkerStart', function (\Swoole\Process\Pool $pool, $worker_id) {
                self::setProcessName("{$this->masterName}:process:worker");
                $process = $pool->getProcess($worker_id);
                $this->workerId = $worker_id;
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
    abstract protected function runProcess();

    protected static function setProcessName($name)
    {
        if (PHP_OS != 'Darwin' && function_exists('swoole_set_process_name')) {
            swoole_set_process_name($name);
        }
    }

    protected function getNumbers(): int
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
