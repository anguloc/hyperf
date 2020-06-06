<?php

namespace App\WebSocket\Controller;

use App\Amqp\Producer\NMessageProducer;
use App\Model\NMessage;
use App\WebSocket\Exception\CloseException;
use App\WebSocket\Exception\LogicException;
use App\WebSocket\Exception\ParamException;
use App\WebSocket\Lib\Constants;
use App\WebSocket\Lib\Core;
use App\WebSocket\Lib\User;
use Hyperf\Utils\Context;
use Hyperf\WebSocketServer\Context as WsContext;

class Admin extends Controller
{
    public function __construct()
    {
        $uid = WsContext::get(Constants::FD_INFO_UID_DDL);
        if (!$uid || $uid != User::ADMIN_USER) {
            throw new CloseException();
        }
    }

    public function sendToUid()
    {
        $req = Context::get(Constants::REQUEST);

        $send_uid = $req['uid'] ?? null;
        $opcode = $req['opcode'] ?? null;
        $message = $req['message'] ?? null;

        if (!$send_uid || !$opcode || empty($message)) {
            return false;
        }

        // 不给admin推送消息
        if ($send_uid == User::ADMIN_USER) {
            return false;
        }

        NMessage::insert([
            'from_uid' => WsContext::get(Constants::FD_INFO_UID_DDL),
            'to_uid' => $send_uid,
            'opcode' => $opcode,
            'content' => json_encode($message, JSON_UNESCAPED_UNICODE),
            'add_time' => time(),
        ]);

        Core::sendToUid($send_uid, $opcode, $message);

        return true;
    }


}