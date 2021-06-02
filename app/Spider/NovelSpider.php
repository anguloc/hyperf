<?php

namespace App\Spider;

use App\Model\SpidersNovelRank;
use App\Model\SpidersTask;
use App\Spider\Lib\AbstractSpider;
use App\Spider\Lib\QiDianHelper;
use App\Spider\Lib\Spider;
use App\Spider\Template\QiDianIndex;
use Hyperf\Database\Model\ModelNotFoundException;
use Symfony\Component\Finder\Finder;
use Swoole\Timer;
use Swoole\Coroutine\Channel;
use League\Flysystem\Adapter\Local;
use League\Flysystem\Filesystem;
use QL\QueryList;
use Sunra\PhpSimple\HtmlDomParser;
use App\Spider\Lib\QiDianToken;

/**
 * @package App\Spider
 */
class NovelSpider extends AbstractSpider implements Spider
{

    protected $startTime;

    protected $isRunning = true;

    protected static $tempDir = BASE_PATH . '/runtime/temp/novel_spider/';

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

    /**
     * 搜索关键词
     * @var string
     */
    protected $keyword;

    /**
     * @var int 任务id
     */
    protected $taskId;

    /**
     * @var SpidersTask 任务数据实体
     */
    protected $task;

    /**
     * @var int 当前步骤
     */
    protected $currentStep;

    // 步骤
    const STEP_INIT = 1; // 初始化
    const STEP_BID = 2; // 拿bid
    const STEP_QIDIAN_INDEX = 3; // 拿起点目录
    const STEP_SOURCE_INDEX = 4; // 拿资源站目录
    const STEP_CONTENT = 5; // 抓取
    const STEP_FORMAT = 6; // 格式化内容
    const STEP_CLEAR_CONTENT = 7; // 清理

    const STEP_ARRAY = [
        self::STEP_INIT,
        self::STEP_BID,
        self::STEP_QIDIAN_INDEX,
        self::STEP_SOURCE_INDEX,
        self::STEP_CONTENT,
        self::STEP_FORMAT,
        self::STEP_CLEAR_CONTENT,
    ];

