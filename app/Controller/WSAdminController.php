<?php

declare(strict_types=1);

namespace App\Controller;

use App\Amqp\Producer\NMessageProducer;
use App\Exception\ParamException;
use App\WebSocket\Conf\Route;
use App\WebSocket\Lib\Packet;
use App\WebSocket\Lib\User;
use Hyperf\WebSocketClient\ClientFactory as WSClientFactory;

class WSAdminController extends AbstractController
{


    public function sendToUid()
    {
        $uid = $this->request->post("uid");
        $message = $this->request->post("message");
        $opcode = $this->request->post("opcode");
        $token = $this->request->post("token");
        if (!$uid || !$message || !$token || !User::isAdminUser($token)) {
            throw new ParamException();
        }

        $mq = [
            'uid' => $uid,
            'opcode' => $opcode,
            'message' => $message,
        ];

        $rt = NMessageProducer::addMessage($mq);
        return [
            'mq' => $rt,
            'time' => time(),
        ];
    }

}
