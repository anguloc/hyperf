<?php

declare(strict_types=1);

namespace App\Controller;

use App\Amqp\Consumer\SpiderConsumer;
use App\Amqp\Producer\DemoProducer;
use App\Amqp\Producer\NMessageProducer;
use App\Amqp\Producer\SpiderProducer;
use App\Model\SpidersTask;
use App\Util\Test;
use App\WebSocket\Lib\Constants;
use Hyperf\Amqp\Producer;
use Hyperf\DbConnection\Db;
use Hyperf\Server\ServerManager;
use Hyperf\Utils\Coroutine;
use Hyperf\Task\TaskExecutor;
use Hyperf\Task\Task;
use Hyperf\Utils\Str;
use Hyperf\HttpServer\Annotation\AutoController;


/**
 * Class SpiderController
 * @AutoController()
 * @package App\Controller
 */
class SpiderController extends AbstractController
{
    public function addTask()
    {
        try {
            $task = $this->request->input('task');
            if (empty($task)) {
                return create_return(ERROR_CODE, 'param missing');
            }

            $id = SpidersTask::insertGetId([
                'content' => json_encode($task, JSON_UNESCAPED_UNICODE),
                'add_time' => time(),
                'update_time' => time(),
            ]);

            $mq = SpiderProducer::addMessage(['task_id' => $id]);
            return create_return(SUCCESS_CODE, ['id' => $id, 'mq' => $mq,]);
        } catch (\Throwable $e) {
            return create_return(ERROR_CODE, 'error');
        }
    }



}
