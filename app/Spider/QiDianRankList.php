<?php

namespace App\Spider;

use App\Model\SpidersNovel;
use App\Model\SpidersNovelOption;
use App\Model\SpidersNovelRank;
use App\Model\SpidersNovelStatistic;
use App\Spider\Lib\AbstractSpider;
use App\Spider\Lib\Spider;
use Hyperf\DbConnection\Db;
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
            self::log("起点排行榜统计失败,file:{$e->getFile()},line:{$e->getLine()},msg:{$e->getMessage()}");
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

        // clear file
        $this->clearFile();

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
            self::log("rank:error:1:http error,url:{$url},content:{$resp->getStatusCode()}");
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
                try {
                    // novel入库
                    $new_novel_key = array_map(function ($v) {
                        return SpidersNovel::getNovelKey($v['nid']);
                    }, $item);
                    $exist_novels = SpidersNovel::query()->where([
                        'is_deleted' => NOT_DELETE_STATUS,
                    ])->whereIn('key', $new_novel_key)->get('key')->toArray();
                    $exist_novels = array_column($exist_novels, 'key');

                    $novel = [];
                    foreach ($item as $bv) {
                        if (in_array(SpidersNovel::getNovelKey($bv['nid']), $exist_novels)) {
                            continue;
                        }
                        $novel[] = [
                            'title' => $bv['title'],
                            'url' => "https://book.qidian.com/info/{$bv['nid']}",
                            'key' => SpidersNovel::getNovelKey($bv['nid']),
                            'add_time' => $time,
                        ];
                    }
                    if ($novel) {
                        SpidersNovel::insertOnDuplicateKey($novel);
                    }
                } catch (\Throwable $e) {
                    self::log("novel-1入库失败{$e->getMessage()}");
                }
            }
        }

        $this->fs->put('index.cache', json_encode($books, JSON_UNESCAPED_UNICODE));
    }

    protected function books()
    {
        $limit = 500;
        $chan = new Channel($limit);
        $consumer_num = 10;
        $sleep_chan = new Channel($consumer_num);

        $last_id = SpidersNovel::query()
            ->where('is_deleted', NOT_DELETE_STATUS)
            ->orderByDesc('add_time')
            ->orderByDesc('id')
            ->value('id');

        if ($last_id <= 0) {
            return true;
        }

        // producer
        $producer = function () use ($sleep_chan, $consumer_num, $chan, $limit, $last_id) {
            // 先++方便查询 <
            $last_id++;
            $dir_num = 0;
            while ($last_id > 0) {
                try {
                    $novels = SpidersNovel::query()
                        ->where('is_deleted', NOT_DELETE_STATUS)
                        ->where('id', '<', $last_id)
                        ->orderByDesc('add_time')
                        ->orderByDesc('id')
                        ->limit($limit)
                        ->get(['id', 'key'])->toArray();
                    if (empty($novels)) {
                        break;
                    }
                    $dir_num++;
                    $dir_path = self::$tempDir . 'books/' . $dir_num;
                    mkdir($dir_path, 0755);
                    $end = end($novels);
                    $last_id = $end['id'];

                    $nid = array_column($novels, 'id');
                    $options = SpidersNovelOption::query()
                        ->where('is_deleted', NOT_DELETE_STATUS)
                        ->where('option', SpidersNovelOption::IS_404)
                        ->whereIn('nid', $nid)
                        ->get(['nid', 'value'])->toArray();
                    $options = array_column($options, 'value', 'nid');

                    foreach ($novels as $novel) {
                        $item = SpidersNovel::restoreNovelKey($novel['key']);
                        if (empty($item)) {
                            continue;
                        }
                        if (isset($options[$novel['id']]) && $options[$novel['id']]) {
                            self::log("spider:jump,id:{$novel['id']},nid:{$item}");
                        }
                        $chan->push(['nid' => $item, 'dir' => $dir_num]);
                    }
                    if (count($novels) < $limit) {
                        break;
                    }
                } catch (\Throwable $e) {
                    self::log("novel_table query error");
                    continue;
                }
            }

            // 结束逻辑
            while (true) {
                if ($chan->isEmpty()) {
                    for ($i = 0; $i < $consumer_num; $i++) {
                        $sleep_chan->push(1);
                    }
                    break;
                }
                sleep(5);
            }

        };

        // consumer
        $consumer = function () use ($chan, $sleep_chan) {
            $client = guzzle([
                'headers' => [
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36'
                ],
            ]);
            $fs = new Filesystem(new Local(self::$tempDir), ['disable_asserts' => true]);
            while (!$sleep_chan->pop(1)) {
                try {
                    if ($chan->isEmpty()) {
                        continue;
                    }
                    $data = $chan->pop(1);
                    if (empty($data) || !isset($data['nid'])) {
                        continue;
                    }

                    $nid = $data['nid'];
                    $dir = $data['dir'];
                } catch (\Throwable $e) {
                    self::log("channel:pop:fail");
                    continue;
                }
                try {
                    $url = "https://book.qidian.com/info/{$nid}";
                    $resp = $client->get($url);
                    if ($resp->getStatusCode() != 200) {
                        self::log("http error,url:{$url},code:{$resp->getStatusCode()}");
                        continue;
                    }

                    $filename = "books/{$dir}/{$nid}_book.html";
//                    $filename = "books/{$nid}_book.html";
                    if (!$fs->put($filename, $resp->getBody()->getContents())) {
                        self::log("nid:{$nid}, file error");
                    }
                } catch (\Throwable $e) {
                    if (!isset($data['request_num'])) {
                        $data['request_num'] = 0;
                    }
                    $data['request_num']++;
                    if ($data['request_num'] < 2) {
                        $chan->push($data);
                    }
                    self::log("error:books:1:" . date("Y-m-d H:i:s") . $e->getMessage());
                }
            }
        };

        // run
        for ($i = 0; $i < $consumer_num; $i++) {
            go(function () use ($consumer) {
                call_user_func($consumer);
            });
        }
        $end = false;
        go(function () use ($producer, &$end) {
            call_user_func($producer);
            $end = true;
        });

        // 阻塞当前协程 其他协程都退出后恢复
        while (true) {
            sleep(10);
            if ($end) {
                break;
            }
        }

        return true;
    }

    protected function novel()
    {
        $dirs = (new Finder())->depth(0)->directories()->in([
            self::$tempDir . '/books',
        ]);

        $ql = new QueryList();
        $line_time = mktime(1, 0, 0, date("m"), date("d"), date("Y"));

        if ($dirs->count() <= 0) {
            self::log("novel:error:missed_books");
            return false;
        }

        $statistics_exist = SpidersNovelStatistic::where([
            'line_time' => $line_time,
            'is_deleted' => NOT_DELETE_STATUS,
        ])->value('id');

        foreach ($dirs as $dir) {
            $files = (new Finder())->files()->in([
                $dir->getRealPath()
            ])->name('*.html');

            $novel_key = [];
            foreach ($files as $file) {
                if (!$file instanceof \Symfony\Component\Finder\SplFileInfo) {
                    continue;
                }
                $file_name = $file->getFilename();
                $nid = str_replace("_book.html", "", $file_name);
                if (empty($nid)) {
                    continue;
                }
                $novel_key[] = SpidersNovel::getNovelKey($nid);
            }

            $novels = SpidersNovel::query()
                ->where('is_deleted', NOT_DELETE_STATUS)
                ->whereIn('key', $novel_key)
                ->get()->toArray();

            $ids = array_column($novels, 'id');

            $raw_options = SpidersNovelOption::query()
                ->where('is_deleted', NOT_DELETE_STATUS)
                ->whereIn('nid', $ids)
                ->get()->toArray();

            $options = [];
            foreach ($raw_options as $option) {
                if (!isset($options[$option['nid']])) {
                    $options[$option['nid']] = [
                        SpidersNovelOption::TAGS_OPTION => [],
                        SpidersNovelOption::CUSTOM_TAGS_OPTION => [],
                    ];
                }
                $options[$option['nid']][$option['option']][] = $option;
            }
            unset($raw_options);

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
                $tags = $ql->find(".book-info>p.tag>a")->map(function (\QL\Dom\Elements $i) {
                    return $i->text();
                })->all();
                $month = $ql->find(".fans-interact #monthCount")->text();
                $rec = $ql->find(".fans-interact #recCount")->text();
                $reward = $ql->find(".fans-interact #rewardNum")->text();
                $custom_tags = $ql->find(".tag-wrap a")->map(function (\QL\Dom\Elements $i) {
                    return $i->text();
                })->all();

                $novel_id = $novels[SpidersNovel::getNovelKey($nid)]['id'] ?? false;
                if (empty($novel_id)) {
                    self::log("novel:error:novel_id_fail:{$nid}");
                    continue;
                }
                $novels_options = $novels[SpidersNovel::getNovelKey($nid)]['options'] ?? [];

                // novel option
                $tags_options = $novels_options[SpidersNovelOption::TAGS_OPTION] ?? [];
                $custom_tags_options = $novels_options[SpidersNovelOption::CUSTOM_TAGS_OPTION] ?? [];

                $tags = array_unique($tags); // 居然会有重复的 不知道他们业务怎么允许的
                $insert_tags = array_merge($insert_tags, array_map(function ($item) use ($novel_id, $time) {
                    return [
                        'nid' => $novel_id,
                        'option' => SpidersNovelOption::TAGS_OPTION,
                        'value' => $item,
                        'add_time' => $time,
                    ];
                }, array_diff($tags, array_column($tags_options, 'value'))));

                $custom_tags = array_unique($custom_tags); // 居然会有重复的 不知道他们业务怎么允许的
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

            if (!empty($insert_tags)) {
                try {
                    SpidersNovelOption::insertOnDuplicateKey($insert_tags);
                } catch (\Throwable $e) {
                    self::log("novel:error:insert_tags_fail:{$e->getMessage()}");
                }
            }

            if (!empty($statistics)) {
                try {
                    SpidersNovelStatistic::insertOnDuplicateKey($statistics);
                } catch (\Throwable $e) {
                    self::log("novel:error:statistics_fail:{$e->getMessage()}");
                }
            }
        }

        return true;
    }

    protected function clearFile()
    {
        // 移除备份的   备份当天的
        $source = 'qidian';
        $bak = 'qidian_bak';

        $fs = new Filesystem(new Local(self::$tempDir . '../'), ['disable_asserts' => true]);

        $a = $fs->listContents();

        $b = $fs->has("asd");

        if ($fs->has($bak)) {
            try {
                $b = $fs->deleteDir($bak);
            } catch (\Throwable $e) {
                $b = false;
                self::log("排行榜爬虫删除备份失败,ex:{$e->getMessage()},file:{$e->getFile()},line:{$e->getLine()}");
            }
            if (!$b) {
                self::log("排行榜爬虫删除备份失败");
            }
        }

        if ($fs->has($source)) {
            try {
                if ($fs->has($bak)) {
                    $bak = $bak . '_' . date('Ymd');
                }
                $fs->rename($source, $bak);
            } catch (\Throwable $e) {
                $b = false;
                self::log("排行榜爬虫备份当天数据失败,ex:{$e->getMessage()},file:{$e->getFile()},line:{$e->getLine()}");
            }
            if (!$b) {
                self::log("排行榜爬虫备份当天数据失败");
            }
        }
    }

    protected function clearHtml()
    {
        $this->ql->setHtml(null);
        QueryList::destructDocuments();
    }

}