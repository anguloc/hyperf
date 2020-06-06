<?php

declare(strict_types=1);

namespace App\Controller;

use App\Amqp\Producer\DemoProducer;
use App\Amqp\Producer\NMessageProducer;
use App\Util\Test;
use App\WebSocket\Lib\Constants;
use Hyperf\Amqp\Producer;
use Hyperf\DbConnection\Db;
use Hyperf\Server\ServerManager;
use Hyperf\Utils\Coroutine;
use Hyperf\Task\TaskExecutor;
use Hyperf\Task\Task;
use Hyperf\Utils\Str;

class IndexController extends AbstractController
{
    public function index()
    {
        $user = $this->request->input('user', 'Hyperf');
        $method = $this->request->getMethod();


        $rt = Db::select("show tables");

//        $a = new MinuteProducer([
//            'a' => 1,
//            'b' => 2,
//            'c' => '3',
//            'time' => date('Y-m-d H:i:s'),
//        ]);
//        $a->addMessage();
//        $b = $this->container->get(Producer::class)->produce($a);
//        $b = $b ? 1: 2;

//        $tr = 3600 * 2 + 60 * 13;
//        $tr = 60 * 3;
//        $tr = 0;
////        $tr = 86400 *2 + 3600 * 2 + 600 * 2 + 60 *2;
//        $b = date('Y-m-d H:i:s',time()+$tr);
//        $b = DemoProducer::addMessage([
//            'a' => 1,
//            'b' => 2,
//            'c' => '4',
//            'time' => date('Y-m-d H:i:s'),
//        ],$tr);

//        $b = NMessageProducer::addMessage(['a' => 1,'b' => 2]);

//        $task = container()->get(Test::class);
//        $result = $task->handle(Coroutine::id());

//        $exec = container()->get(TaskExecutor::class);
//        $result1 = $exec->execute(new Task([Test::class, 'handle'], [Coroutine::id()]));

        $aasd = ServerManager::list();

        $ssd = [];
        foreach ($aasd as $item) {
            $ssd[] = get_type($item);
        }

        $result = [
            'c' => $ssd,
            'a' => get_type(container()->get(\Swoole\Server::class)),
        ];


        return [
            'method' => $method,
            'message' => "Hello {$user}.",
            'db' => $rt,
            'b' => $b??false,
            'time' => date('Y-m-d H:i:s'),
            'asd' => $result??1,
            'asd1' => $result1??1,
        ];
    }

    public function getToken()
    {
        $uid = mt_rand(1,10);
        $token = Constants::TOKEN_PREFIX . Str::random(6);
        redis()->set($token, json_encode(['uid' => $uid]), 3600);

        return $token;
    }
}