    public function run()
    {
        try {
            if (is_dir(self::$tempDir)) {
                mkdir(self::$tempDir, 0644, true);
            }

            if ($this->taskId) {
                $this->task = (new SpidersTask())->where([
                    'id' => $this->taskId,
                ])->firstOrFail();
            } else {
                if (!$this->keyword) {
                    throw new \LogicException("关键词缺失");
                }
                $this->task = new SpidersTask();
                $this->task->fill([
                    'content' => json_encode([
                        'step' => self::STEP_INIT,
                        'bid' => '',
                        'keyword' => $this->keyword,
                        'qidian_search_file' => '',
                        'qidian_index_file' => '',
                        'source' => [],
                        'exception' => '',
                    ]),
                ])->save();
                $this->taskId = $this->task->id;
            }
            $this->task->content = json_decode($this->task->content, true);
            $this->keyword = $this->task->content['keyword'];
            $this->currentStep = $this->task->content['step'] ?? false;
            if (!in_array($this->currentStep, self::STEP_ARRAY)) {
                throw new \LogicException("错误的步骤");
            }

            \Swoole\Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);
            \Swoole\Coroutine\Run([$this, 'doRun']);
        } catch (ModelNotFoundException $e) {
            self::log("错误的id");
        } catch (\Throwable $e) {
            self::log("error,msg:{$e->getMessage()}");
            $this->saveTask([
                'exception' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 保存任务
     *
     * @param array $array 额外的保存字段
     * @return bool
     */
    protected function saveTask($array = [])
    {
        if (empty($this->task)) {
            return false;
        }
        $content = $this->task->content;
        if (!empty($array)) {
            $content = array_merge($content, $array);
        }
        $this->task->fill([
            'content' => json_encode($content, JSON_UNESCAPED_UNICODE),
        ])->save();
        return true;
    }

    /**
     * 设置关键词
     * @param $keyword
     * @return self
     */
    public function setKeyword($keyword)
    {
        $this->keyword = $keyword;
        return $this;
    }

    /**
     * 设置任务id
     * @param $task_id
     */
    public function setTaskId($task_id)
    {
        $this->taskId = $task_id;
    }


    public function doRun($step = 0)
    {
        $this->init();
        $this->searchQidian();
        $this->saveQiDianIndex();
        $this->queryEachSource();
        $this->spiderContent();
        $this->formatContent();
        $this->clearContent();
    }

    /**
     * 搜索起点
     * 抓取起点bid
     * 写库
     */
    protected function searchQidian()
    {
        if ($this->currentStep >= self::STEP_BID) {
            return true;
        }
        if (!$this->keyword) {
            throw new \LogicException("关键词不存在");
        }

        $file_name = md5('qidian_search_file' . $this->keyword);
        $file = self::$tempDir . "/keyword/{$file_name}.html";

        if (!is_file($file)) {
            $url = "https://www.qidian.com/search?kw=" . $this->keyword;
            $resp = guzzle()->get($url);
            if ($resp->getStatusCode() != 200) {
                throw new \LogicException("起点搜索错误");
            }
            $html = $resp->getBody()->getContents();
            file_put_contents($file, $html);
        } else {
            $html = file_get_contents($file);
        }
        $this->task->content['qidian_search_file'] = $file;

        $ql = new QueryList();
        $dom = $ql->setHtml($html)->find(".red-kw");
        $title = $dom->text();
        if ($title != $this->keyword) {
            throw new \LogicException("没有搜索到");
        }
        $bid = $dom->parent("a")->attr("data-bid");
        if (!$bid) {
            throw new \LogicException("未找到bid");
        }

        $this->task->content['bid'] = $bid;
        $this->task->content['step'] = self::STEP_BID;
        $this->currentStep = self::STEP_BID;
        $this->saveTask();
        return true;
    }

    /**
     * 从起点拿目录，写库
     */
    protected function saveQiDianIndex()
    {
        if ($this->currentStep >= self::STEP_QIDIAN_INDEX) {
            return true;
        }

        $file_name = md5('qidian_index_file' . $this->keyword);
        $file = self::$tempDir . "/keyword/{$file_name}.html";

        if (!is_file($file)) {
            $bid = $this->task->content['bid'];
            $index = QiDianHelper::getIndex($bid);
            if (!$index || !is_array($index)) {
                throw new \LogicException("抓取主站目录失败");
            }
            file_put_contents($file, $index);
        } else {
            $index = file_get_contents($file);
        }
        $this->task->content['qidian_index_file'] = $file;

        $novel_index = (new QiDianIndex())->parse($index);
        // TODO 入库

        $this->task->content['step'] = self::STEP_QIDIAN_INDEX;
        $this->currentStep = self::STEP_QIDIAN_INDEX;
        $this->saveTask();

        return true;
    }

    /**
     * 从其他网站搜索，拿目录链接
     */
    protected function queryEachSource()
    {
        if ($this->currentStep >= self::STEP_SOURCE_INDEX) {
            return true;
        }

        // TODO 多个源站
        $source = 'https://www.ibooktxt.com/search.php?q=';
        $file_name = md5('source' . $source . $this->keyword);
        $file = self::$tempDir . "/keyword/{$file_name}.html";
        if (!is_file($file)) {
            $url = $source . $this->keyword;
            $html = http_request($url);
            file_put_contents($file, $html);
        } else {
            $html = file_get_contents($file);
        }

//        $ql = new QueryList();
//        $ql->setHtml($html)->find(".result-item-title .title");

        $this->task->content['source'][$file_name] = [
            'search_file' => $file,
        ];

        $this->currentStep = self::STEP_SOURCE_INDEX;
        return true;
    }

    /**
     * 抓目录，对比数据库中目录
     * 调用爬虫接口，回写rid关联爬虫内容表
     */
    protected function spiderContent()
    {
        if ($this->currentStep >= self::STEP_CONTENT) {
            return true;
        }
    }

    /**
     * 确保所有章节拉取到后、或者手动改状态后，格式化数据到章节表
     */
    protected function formatContent()
    {
        if ($this->currentStep >= self::STEP_FORMAT) {
            return true;
        }
    }

    /**
     * 调用清理逻辑，洗数据
     */
    protected function clearContent()
    {
        if ($this->currentStep >= self::STEP_CLEAR_CONTENT) {
            return true;
        }
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
            'verify' => false,
        ]);

        if (\Swoole\Coroutine::getuid() > 0) {
            $this->spiderQueue = new Channel(3000);
            $this->parseQueue = new Channel(3000);
            $this->sleepChan = new Channel();
        }

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
        echo $data['url'] . PHP_EOL;
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


    protected function clearHtml()
    {
        $this->ql->setHtml(null);
        QueryList::destructDocuments();
    }

}