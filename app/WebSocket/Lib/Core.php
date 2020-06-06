<?php

namespace App\WebSocket\Lib;

use App\Model\NMessage;
use App\Util\Logger;
use App\WebSocket\Conf\Route;
use App\WebSocket\Exception\CloseException;
use App\WebSocket\Exception\LogicException;
use App\WebSocket\Exception\ParamException;
use Psr\Http\Message\ServerRequestInterface;
use Swoole\Server;
use Hyperf\Utils\Context;
use Hyperf\WebSocketServer\Context as WsContext;

class Core
{
    /**
     * ws当前节点发送数据
     *
     * @param $data
     * @param $opcode
     * @param null $fd
     * @return bool|mixed
     */
    public static function send($data, $opcode = null, $fd = null)
    {
        $fd = $fd ?: Context::get(WsContext::FD);
        if (is_array($fd)) {
            foreach ($fd as $item) {
                self::send($data, $opcode, $item);
            }
            return true;
        }
        if (!$fd || $fd <= 0) {
            Logger::get('ws:', WS_LOG)->error("ws推送数据时，fd丢失，数据发送失败" . json_encode($data));
        }
        if (is_null($opcode) && Context::has(Constants::OPCODE)) {
            $opcode = Context::get(Constants::OPCODE, 0);
        }
        $server = server();
        if ($server instanceof \Swoole\WebSocket\Server && $server->exist($fd)) {
            return $server->push($fd, Packet::encode($opcode, $data), WEBSOCKET_OPCODE_BINARY);
        } else {
            return false;
        }
    }

    /**
     * 对uid发送数据
     *
     * @param $uid
     * @param $opcode
     * @param $data
     * @return bool
     */
    public static function sendToUid($uid, $opcode, $data)
    {
        if (is_array($uid)) {
            foreach ($uid as $item) {
                self::sendToUid($item, $opcode, $data);
            }
            return true;
        }
        // TODO 这里需要优化成集群模式
        Logger::get('ws:', WS_LOG)->debug(__FUNCTION__ . ":" . json_encode(func_get_args()));

        // uid在线就推送
        $fds = User::getFdByUid($uid);
        if (!$fds) {
            return false;
        }

        foreach ($fds as $fd) {
            self::send($data, $opcode, $fd);
        }

        return true;
    }

    public static function exceptionHandle(\Throwable $e)
    {
        $trace = json_encode($e->getTrace());
        Logger::get('ws:', WS_LOG)->error("exception error " . get_type($e) . ",{$e->getMessage()},{$e->getCode()},trace:{$trace}");
        self::send(create_return(ERROR_CODE, $e->getMessage(), '', $e->getCode()));
        if ($e instanceof CloseException) {
            defer(function () {
                $fd = Context::get(WsContext::FD);
                if ($fd && $fd > 0) {
                    server()->close($fd);
                }
            });
        }
    }

    /**
     * 运行控制器
     * TODO 如果有需要 可以实现下IOC
     *
     * @return mixed|null
     */
    public static function dispatch()
    {
        $opcode = Context::get(Constants::OPCODE);

        if (!$opcode) {
            return null;
        }

        $callback = Route::get($opcode);
        if (is_array($callback)) {
            list($class, $method) = $callback;
        } elseif (is_string($callback)) {
            $class = $callback;
            $method = '__invoke';
        } else {
            throw new LogicException();
        }
        if (!class_exists($class)) {
            throw new LogicException();
        }

        $obj = new $class();
        if (!method_exists($obj, $method)) {
            throw new LogicException();
        }
        $result = call_user_func([$obj, $method]);

        return $result;
    }


}