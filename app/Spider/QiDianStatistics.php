<?php

namespace App\Spider;


use App\Model\SpidersNovel;
use App\Model\SpidersNovelOption;
use App\Spider\Lib\AbstractProcessSpider;
use App\Spider\Lib\QiDianToken;
use App\Spider\Lib\Spider;
use DHelper\Process\DMProcess;
use QL\QueryList;
use League\Flysystem\Filesystem;
use League\Flysystem\Adapter\Local;
use Swoole\Coroutine\Channel;
use Swoole\Timer;
use Symfony\Component\Finder\Finder;

/**
 * php bin/hyperf.php process:spider QiDianStatistics
 * Class QiDianStatistics
 * @package App\Spider
 */
class QiDianStatistics extends AbstractProcessSpider implements Spider
{
    protected $targetUrl = 'https://book.qidian.com/info/1010868264#Catalog';

    protected $initTime;

    protected static $tempDir = BASE_PATH . '/runtime/temp/qidian/';

    const BASE_STEP = 'base'; // 基础 查全部 拉取分类 写全部的html文件
    const CATE_STEP = 'cate'; // 获取所有的一级分类 写html
    const SUB_CATE_STEP = 'subCate'; // 获取所有的二级分类 写html
    const BOOK_STEP = 'book'; // 解析html
    const DB_STEP = 'db'; // db

    /**
     * @var Channel
     */
    protected $queue;

    /**
     * @var Finder
     */
    protected $finder;

    /**
     * @var Filesystem
     */
    protected $fs;

    /**
     * @var QueryList
     */
    protected $ql;

    /**
     * @var \GuzzleHttp\Client
     */
    protected $client;

    // process
    const SPIDER = 'spider';
    const PARSE = 'parse';

    protected $data = [];

    protected static $processes = [
        [
            'name' => self::PARSE,
            'callback' => 'parse',/** @see QiDianStatistics::parse() */
        ],
        [
            'name' => self::SPIDER,
            'callback' => 'spider', /** @see QiDianStatistics::spider() */
            'num' => 2,
        ],

    ];

