<?php

namespace App\Spider;

use App\Model\SpidersNovel;
use App\Model\SpidersNovelOption;
use App\Model\SpidersNovelRank;
use App\Model\SpidersNovelStatistic;
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
            $this->books();
            return;
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

        self::log("排行榜抓取结束，时间：" . (microtime(true) - $this->startTime));

        $this->index();

        self::log("排行榜解析结束，时间：" . (microtime(true) - $this->startTime));

        $this->books();

        self::log("排行榜单本抓取结束，时间：" . (microtime(true) - $this->startTime));

        $this->novel();

        // TODO clear file

        self::log("排行榜任务结束，时间：" . (microtime(true) - $this->startTime));
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
        $this->finder = $this->finder ?? new Finder();
        $this->ql = $this->ql ?? new QueryList();
        $this->fs = $this->fs ?? new Filesystem(new Local(self::$tempDir), ['disable_asserts' => true]);
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


        $exist = SpidersNovelRank::where([
            'line_time' => $line_time,
            'is_deleted' => NOT_DELETE_STATUS,
        ])->first('id');
        if (!$exist) {
            $list = array_chunk($books, 500);
            // 排行榜
            foreach ($list as $item) {
                try {
                    SpidersNovelRank::insertOnDuplicateKey($item);
                } catch (\Throwable $e) {
                    self::log("排行榜入库失败{$e->getMessage()}");
                }
            }
        }

        $this->fs->put('index.cache', json_encode($books, JSON_UNESCAPED_UNICODE));
    }

    protected function books()
    {
        $this->finder = $this->finder ?? new Finder();
        $this->ql = $this->ql ?? new QueryList();
        $this->fs = $this->fs ?? new Filesystem(new Local(self::$tempDir), ['disable_asserts' => true]);
        $this->client = $this->client ?? guzzle([
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36'
                ],
            ]);


        $data = $this->fs->read('index.cache');
        $data = json_decode($data, true);
        $count = count($data);
        if ($count <= 0) {
            self::log("起点排行榜数据错误");
            return false;
        }
        $chan = new Channel($count);
        $sleep_chan = new Channel(1);
        foreach ($data as $item) {
            $chan->push($item);
        }

        $spider = function () use ($chan) {
            if ($chan->isEmpty()) {
                return false;
            }
            $data = $chan->pop(2);
            if (empty($data) || !isset($data['nid'])) {
                return false;
            }
            $nid = $data['nid'];
            $url = "https://book.qidian.com/info/{$nid}";
            $resp = $this->client->get($url);
            if ($resp->getStatusCode() != 200) {
                $content = '';
                if ($resp->getBody()) {
                    $content = $resp->getBody()->getContents();
                }
                self::log("http error,url:{$url},content:{$content}");
                return false;
            }
            $filename = "books/{$nid}_book.html";
            $html = $resp->getBody()->getContents();
            if (!$this->fs->put($filename, $html)) {
                self::log("nid:{$nid}, file error");
            }
            return true;
        };

        while (!$sleep_chan->pop(2)) {
            for ($i = 1; $i <= 2; $i++) {
                go(function () use ($spider) {
                    try {
                        call_user_func($spider);
                    } catch (\Throwable $e) {
                        echo "error", PHP_EOL;
                    }
                });
            }
            if ($chan->isEmpty()) {
                $sleep_chan->push(1);
            }
        }

        return true;
    }

    protected function novel()
    {
        $files = (new Finder())->files()->in([
            self::$tempDir . '/books',
        ])->name("*.html");

        $ql = new QueryList();
        $line_time = mktime(1, 0, 0, date("m"), date("d"), date("Y"));

        $novels = SpidersNovel::where('is_deleted', NOT_DELETE_STATUS)->get()->all();

        $raw_options = SpidersNovelOption::where('is_deleted', NOT_DELETE_STATUS)->get()->all();

        $statistics_exist = SpidersNovelStatistic::where([
            'line_time' => $line_time,
            'is_deleted' => NOT_DELETE_STATUS,
        ])->first('id');

        $options = [];
        foreach ($raw_options as $option) {
            if (!$option instanceof SpidersNovelOption) {
                continue;
            }
            if (!isset($options[$option['nid']])) {
                $options[$option['nid']] = [
                    SpidersNovelOption::TAGS_OPTION => [],
                    SpidersNovelOption::CUSTOM_TAGS_OPTION => [],
                ];
            }
            $options[$option['nid']][$option['option']][] = $option->getAttributes();
        }

        $novels = array_column($novels, null, 'key');

        foreach ($novels as $key => $novel) {
            $novels[$key]['options'] = $options[$novel['id']] ?? [];
        }

        $time = time();
        $statistics = $insert_tags = [];
        foreach ($files as $file) {
            if (!$file instanceof \Symfony\Component\Finder\SplFileInfo || !$html = $file->getContents()) {
                continue;
            }

            $ql = $ql->setHtml($html);

            $nid = str_replace('_book.html', '', $file->getFilename());
            $title = $ql->find(".book-info>h1>em")->text();
            $tags = $ql->find(".book-info>p.tag>a")->map(function (\QL\Dom\Elements $i) {
                return $i->text();
            })->all();
            $month = $ql->find(".fans-interact #monthCount")->text();
            $rec = $ql->find(".fans-interact #recCount")->text();
            $reward = $ql->find(".fans-interact #rewardNum")->text();
            $custom_tags = $ql->find(".tag-wrap a")->map(function (\QL\Dom\Elements $i) {
                return $i->text();
            })->all();

            // novel
            if (!isset($novels[SpidersNovel::getNovelKey($nid)])) {
                try {
                    $novel_id = SpidersNovel::insertGetId([
                        'title' => $title,
                        'url' => "https://book.qidian.com/info/{$nid}",
                        'key' => SpidersNovel::getNovelKey($nid),
                        'add_time' => time(),
                    ]);
                    $novels_options = [];
                } catch (\Throwable $e) {
                    self::log("insert novel error-{$nid}");
                    continue;
                }
            } else {
                $novel_id = $novels[SpidersNovel::getNovelKey($nid)]['id'];
                $novels_options = $novels[SpidersNovel::getNovelKey($nid)]['options'] ?? [];
            }

            // novel option
            $tags_options = $novels_options[SpidersNovelOption::TAGS_OPTION] ?? [];
            $custom_tags_options = $novels_options[SpidersNovelOption::CUSTOM_TAGS_OPTION] ?? [];

            $insert_tags = array_merge($insert_tags, array_map(function ($item) use ($novel_id, $time) {
                return [
                    'nid' => $novel_id,
                    'option' => SpidersNovelOption::TAGS_OPTION,
                    'value' => $item,
                    'add_time' => $time,
                ];
            }, array_diff($tags, array_column($tags_options, 'value'))));

            $insert_tags = array_merge($insert_tags, array_map(function ($item) use ($novel_id, $time) {
                return [
                    'nid' => $novel_id,
                    'option' => SpidersNovelOption::CUSTOM_TAGS_OPTION,
                    'value' => $item,
                    'add_time' => $time,
                ];
            }, array_diff($custom_tags, array_column($custom_tags_options, 'value'))));

            // statistics
            if (!$statistics_exist) {
                $statistics[] = [
                    'nid' => $novel_id,
                    'month_ticket' => (int)$month,
                    'rec_ticket' => (int)$rec,
                    'reward' => (int)$reward,
                    'line_time' => $line_time,
                    'add_time' => $time,
                ];
            }
        }

        $list = array_chunk($insert_tags, 500);
        foreach ($list as $item) {
            try {
                SpidersNovelOption::insertOnDuplicateKey($item);
            } catch (\Throwable $e) {
                self::log("标签入库失败{$e->getMessage()}");
            }
        }

        $list = array_chunk($statistics, 500);
        foreach ($list as $item) {
            try {
                SpidersNovelStatistic::insertOnDuplicateKey($item);
            } catch (\Throwable $e) {
                self::log("统计入库失败{$e->getMessage()}");
            }
        }

        return;
    }

    protected function clearHtml()
    {
        $this->ql->setHtml(null);
        QueryList::destructDocuments();
    }

}