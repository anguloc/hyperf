<?php

namespace App\Spider\Lib;


use App\Util\Logger;
use DHelper\Process\DMProcess;

abstract class AbstractProcessSpider implements Spider
{
    protected static $processes = [
//        'name' => '',
//        'callback' => '',
//        'num' => 1,
//        'options' => [
//            'process_name' => 'children name',
//        ],
    ];

    /**
     * @var DMProcess
     */
    protected $pm;

    public function run()
    {
        try {
            $this->pm = new DMProcess();

            foreach (static::$processes as $index => $process) {
                if (!$valid = self::getProcessConfig($process)) {
                    continue;
                }
                list($name, $callback, $num, $options) = $valid;
                $this->pm->register($name, $callback, $num, $options);
            }

            $this->pm->start();
        }catch (\Throwable $e){
            self::log("spider error: code:{$e->getCode()},message:{$e->getMessage()},file:{$e->getFile()},line:{$e->getLine()},exception:" . get_class($e));
        }
    }

    protected function getProcessConfig($process)
    {
        if(!is_string($process['name']) || empty($process['name'])){
            return false;
        }

        $callback = $process['callback'];
        if (!is_callable($callback)) {
            if (!is_string($callback) || !is_callable([$this, $callback])) {
                return false;
            }
            $callback = [$this, $callback];
        }

        if (!isset($process['num'])) {
            $process['num'] = 1;
        }
        if(!is_int($process['num']) || $process['num'] <= 0){
            return false;
        }

        if (!isset($process['options'])) {
            $process['options'] = [];
        }
        if(!is_array($process['options'])){
            return false;
        }
        return [
            $process['name'],
            $callback,
            $process['num'],
            $process['options'],
        ];
    }


    protected static function log($message, $context = [])
    {
        Logger::get(SPIDER_LOG, SPIDER_LOG)->info($message, $context);
    }
}