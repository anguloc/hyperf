<?php

namespace App\Spider;

use App\Model\SpidersNovelRank;
use App\Spider\Lib\AbstractSpider;
use App\Spider\Lib\Spider;
use Symfony\Component\Finder\Finder;
use Swoole\Timer;
use Swoole\Coroutine\Channel;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use QL\QueryList;
use App\Spider\Lib\QiDianToken;

/**
 * php bin/hyperf.php process:spider QiDianRankList
 *
 * Class QiDianRankList
 * @package App\Spider
 */
class QiDianRankList extends AbstractSpider implements Spider
{

    protected $startTime;

    protected $isRunning = true;

    protected static $tempDir = BASE_PATH . '/runtime/temp/qidian/';

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

    /**
     * @var Channel
     */
    protected $spiderQueue;

    /**
     * @var Channel
     */
    protected $parseQueue;

    /**
     * @var Channel
     */
    protected $sleepChan;

    const BASE_STEP = 'base'; // 基础 查全部 拉取分类 写全部的html文件
    const CATE_STEP = 'cate'; // 获取所有的一级分类 写html
    const SUB_CATE_STEP = 'subCate'; // 获取所有的二级分类 写html

    public function run()
    {
        try {
            \Swoole\Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);
            \Swoole\Coroutine\Run([$this, 'doRun']);
        } catch (\Throwable $e) {
            self::log("起点排行榜统计失败,msg:{$e->getMessage()}");
        }
    }

    public function doRun()
    {
        $this->init();

        Timer::tick(5000, function ($id) {
//            self::log("定时检测：parse：{$this->parseQueue->length()},spider：{$this->spiderQueue->length()}");
            if ($this->parseQueue->isEmpty() && $this->spiderQueue->isEmpty()) {
                $this->sleepChan->push(1);
                Timer::clear($id);
            }
        });
        while (!$r = $this->sleepChan->pop(2)) {
            for ($i = 1; $i <= 2; $i++) {
                go(function () {
                    try {
                        $this->spider();
                    } catch (\Throwable $e) {
                    }
                });
            }

            go(function () {
                try {
                    $this->parse();
                } catch (\Throwable $e) {
                }
            });
        }


        $this->index();

        self::log("排行榜抓取结束，时间：" . (microtime(true) - $this->startTime));
    }

    protected function init()
    {
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

        $this->spiderQueue = new Channel(3000);
        $this->parseQueue = new Channel(3000);
        $this->sleepChan = new Channel();

        $this->spiderQueue->push([
            'url' => "https://www.qidian.com/all?chanId=0&subCateId=0&page=1&style=2&tag=",
            'filename' => 'base/1.html',
            'step' => self::BASE_STEP,
        ]);
        $this->spiderQueue->push([
            'url' => "https://www.qidian.com/all?chanId=0&subCateId=0&page=2&style=2&tag=",
            'filename' => 'base/2.html',
            'no_parse' => 1,
        ]);

        $this->startTime = microtime(true);
    }

    public function spider()
    {
        if ($this->spiderQueue->isEmpty()) {
            return;
        }
        $data = $this->spiderQueue->pop();
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
        if (!isset($data['no_parse'])) {
            $this->parseQueue->push($data);
        }
    }

    public function parse()
    {
        if ($this->parseQueue->isEmpty()) {
            return;
        }
        $data = $this->parseQueue->pop();
        if (!$step = $data['step'] ?? null) {
            return;
        }
        if (!is_callable([$this, $data['step']])) {
            return;
        }
        call_user_func([$this, $data['step']], $data);
    }

    protected function base($data)
    {
        if (!$filename = $data['filename'] ?? null) {
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
                    $this->spiderQueue->push([
                        'url' => "https://www.qidian.com/all?chanId={$data_id}&subCateId=0&page=1&style=2&tag=",
                        'step' => self::CATE_STEP,
                        'filename' => "cate/{$data_id}_1.html",
                        'chan_id' => $data_id,
                        'cate_name' => $i->text(),
                    ]);
                    $this->spiderQueue->push([
                        'url' => "https://www.qidian.com/all?chanId={$data_id}&subCateId=0&page=2&style=2&tag=",
                        'step' => self::CATE_STEP,
                        'filename' => "cate/{$data_id}_2.html",
                        'chan_id' => $data_id,
                        'cate_name' => $i->text(),
                        'no_parse' => 1,
                    ]);
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
        if (!$html = $this->fs->read($filename)) {
            return false;
        }

        $this->ql->setHtml($html)->find('.sub-type dl[class=]')->each(function (\QL\Dom\Elements $item) use ($data) {
            $item->children('dd')->each(function (\QL\Dom\Elements $i) use ($data) {
                $data_id = $i->attr('data-subtype');
                if ($data_id > 0) {
                    $this->spiderQueue->push([
                        'url' => "https://www.qidian.com/all?chanId={$data_id}&subCateId=0&page=1&style=2&tag=",
                        'step' => self::SUB_CATE_STEP,
                        'filename' => "subCate/{$data['chan_id']}_{$data_id}_1.html",
                        'cate_name' => $i->text(),
                    ]);
                    $this->spiderQueue->push([
                        'url' => "https://www.qidian.com/all?chanId={$data_id}&subCateId=0&page=2&style=2&tag=",
                        'step' => self::SUB_CATE_STEP,
                        'filename' => "subCate/{$data['chan_id']}_{$data_id}_2.html",
                        'cate_name' => $i->text(),
                        'no_parse' => 1,
                    ]);
                }
            });
        });
        $this->clearHtml();

        return true;
    }

    protected function index()
    {
        $this->finder = new Finder();
        $this->ql = new QueryList();
        $this->fs = new Filesystem(new Local(self::$tempDir), ['disable_asserts' => true]);
        $files = $this->finder->files()->in([
            self::$tempDir . self::BASE_STEP,
            self::$tempDir . self::CATE_STEP,
            self::$tempDir . self::SUB_CATE_STEP,
        ])->name("*.html");

        $books = [];
        $line_time = mktime(1, 0, 0, date("m"), date("d"), date("Y"));

        $time = time();
        foreach ($files as $file) {
            if (!$file instanceof \Symfony\Component\Finder\SplFileInfo || !$html = $file->getContents()) {
                continue;
            }

            $data = $this->ql->setHtml($html)->rules([
                'nid' => ['a[class=name]', 'data-bid'],
                'title' => ['a[class=name]', 'text'],
                'line_time' => ['a[class=name]', 'data-bid', '', function () use ($line_time) {
                    return $line_time;
                }],
                'add_time' => ['a[class=name]', 'text', '', function () use ($time) {
                    return $time;
                }],
            ])->range('.book-text>.rank-table-list tbody tr')->query()->getData()->all();

            $books = array_column($data, null, 'nid') + $books;
            $this->clearHtml();
        }


        $list = array_chunk($books, 500);
        foreach ($list as $item) {
            try {
                SpidersNovelRank::insertOnDuplicateKey($item);
            } catch (\Throwable $e) {

            }
        }

        $this->fs->put('index.cache', json_encode($books, JSON_UNESCAPED_UNICODE));

    }

    protected function clearHtml()
    {
        $this->ql->setHtml(null);
        QueryList::destructDocuments();
    }

}