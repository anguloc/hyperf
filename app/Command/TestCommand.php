<?php

declare(strict_types=1);

namespace App\Command;

use App\Amqp\Producer\MinuteProducer;
use App\Command\Lib\BaseCommand;
use App\Command\Lib\ProcessCommand;
use App\Spider\Lib\QiDianToken;
use App\Util\DelayQueue;
use App\Util\Logger;
use Hyperf\Amqp\Producer;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use Psr\Container\ContainerInterface;
use Swoole\Process;
use Swoole\Timer;
use xingwenge\canal_php\CanalConnectorFactory;
use xingwenge\canal_php\CanalClient;
use xingwenge\canal_php\Fmt;
use Hyperf\DbConnection\Db;
use Hyperf\Guzzle\ClientFactory;
use Psr\Log\LoggerInterface;
use Sunra\PhpSimple\HtmlDomParser;
use DHelper\Process\DMProcess;
use Swoole\Coroutine\Channel;

/**
 * @Command
 */
class TestCommand extends BaseCommand
//class TestCommand extends ProcessCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;


    protected $masterPid;
    protected $masterData = [];
    protected $workers = [];
    protected $coroutine = false;

    protected static $daemon = true;

    const BASE_URL = 'http://www.sis001.com/forum/';

    protected $nums = 2;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
        $this->setName("test");
    }

    public function configure()
    {
        $this->setDescription('test');
    }

    public function asd(\DHelper\Process\DMProcess $p = null)
    {
        Timer::tick(3000, function()use($p){
//            echo "asd\n";
            $p->sendTo('a', 'Hello func');
        });

        $worker_id = $p->getWorkerId();
        go(function()use($p,$worker_id){
            while(1){
                $l = $p->recv();
                echo "asd[{$worker_id}]接收数据：",$l,PHP_EOL;
            }
        });

    }

    public function handle()
    {


        \Co\run(function(){
            $chan = new \Swoole\Coroutine\Channel(30);
            \Swoole\Coroutine::create(function () use ($chan) {
                for($i = 0; $i < 100000; $i++) {
                    \co::sleep(1.0);
                    $chan->push(['rand' => rand(1000, 9999), 'index' => $i]);
                    echo "$i\n";
                }
            });
            \Swoole\Coroutine::create(function () use ($chan) {
                while(1) {
//                    $data = $chan->pop();
//                    var_dump($data);
                    echo "l:{$chan->length()}\n";
                    \co::sleep(1.0);
                }
            });
        });


        return;
        go(function(){
            $q = new Channel();
            go(function()use($q){

                echo "a",$q->length(),PHP_EOL;
                var_dump($q->isFull());
                $q->push(1);
                $q->push(1);
                echo "b",$q->length(),PHP_EOL;
                $q->pop();
                echo "c",$q->length(),PHP_EOL;

            });
        });


        go(function(){
            Timer::tick(2000, function () {

            });
        });


        return;

//        $this->container->get(Producer::class)->produce($message);
        $p = new \DHelper\Process\DMProcess();
        $p->register('worker_1', function(DMProcess $p = null){
            Timer::tick(5000, function()use($p){
                $p->sendTo('worker_2', 'Hello worker_2');
            });
            $worker_id = $p->getWorkerId();
            go(function()use($p,$worker_id){
                while(1){
                    $l = $p->recv();
                    echo "worker_1[{$worker_id}]接收数据：",$l,PHP_EOL;
                }
            });
        },2);
        $p->register('worker_2', function(DMProcess $p = null)
        {
            Timer::tick(3000, function()use($p){
                $p->sendTo('worker_1', 'Hello worker_1');
            });

            $worker_id = $p->getWorkerId();
            go(function()use($p,$worker_id){
                while(1){
                    $l = $p->recv();
                    echo "worker_2[{$worker_id}]接收数据：",$l,PHP_EOL;
                }
            });

        },3);


        $p->start();
        die;
        set_error_handler(function () {
//            print_r(func_get_args());
            echo "error\r";
        });
        set_exception_handler(function () {
//            print_r(func_get_args());
            echo "ex\r";
        });

        if (!function_exists('https_request')) {
            function https_request($url, $data = null)
            {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                // curl_setopt($curl, CURLOPT_REFERER, 'http://www.layui.com');
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, FALSE);
                curl_setopt($curl, CURLOPT_HTTPHEADER, [
                    'User-Agent:Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
                ]);
                if (!empty($data)) {
                    curl_setopt($curl, CURLOPT_POST, 1);
                    curl_setopt($curl, CURLOPT_POSTFIELDS, $data);
                }
                curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
                $output = curl_exec($curl);
                $output = curl_getinfo($curl);
                var_dump($output);
                die;
//                return curl_errno($curl);
                curl_close($curl);
                // $output = json_decode($output,true);
                return $output;
            }
        }

