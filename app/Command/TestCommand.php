<?php

declare(strict_types=1);

namespace App\Command;

use App\Amqp\Consumer\SpiderConsumer;
use App\Amqp\Producer\MinuteProducer;
use App\Command\Lib\BaseCommand;
use App\Command\Lib\ProcessCommand;
use App\Model\NMessage;
use App\Spider\Lib\QiDianToken;
use App\Util\DelayQueue;
use App\Util\Logger;
use App\WebSocket\Conf\Route;
use App\WebSocket\Controller\Home;
use App\WebSocket\Lib\Packet;
use QL\QueryList;
use Swoole\Coroutine\Context;
use DHelper\RabbitMQ\RabbitMQTask;
use Doctrine\Common\Annotations\AnnotationReader;
use Hyperf\Amqp\Producer;
use Hyperf\Command\Annotation\Command;
use Hyperf\Contract\StdoutLoggerInterface;
use Hyperf\Di\Annotation\AnnotationCollector;
use Hyperf\Di\Annotation\Inject;
use Hyperf\Di\ReflectionManager;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Redis\Redis;
use PhpCsFixer\DocBlock\Annotation;
use PhpParser\ParserFactory;
use Psr\Container\ContainerInterface;
use Roave\BetterReflection\Reflection\ReflectionClass;
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
use Hyperf\WebSocketClient\ClientFactory as WSClientFactory;
use Symfony\Component\Finder\Finder;

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
        Timer::tick(3000, function () use ($p) {
//            echo "asd\n";
            $p->sendTo('a', 'Hello func');
        });

        $worker_id = $p->getWorkerId();
        go(function () use ($p, $worker_id) {
            while (1) {
                $l = $p->recv();
                echo "asd[{$worker_id}]接收数据：", $l, PHP_EOL;
            }
        });

    }


    public function getC($b = true)
    {
        /** @var \Hyperf\WebSocketClient\Client $c */
        static $c = null;
        if (!$b) {
            $c = null;
        }
        if ($c) {
            return $c;
        }


        $host = '127.0.0.1:9503/?token=d033e22ae348aeb5660fc2140aec35850c4da997';
        // 通过 ClientFactory 创建 Client 对象，创建出来的对象为短生命周期对象
        $f = container()->get(WSClientFactory::class);

        $c = $f->create($host, false);

        $data = $c->recv(2);
        $data = Packet::decode($data->data);

        print_r($data);
        $c = null;


        \Swoole\Coroutine::sleep(1);

        return $c;
    }

    function cal($order_num)
    {
        $ladder = [
            5 => 30,
            20 => 15,
            50 => 10,
            100 => 9,
            500 => 8,
            1000 => 7,
            2000 => 6,
            3000 => 5,
            4000 => 4,
            5000 => 3,
            6000 => 2,
        ];
        $price = 0;
        $remain_num = $order_num;
        foreach ($ladder as $k => $v) {
            if ($order_num > $k) {
                $price += $k * $v;
                $remain_num -= $k;
            } else {
                $price += $remain_num * $v;
                $remain_num = 0;
                break;
            }
        }
        if ($remain_num > 0) {
            $price += $remain_num;
        }

        return $price;
    }

    public function calc($n = 1)
    {
        if ($n <= 1) {
            return '1';
        }

        $before = $this->calc($n - 1);
        $mark = $m = 0;
        $res = '';

        $len = strlen($before);
        for ($i = 0; $i < $len; $i++) {
            $item = $before[$i];
            if ($m == 0 || $m == $item) {
                $m = $item;
                $mark++;
            } elseif ($m != $item) {
                $res .= $mark . $m;
                $m = $item;
                $mark = 1;
            }

            if ($i == $len - 1) {
                $res .= $mark . $m;
            }
        }
        return (string)$res;
    }

    public function handle()
    {


        $data = [
            'url' => 'http://www.gkfk5.cn',
        ];
        $m = new SpiderConsumer();
        $ret = $m->consume($data);
        return $ret;

        return;

        $this->dsad();

        return;
        go(function(){
            $con = \Swoole\Coroutine::getContext();
            $a = $con['asd'] ?? null;
            var_dump([
                'a',$a
            ]);

            $con['asd'] = 'hdfhdfgfdsg';

            $con = \Swoole\Coroutine::getContext();
            $a = $con['asd'] ?? null;
            var_dump([
                'a',$a
            ]);

            go(function(){
                $con = \Swoole\Coroutine::getContext();
                $a = $con['asd'] ?? null;
                var_dump([
                    'a',$a
                ]);
            });

        });

        return;



//        $file = BASE_PATH . '/../gk/public/a.msp';
////        $b = is_file($file);
//////        var_dump($b);
//////        die;
/////
//
//
//
//
//        $data = file_get_contents($file);


        $data = http_request('http://l.cn/a.msp');



        for ($i = 0; $i < 7; $i++) {
            echo ord($data[$i]);
            echo PHP_EOL;
        }
        echo strlen($data);

        die;

        $data = msgpack_pack(['s' => 1, 'b' => 2,]);


//        for ($i=0;$i<7;$i++){
//            echo ord($data[$i]);
//            echo PHP_EOL;
//        }
//
//
//        echo strlen($data);

        file_put_contents("a.data", $data);
        return;

        echo strlen($this->calc(31));die;


        echo $this->calc(20), PHP_EOL;

        return;


        echo $this->cal(6);
        echo PHP_EOL;
        return;

        $a = ['insert', 'insertGetId', 'getBindings', 'toSql',
            'exists', 'doesntExist', 'count', 'min', 'max', 'avg', 'average', 'sum', 'getConnection',];


        $this->model();

        return;

        $b = NMessage::insert([
            'from_uid' => 1,
        ]);

        var_dump($b);

        return;

//        echo sha1("admin");
        echo sha1("395df8f7c51f007019cb30201c49e884b46b92fa");


        return;

        $a = call_user_func([Home::class, 'asd']);
        return;
        $b = is_callable([Home::class, 'asd']);
        var_dump($b);
        return;
        $sendStr = 'abcaa';
        $cmd = 13;
        $scmd = 564;

//        $a = pack("S2", 1,$scmd);
        $a = pack("C2", 2, $scmd);
        $b = pack('N', $scmd);
//        $b = pack("S",$scmd);

//        $b = pack("C2",0,$scmd) == pack("S2",0,$scmd);
//        var_dump($b);

//        file_put_contents('b.log',$a);

        var_dump(ord($a[0]));
        var_dump(ord($a[1]));

        var_dump(ord($b[0]));
        var_dump(ord($b[1]));
        var_dump(ord($b[2]));
        var_dump(ord($b[3]));

        var_dump(strlen($a));
        var_dump(strlen($b));

        var_dump($a);
        var_dump($b);

        var_dump($a == $b);

        return;

        $a = pack('N', strlen($sendStr) + 2) . pack("C2", 0, $scmd) . $sendStr;
        var_dump(ord($a));
        $header = substr($a, 0, 4);
        $len = unpack("Nlen", $header);
        $len = $len["len"];

        $cmd = unpack("Ccmd/Cscmd", substr($a, 4, 6));
//        $cmd = unpack("Ccmd", substr($a, 4, 6));

        var_dump($len);
        var_dump($cmd);
        var_dump($a);
        return;

        var_dump(function_exists('msgpack_pack'));

        echo 123;
        return;


//        print_r($a);

        return;

        $m = new RabbitMQTask();

        $m->start();

        return;
//        $this->container->get(Producer::class)->produce($message);
        $p = new \DHelper\Process\DMProcess();
        $p->register('worker_1', function (DMProcess $p = null) {
            Timer::tick(5000, function () use ($p) {
                $p->sendTo('worker_2', 'Hello worker_2');
            });
            $worker_id = $p->getWorkerId();
            go(function () use ($p, $worker_id) {
                while (1) {
                    $l = $p->recv();
                    echo "worker_1[{$worker_id}]接收数据：", $l, PHP_EOL;
                }
            });
        }, 2);
        $p->register('worker_2', function (DMProcess $p = null) {
            Timer::tick(3000, function () use ($p) {
                $p->sendTo('worker_1', 'Hello worker_1');
            });

            $worker_id = $p->getWorkerId();
            go(function () use ($p, $worker_id) {
                while (1) {
                    $l = $p->recv();
                    echo "worker_2[{$worker_id}]接收数据：", $l, PHP_EOL;
                }
            });

        }, 3);


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

    protected function model()
    {
//        $r = ReflectionManager::reflectClass(\Hyperf\Database\Model\Builder::class);
        $r = ReflectionManager::reflectClass(\Hyperf\Database\Query\Builder::class);

        foreach ($r->getMethods() as $item) {
//            echo $item->getName(),PHP_EOL;
            $name = $item->getName();
            if (substr($name, 0, 2) == '__') {
                continue;
            }


            if ($item->isPublic()) {
                if (!in_array($name, [
                    'find',
                ])) {
//                    continue;
                }
                $is_static = $item->isStatic() ? 'static ' : '';
//                $return = $item->getReturnType() ? "{$item->getReturnType()} " :'';
                $params = $item->getParameters();

                $doc = $item->getDocComment();

                $return = '';
                if (strpos($doc, "@return") !== false) {
                    preg_match("/(.*)@return\s(.*)/", $doc, $match);
                    $return = $match[2] . " ";
                }

                $p = [];
                foreach ($params as $param) {
//                    var_dump([
//                        $param->getDefaultValue(),
//                        $param->getDefaultValueConstantName(),
//                        $param->isDefaultValueConstant(),
//                        $param->isDefaultValueAvailable(),
//                    ]);
                    $default = '';
                    if ($param->isDefaultValueAvailable()) {
//                        var_dump($param->isDefaultValueConstant());
                        if ($param->isDefaultValueConstant()) {
                            echo $name, PHP_EOL;
                            $default = " = {$param->getDefaultValue()}";
                        } else {
                            $type = gettype($param->getDefaultValue());
//                            var_dump($type);
                            if ($type == 'array') {
                                $type = '[]';
                            } elseif ($type == 'NULL') {
                                $type = 'null';
                            } elseif ($type == 'integer') {
                                $type = "{$param->getDefaultValue()}";
                            } elseif ($type == 'string') {
                                $type = "'{$param->getDefaultValue()}'";
                            } else {
                                $type = 'ee';
                            }
                            $default = " = {$type}";
                        }
                    }
                    $p[] = "{$param->getType()} \${$param->getName()}{$default}";
//                    echo $param->getType();
//                    echo $param->getName();
                }
                $ps = empty($p) ? '' : implode(', ', $p);
//                var_dump($ps);
//                die;
                echo " * @method {$is_static}{$return}{$name}({$ps})", PHP_EOL;
            }
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

            go(function () {
                /** @var \Swoole\Process $p */
                $p = $this->pool->getProcess(0);
                $sock = $p->exportSocket();
                while (1) {
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
            go(function () {
                /** @var \Swoole\Process $p */
                $p = $this->pool->getProcess(1);
                $sock = $p->exportSocket();
                while (1) {
                    $data = $sock->recv();
                    echo "task:接收到数据：{$data}\n";
                }
            });
        }
    }

    function br2nl($string)
    {
        return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
    }

    public function dsad()
    {

        $ql = new QueryList();

        $finder = new Finder();

        $files = $finder->files()->in([
            BASE_PATH . '/runtime/temp/dd',
        ])->name("*.html");

        foreach ($files as $k=>$file) {
            if (!$file instanceof \Symfony\Component\Finder\SplFileInfo || !$html = $file->getContents()) {
                continue;
            }
            $html = $file->getContents();
            $html =$this->br2nl($html);
            $html = strip_tags($html);

            file_put_contents($k . '.txt',$html);


        }

        echo 1;
        die;
    }
}