    public function run()
    {
        try {
            // temp dir
            $this->fs = new Filesystem(new Local(self::$tempDir), ['disable_asserts' => true]);
            $this->finder = new Finder();

            // ql
            $this->ql = new QueryList();

            // guzzle
            $this->client = guzzle([
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36'
                ],
            ]);

            // 1361

            $files = $this->finder->files()->in([
                self::$tempDir . 'file',
            ])->name("*.html")->count();

            echo $files;
            die;

            $a = $this->fs->read("index.cache");
            $a = json_decode($a, true);
            echo count($a);
            die;


            parent::run();
        } catch (\Throwable $e) {
            self::log("spider error: code:{$e->getCode()},message:{$e->getMessage()},file:{$e->getFile()},line:{$e->getLine()},exception:" . get_class($e));
        }
    }

    public function spider()
    {
        $this->common(self::SPIDER);

        Timer::tick(2000, function () {
            if ($this->queue->isEmpty()) {
                return;
            }
            $data = $this->queue->pop();
            if (!$url = $data['url'] ?? false) {
                return;
            }
            $resp = $this->client->get($url);
            if ($resp->getStatusCode() != 200) {
                $content = '';
                if ($resp->getBody()) {
                    $content = $resp->getBody()->getContents();
                }
                self::log("http error,url:{$url},content:{$content}");
                return;
            }
            $filename = $data['filename'] ?? 'tmp.log';
            $html = $resp->getBody()->getContents();
            if (!$this->fs->put($filename, $html)) {
                self::log("file write error,url:{$url}");
                return;
            }
            $this->pm->sendTo(self::PARSE, json_encode($data));
        });
    }

    public function parse()
    {
        $this->common(self::PARSE);
        Timer::tick(1000, function () {
            if ($this->queue->isEmpty()) {
                return;
            }

            $data = $this->queue->pop();
            if (!$step = $data['step'] ?? null) {
                return;
            }
            if (!is_callable([$this, $data['step']])) {
                return;
            }
            call_user_func([$this, $data['step']], $data);
        });

        go(function () {
            $this->pm->sendTo(self::SPIDER, json_encode([
                'url' => "https://www.qidian.com/all?chanId=0&subCateId=0&page=1&style=2&tag=",
                'filename' => 'base/1.html',
                'step' => self::BASE_STEP,
                'next' => 1
            ]));
            $this->pm->sendTo(self::SPIDER, json_encode([
                'url' => "https://www.qidian.com/all?chanId=0&subCateId=0&page=2&style=2&tag=",
                'filename' => 'base/2.html',
                'step' => self::BASE_STEP,
            ]));
        });


    }

    protected function common($name)
    {
        $this->queue = new Channel(2000);
        $this->data['running'] = 0;
        $this->data['empty_num'] = 0;
        $this->data['other'] = [];

        if ($this->pm->getWorkerId() == 0) {
            $num = 0;
            foreach (self::$processes as $process) {
                $num += $process['num'] ?? 1;
            }
            $this->data['num'] = $num;

            $this->data['step'] = 1;

        }

        Timer::tick(5000, function () use ($name) {
            $worker_id = $this->pm->getWorkerId();
            $pid = $this->pm->getProcess()->pid;
            self::log("{$name}进程[workerId {$worker_id}#][pid {$pid}#]，工作队列长度" . $this->queue->length());

            $l = $this->queue->length();
            if ($l == 0) {
                $this->data['empty_num']++;
                self::log("需要记录的值 2# {$l}", $this->data);
                if ($this->data['empty_num'] > 3) {
                    $this->data['running'] = 1;
                    if ($this->data['empty_num'] > 100000) {
                        $this->data['empty_num'] = 3;
                    }
                    if ($this->pm->getWorkerId() == 0) {
                        $this->pm->sendAll(json_encode([
                            'controller' => 'q',
                        ]));
                        $num = array_sum($this->data['other']);
                        self::log("需要记录的值 1# {$num}");
                        if ($num == $this->data['num'] - 1) {
                            if ($this->data['step'] == 1) {
                                $this->index();
                                $this->data['step'] = 0;
                                $this->data['empty_num'] = 0;
                                $this->data['other'] = [];
                            } else {
                                $this->pm->stop();
                            }
                        }
                    }
                }

            }
        });

        go(function () {
            while (true) {
                $data = $this->pm->recv();
                $data = json_decode($data, true);
                if (isset($data['controller'])) {
                    switch ($data['controller']) {
                        case 'q';
                            $this->pm->send(0, json_encode([
                                'controller' => 'a',
                                'worker_id' => $this->pm->getWorkerId(),
                                'rt' => $this->data,
                            ]));
                            break;
                        case 'a':
                            $this->data['other'][$data['worker_id']] = $data['rt']['running'];
                            break;
                    }
                    continue;
                }
                $this->data['empty_num'] = 0;
                $this->data['running'] = 0;
                $this->queue->push($data);
            }
        });
    }


    protected function base($data)
    {
        if (!$filename = $data['filename'] ?? null) {
            return false;
        }
        if (!isset($data['next'])) {
            return false;
        }
        if (!$html = $this->fs->read($filename)) {
            return false;
        }

        // 获取所有分类
        $this->ql->setHtml($html)->find('.select-list ul[type=category]')->each(function (\QL\Dom\Elements $item) {
            $item->children('li')->each(function (\QL\Dom\Elements $i) {
                $data_id = $i->attr('data-id');
                if ($data_id > 0) {
                    $this->pm->sendTo(self::SPIDER, json_encode([
                        'url' => "https://www.qidian.com/all?chanId={$data_id}&subCateId=0&page=1&style=2&tag=",
                        'step' => self::CATE_STEP,
                        'filename' => "cate/{$data_id}_1.html",
                        'chan_id' => $data_id,
                        'cate_name' => $i->text(),
                        'next' => 1,
                    ]));
                    $this->pm->sendTo(self::SPIDER, json_encode([
                        'url' => "https://www.qidian.com/all?chanId={$data_id}&subCateId=0&page=2&style=2&tag=",
                        'step' => self::CATE_STEP,
                        'filename' => "cate/{$data_id}_2.html",
                        'chan_id' => $data_id,
                        'cate_name' => $i->text(),
                    ]));
                }
            });
        });
        $this->clearHtml();


        return true;
    }

    protected function cate($data)
    {
        if (!$filename = $data['filename'] ?? null) {
            return false;
        }
        if (!isset($data['next'])) {
            return false;
        }
        if (!$html = $this->fs->read($filename)) {
            return false;
        }

        $this->ql->setHtml($html)->find('.sub-type dl[class=]')->each(function (\QL\Dom\Elements $item) use ($data) {
            $item->children('dd')->each(function (\QL\Dom\Elements $i) use ($data) {
                $data_id = $i->attr('data-subtype');
                if ($data_id > 0) {
                    $this->pm->sendTo(self::SPIDER, json_encode([
                        'url' => "https://www.qidian.com/all?chanId={$data_id}&subCateId=0&page=1&style=2&tag=",
                        'step' => self::SUB_CATE_STEP,
                        'filename' => "subCate/{$data['chan_id']}_{$data_id}_1.html",
                        'cate_name' => $i->text(),
                        'next' => 1,
                    ]));
                    $this->pm->sendTo(self::SPIDER, json_encode([
                        'url' => "https://www.qidian.com/all?chanId={$data_id}&subCateId=0&page=2&style=2&tag=",
                        'step' => self::SUB_CATE_STEP,
                        'filename' => "subCate/{$data['chan_id']}_{$data_id}_2.html",
                        'cate_name' => $i->text(),
                    ]));
                }
            });
        });
        $this->clearHtml();

        return true;
    }

    protected function index()
    {
        $files = $this->finder->files()->in([
            self::$tempDir . self::BASE_STEP,
            self::$tempDir . self::CATE_STEP,
            self::$tempDir . self::SUB_CATE_STEP,
        ])->name("*.html");

        $index = [];
        if ($this->fs->has('index.cache')) {
            $index = $this->fs->read('index.cache');
            $index = json_decode($index, true);
            if (!is_array($index)) {
                $index = [];
                $this->fs->delete('index.cache');
            }
        }

        // 避免replace
        $base_dir_len = strlen(self::$tempDir);
        foreach ($files as $file) {
            $file = substr($file, $base_dir_len);
            if (!$html = $this->fs->read($file)) {
                continue;
            }

            $data = $this->ql->setHtml($html)->rules([
                'title' => ['a[class=name]', 'text'],
                'data_bid' => ['a[class=name]', 'data-bid'],
            ])->range('.book-text>.rank-table-list tbody tr')->query()->getData()->all();

//            $data = $this->ql->setHtml($html)->find('.book-text>.rank-table-list tbody tr')->map(function (\QL\Dom\Elements $i) {
//                $item = $i->children('td')->eq(1)->children('a');
//                $title = $item->text();
//                $id = $item->attr('data-bid');
//
//                $pre = substr($id,0 ,2);
//                $this->pm->sendTo(self::SPIDER, json_encode([
//                    'step' => self::BOOK_STEP,
//                    'url' => "https://book.qidian.com/info/{$id}",
//                    'id' => $id,
//                    'filename' => "file/{$pre}/{$id}.html",
//                ]));
//
//                return [
//                    'title' => $title,
//                    'data_bid' => $id,
//                ];
//            })->all();

            $index = array_column($data, null, 'data_bid') + $index;
        }

        foreach ($index as $key => $v) {
            $pre = substr($v['data_bid'],0 ,2);
            $this->pm->sendTo(self::SPIDER, json_encode([
                'step' => self::BOOK_STEP,
                'url' => "https://book.qidian.com/info/{$v['data_bid']}",
                'id' => $v['data_bid'],
                'filename' => "file/{$pre}/{$v['data_bid']}.html",
            ]));
        }

        $this->fs->put('index.cache', json_encode($index, JSON_UNESCAPED_UNICODE));
        $this->clearHtml();
    }

    public function __toString()
    {
        return "当前进度：" . $this->jobsQueue->count() . "：" . json_encode($this->currentJob, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }

    /**
     * 释放ql占用的内存
     */
    protected function clearHtml()
    {
        $this->ql->setHtml(null);
        QueryList::destructDocuments();
    }


}