//        https_request("http://l.cn");
//        die;

        try {

//            $a = https_request("https://www.zhipin.com/c101280600-p100103/%E5%8D%97%E5%B1%B1%E5%8C%BA/");
////            $a = https_request("https://www.baidu.com");
//            print_r($a);
//            die;

            $client = $this->container->get(ClientFactory::class)->create([
                'headers' => [
                    \GuzzleHttp\RequestOptions::HEADERS => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',

                ],
//                'allow_redirects' => false
            ]);

//            $client = new \GuzzleHttp\Client([
//                'headers' => [
//                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36',
//                    \GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => false,
//                ],
//            ]);
//
//            $resp = $client->get("https://www.cnoz.org/0_1/");
//            $resp = $client->get("http://www.173kt.net/book/16688/");
            $resp = $client->get("https://www.zhipin.com/c101280600-p100103/%E5%8D%97%E5%B1%B1%E5%8C%BA/");
//            $resp = $client->get("http://l.cn", [\GuzzleHttp\RequestOptions::ALLOW_REDIRECTS => false]);
//            print_r($resp->getHeaders());
            file_put_contents('a.log', $resp->getBody()->getContents());
            die;
            $resp = $client->get("https://www.zhipin.com/web/common/security-check.html?seed=2t%2BIsPTPJ4rV%2FkDu4%2BYQ3aX5I7MdONt6LDwVyqMxwxE%3D&name=f5e6fed3&ts=1585722606822&callbackUrl=%2Fc101280600-p100103%2F%25e5%258d%2597%25e5%25b1%25b1%25e5%258c%25ba%2F&srcReferer=");
            print_r($resp->getHeaders());
            die;

            $a = microtime(true);
            $file = 'a.log';

            $str = file_get_contents($file);
            echo mb_detect_encoding($str, array("ASCII", "UTF-8", "GB2312", "GBK", "BIG5"));
            die;
//            if ($from_encode = mb_detect_encoding($str, array("ASCII", "UTF-8", "GB2312", "GBK", "BIG5"))) {
//                $str = iconv($from_encode, "UTF-8//IGNORE", $str);
//            }

            $dom = HtmlDomParser::str_get_html($str);
            $b = microtime(true);
//            print_r($dom);
//            $a = $dom->find("div");
//            $a = $dom->firstChild();
//            print_r($a);

            $listDom = $dom->getElementById("list");
            if (empty($listDom)) {
                echo 'err', "\r";
            }

            $sd = $listDom->find('dl a');

            foreach ($sd as $item) {
                dd($item->text());
            }


            dd($b - $a);
        } catch (\Throwable $e) {
            print_r($e->getMessage());
        }


    }


    protected function runProcess()
    {
        if ($this->workerId == 0) {
            \Swoole\Timer::tick(3000, function () {
                $process = $this->pool->getProcess($this->workerId);
                $pid = $process->pid;
//                echo "main:{$pid}\n";
                /** @var \Swoole\Process $p */
                $p = $this->pool->getProcess(1);
                $sock = $p->exportSocket();
                $sock->send("Hello task");
                echo "task向main写数据\n";
            });

            go(function(){
                /** @var \Swoole\Process $p */
                $p = $this->pool->getProcess(0);
                $sock = $p->exportSocket();
                while(1){
                    $data = $sock->recv();
                    echo "main:接收到数据：{$data}\n";
                }
            });

        } else {
            \Swoole\Timer::tick(4000, function () {
                $process = $this->pool->getProcess($this->workerId);
                $pid = $process->pid;
//                echo "task:{$pid}\n";

                /** @var \Swoole\Process $p */
                $p = $this->pool->getProcess(0);
                $sock = $p->exportSocket();
                $sock->send("Hello main");
                echo "task向main写数据\n";
            });
            go(function(){
                /** @var \Swoole\Process $p */
                $p = $this->pool->getProcess(1);
                $sock = $p->exportSocket();
                while(1){
                    $data = $sock->recv();
                    echo "task:接收到数据：{$data}\n";
                }
            });
        }
    }
}
