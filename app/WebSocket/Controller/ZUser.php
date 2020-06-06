<?php

namespace App\WebSocket\Controller;

use App\WebSocket\Exception\CloseException;
use App\WebSocket\Exception\LogicException;
use App\WebSocket\Exception\ParamException;
use App\WebSocket\Lib\Constants;
use App\WebSocket\Lib\User;
use Hyperf\Utils\Context;
use Hyperf\WebSocketServer\Context as WsContext;

class ZUser extends Controller
{
    public function __construct()
    {
        $uid = WsContext::get(Constants::FD_INFO_UID_DDL);
        if (!$uid || $uid != User::Z_USER) {
            throw new CloseException();
        }
    }




}