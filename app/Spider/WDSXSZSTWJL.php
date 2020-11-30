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
use Sunra\PhpSimple\HtmlDomParser;
use App\Spider\Lib\QiDianToken;

/**
 * php bin/hyperf.php process:spider WDSXSZSTWJL
 *
 * 我师兄实在太稳健了
 *
 * @package App\Spider
 */
class WDSXSZSTWJL extends AbstractSpider implements Spider
{

    protected $startTime;

    protected $isRunning = true;

    protected static $tempDir = BASE_PATH . '/runtime/temp/wdsxszstwjl/';

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

    public function run()
    {
        try {
            return $this->doRun();
            \Swoole\Runtime::enableCoroutine(true, SWOOLE_HOOK_ALL);
            \Swoole\Coroutine\Run([$this, 'doRun']);
        } catch (\Throwable $e) {
            self::log("error,msg:{$e->getMessage()}");
        }
    }

    public function doRun()
    {
        $this->init();

        // index.html
//        $resp = $this->client->get("https://www.23txt.com/files/article/html/56/56263/");
//        $html = $resp->getBody()->getContents();
//        $encode = mb_detect_encoding($html, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
//        $html = iconv($encode, 'UTF-8//IGNORE', $html);
//        $this->fs->put("index.html", $html);
//        return;

        // index.json
//        $rt = [];
//        $html = $this->fs->read("index.html");
//        $html = str_replace('charset=gbk','',$html);
//        $encode = mb_detect_encoding($html, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
//        $html = iconv($encode, 'UTF-8//IGNORE', $html);
//        $this->ql->setHtml($html)->find("#list dl dd")->each(function (\QL\Dom\Elements $item) use (&$rt) {
//            $rt[] = [
//                'href' => "https://www.23txt.com".$item->children('a')->attr('href'),
//                'title' => $item->text()
//            ];
//        });
//        $this->fs->put("index.json", json_encode($rt, JSON_UNESCAPED_UNICODE));
//        return;

        // parse 345 1375
        $files = $this->finder->files()->in([
            self::$tempDir,
        ])->name('*.html');

        $i = 0;
        foreach ($files as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            if ($file->getFilename() == 'index.html') {
                continue;
            }
            $html = $file->getContents();
            $html = str_replace('charset=gbk','',$html);
            $encode = mb_detect_encoding($html, array("ASCII", 'UTF-8', "GB2312", "GBK", 'BIG5'));
            $html = iconv($encode, 'UTF-8//IGNORE', $html);

            $dom = $this->ql->setHtml($html);


            $title = $dom->find(".bookname h1")->text();

            $c = $dom->find('#content')->html();

            $con = explode("<br>", $c);

            foreach ($con as $k => &$item) {
                $item = trim($item);
                $item = ltrim($item, chr(194) . chr(160));
            }

            $c = implode("\n", $con);
            $c = strip_tags($c);

            $c = $title . "\n\n" . preg_replace('/\<br(\s*)?\/?\>/i', "\n", $c) . "\n\n\n";

            $c = trim($c) . "\n\n";

            $c = str_replace(['【看书君】','www.kanshujun.com','看书君', '(有）?(意）?(思）?(书）?(院）', '有）?意）?思）?书）?院）'],'',$c);

            file_put_contents(self::$tempDir . 'log.log', $c, FILE_APPEND);

            $i++;
            $t = microtime(true) - $this->startTime;
            echo "运行完第{$i}次：".date("Y-m-d H:i:s").'--'.$t.PHP_EOL;

        }

        return;

        // inject
        $json = $this->fs->read("index.json");
        $data = json_decode($json, true);
        defer(function()use($data){
            echo 'index:',count($data),PHP_EOL;
        });
        $c = true;
        foreach ($data as $datum) {
            if (strpos($datum['href'],'24824137') !== false) {
                $c = false;
            }
            if ($c) {
                continue;
            }
            $filename = substr($datum['href'], strrpos($datum['href'], '/') + 1);
            $filename = substr($filename,0,2) . '/' . $filename;
            $this->spiderQueue->push([
                'url' => $datum['href'],
                'filename' => $filename,
                'no_parse' => 1,
            ]);
        }

        Timer::tick(5000, function ($id) {
            self::log("定时检测：parse：{$this->parseQueue->length()},spider：{$this->spiderQueue->length()}");
            if ($this->parseQueue->isEmpty() && $this->spiderQueue->isEmpty()) {
                $this->sleepChan->push(1);
                Timer::clear($id);
            }
        });
        while (!$r = $this->sleepChan->pop(1)) {
            go(function () {
                $this->spider();
            });
            go(function () {
                $this->spider();
            });
            go(function () {
                $this->spider();
            });
            go(function () {
                $this->spider();
            });
//            go(function () {
//                $this->parse();
//            });
        }


//        $this->index();

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