<?php

declare(strict_types=1);

namespace App\Amqp\Consumer;

use App\Amqp\Lib\BaseConsumer;
use App\Util\Logger;
use App\WebSocket\Conf\Route;
use App\WebSocket\Lib\Packet;
use Hyperf\Amqp\Result;
use Hyperf\Amqp\Annotation\Consumer;
use Hyperf\Amqp\Message\ConsumerMessage;
use Hyperf\WebSocketClient\ClientFactory as WSClientFactory;
use Swoole\Coroutine\Channel;

/**
 * php bin/hyperf.php process:rabbit NMessageConsumer >> ./runtime/logs/process.log
 * @Consumer
 */
class NMessageConsumer extends BaseConsumer
{
    protected $exchange = 'n_message';
    protected $queue = 'n_message';
    protected $routingKey = 'n_message';
    protected $nums = 1;
    protected $enable = false;

    protected $coroutine = true;

    /**
     * @var \Hyperf\WebSocketClient\Client
     */
    protected $client;

    public function consume($data): string
    {
        $ws = $this->initConnect();
        if (!$ws) {
            Logger::get()->error("class:" . __CLASS__ . ",function:" . __FUNCTION__ . ",line:" . __LINE__ . ",ws初始化失败");
            return Result::NACK;
        }

        $data = Packet::encode(Route::ADMIN_SEND, $data);
        $bool = $this->client->push($data);
        if (!$bool) {
            Logger::get()->error("class:" . __CLASS__ . ",function:" . __FUNCTION__ . ",line:" . __LINE__ . ",ws推送失败");
            return Result::NACK;
        }

        return Result::ACK;
    }

    protected function initConnect()
    {
        $host = $this->getHost();
        $this->client = container()->get(WSClientFactory::class)->create($host);

        // open后server端有逻辑处理，不能立即进行业务 需要等server返回才行
        $data = $this->client->recv(2);
        if (!$data) {
            $this->client->close();
            $this->client = null;
            return false;
        }
        $data = Packet::decode($data->data);
        list($opcode, $data) = $data;
        if (!isset($data['code']) || $data['code'] != SUCCESS_CODE || $opcode != Route::LOGIN) {
            $this->client->close();
            $this->client = null;
            return false;
        }

        return true;
    }

    protected function getHost()
    {
        $token = 'd033e22ae348aeb5660fc2140aec35850c4da997';
        // TODO 这里可以从服务中心里获取
        $host = defined('HOST_2') ? HOST_2 : 0;
        $port = defined('PORT_9') ? PORT_9 : 0;
        return "{$host}:{$port}/?token={$token}";
    }
}
