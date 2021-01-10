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

            $data = ['url' => $url];
            $resp = [];
            if (isset($task['is_need_id']) && $task['is_need_id'] == 1) {
                $time = time();
                $insert_data = [
                    'url' => $data['url'],
                    'content' => '',
                    'add_time' => $time,
                    'update_time' => $time,
                ];
                $data['rid'] = $resp['rid'] = SpidersRequest::insertGetId($insert_data);
            }

            $mq = SpiderProducer::addMessage($data);
            $resp['mq'] = $mq;
            return create_return(SUCCESS_CODE, $resp);
        } catch (\Throwable $e) {
            Logger::get()->error("class:" . __CLASS__ . ",function:" . __FUNCTION__ . ",line:" . __LINE__ . "，" . $e->getMessage());
            return create_return(ERROR_CODE, 'error');
        }
    }


}
