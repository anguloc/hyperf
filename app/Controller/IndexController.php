<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace App\Controller;

use App\Amqp\Producer\DemoProducer;
use Hyperf\Amqp\Producer;
use Hyperf\DbConnection\Db;

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

        $tr = 3600 * 2 + 60 * 13;
        $tr = 60 * 3;
        $b = DemoProducer::addMessage([
            'a' => 1,
            'b' => 2,
            'c' => '4',
            'time' => date('Y-m-d H:i:s'),
        ],$tr);

        return [
            'method' => $method,
            'message' => "Hello {$user}.",
            'db' => $rt,
            'b' => $b??false,
            'time' => date('Y-m-d H:i:s'),
        ];
    }
}
