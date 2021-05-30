<?php

namespace App\WebSocket;

use App\Util\Logger;
use App\WebSocket\Conf\Route;
use App\WebSocket\Exception\CloseException;
use App\WebSocket\Exception\LogicException;
use App\WebSocket\Exception\ParamException;
use App\WebSocket\Lib\Constants;
use App\WebSocket\Lib\Coordinator;
use App\WebSocket\Lib\Core;
use App\WebSocket\Lib\Packet;
use App\WebSocket\Lib\User;
use Hyperf\Contract\OnCloseInterface;
use Hyperf\Contract\OnMessageInterface;
use Hyperf\Contract\OnOpenInterface;
use Hyperf\Utils\Context;
use Hyperf\WebSocketServer\Context as WsContext;
use Swoole\Http\Request;
use Swoole\Server;
use Swoole\Websocket\Frame;
use Swoole\WebSocket\Server as WebSocketServer;
use Psr\Container\ContainerInterface;

class RegisterService implements OnMessageInterface, OnOpenInterface, OnCloseInterface
{

    /**
     * @var ContainerInterface
     */
    protected $container;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        $this->logger = Logger::get('ws:', WS_LOG);
    }

    public function onOpen($server, Request $request): void
    {
        try {
            $fd = $request->fd;
            Coordinator::init($fd);
            defer(function () use ($fd) {
                Coordinator::clear($fd);
            });
            $this->logger->debug("web socket open success,fd: {$fd}");
            User::bind();
            Core::send(create_return(SUCCESS_CODE, ['desc' => 'open success']), Route::LOGIN);
        } catch (\Throwable $e) {
            Core::exceptionHandle($e);
        }
    }

    public function onMessage($server, Frame $frame): void
    {
        try {
            $this->logger->debug("web socket recv data {$frame->data} from fd {$frame->fd}");

            User::changeUid();
            // packet data
            list($opcode, $req) = Packet::decode($frame->data);

            Context::set(Constants::OPCODE, $opcode);
            Context::set(Constants::REQUEST, $req);

            // route
            Core::dispatch();

            Context::destroy(Constants::OPCODE);
            Context::destroy(Constants::REQUEST);

        } catch (\Throwable $e) {
            Core::exceptionHandle($e);
        }
    }

    public function onClose($server, int $fd, int $reactorId): void
    {
        try {
            defer(function () use ($fd) {
                Coordinator::clear($fd);
            });
            Coordinator::sleep($fd);
            $this->logger->debug("web socket close fd:{$fd},reactorId {$reactorId}");
            User::unBind();
        } catch (\Exception $e) {
            $this->logger->error("web socket error ");
        }
    }

}