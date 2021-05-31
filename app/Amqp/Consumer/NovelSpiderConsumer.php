<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Amqp\Lib\BaseConsumer;
use App\Amqp\Producer\SpiderProducer;
use App\Exception\ParamException;
use App\Model\SpidersRequest;
use App\Util\Logger;
use App\WebSocket\Conf\Route;
use App\WebSocket\Lib\Packet;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\DbConnection\Db;
use Hyperf\WebSocketClient\ClientFactory as WSClientFactory;
use Swoole\Coroutine\Channel;

/**
 * php -d swoole.use_shortname=Off bin/hyperf.php process:rabbit NovelSpiderConsumer >> ./runtime/logs/spider_request.log
 * @Consumer
 */
class NovelSpiderConsumer extends BaseConsumer
{
    protected $exchange = 'novel_spider';
    protected $queue = 'novel_spider';
    protected $routingKey = 'novel_spider';
    public $nums = 4;
    protected $enable = false;

    protected $coroutine = true;


    public function consume($raw): string
    {
        $data = $this->before($raw);
        if ($data === false) {
            Logger::get()->error("class:" . __CLASS__ . ",function:" . __FUNCTION__ . ",line:" . __LINE__ . "，参数错误，data:" . json_encode($raw, JSON_UNESCAPED_UNICODE));
            return Result::ACK;
        }
        [$rid, $url, $post, $retry_num] = $data;

        $max_request_num = 3;
        $request_num = 0;
        $resp = '';
        while ($request_num < $max_request_num) {
            $request_num++;
            $sleep_expire = mt_rand(1, 3);
            try {
                $resp = http_request($url);
            } catch (\Throwable $e) {
                Logger::get()->error("class:" . __CLASS__ . ",function:" . __FUNCTION__ . ",line:" . __LINE__ . "，请求错误，errmsg:" . $e->getMessage());
            } finally {
                if (!$resp) {
                    sleep($sleep_expire);
                }
            }
        }

        try {
            SpidersRequest::where(['id' => $rid])->update([
                'request_num' => Db::raw("`request_num` + {$request_num}"),
                'content' => $resp,
            ]);
        } catch (\Throwable $e) {
            Logger::get()->error("class:" . __CLASS__ . ",function:" . __FUNCTION__ . ",line:" . __LINE__ . "，参数错误，err:" . $e->getMessage());
        }

        if (!$resp && $retry_num++ < 2) {
            $this->retry([
                'rid' => $rid,
                'url' => $url,
                'post' => $post,
                'retry_num' => $retry_num,
            ]);
        }

        return Result::ACK;
    }

    protected function retry($data)
    {
        if (!SpiderProducer::addMessage($data)) {
            Logger::get()->error("class:" . __CLASS__ . ",function:" . __FUNCTION__ . ",line:" . __LINE__ . "，重新入队列失败，data:" . json_encode($data));
        }
    }

    protected function before($data)
    {
        if (empty($data['url'])) {
            return false;
        }
        if (empty($data['retry_num']) || !is_int($data['retry_num']) || $data['retry_num'] <= 0) {
            $data['retry_num'] = 0;
        }
        if (!isset($data['post'])) {
            $data['post'] = null;
        }

        $time = time();
        if (!isset($data['rid'])) {
//            $task = SpidersRequest::where(['url' => $data['url']])->select('id')->first();
            if (empty($task)) {
                $task = [
                    'url' => $data['url'],
                    'content' => '',
                    'add_time' => $time,
                    'update_time' => $time,
                ];
                try {
                    $task['id'] = SpidersRequest::insertGetId($task);
                } catch (\Throwable $e) {
                    Logger::get()->error("class:" . __CLASS__ . ",function:" . __FUNCTION__ . ",line:" . __LINE__ . "，数据库插入数据错误");
                    return false;
                }
            }
            $data['rid'] = $task['id'];
        }

        return [$data['rid'], $data['url'], $data['post'], $data['retry_num']];
    }

}
