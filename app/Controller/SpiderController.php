<?php

declare(strict_types=1);

namespace App\Controller;

use App\Amqp\Consumer\SpiderConsumer;
use App\Amqp\Producer\DemoProducer;
use App\Amqp\Producer\NMessageProducer;
use App\Amqp\Producer\SpiderProducer;
use App\Model\SpidersRequest;
use App\Model\SpidersTask;
use App\Util\Logger;
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

            $url = $task['url'];

            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                return create_return(ERROR_CODE, '格式错误');
            }

            $mq = SpiderProducer::addMessage(['url' => $url]);
            return create_return(SUCCESS_CODE, ['mq' => $mq,]);
        } catch (\Throwable $e) {
            return create_return(ERROR_CODE, 'error');
        }
    }


}
