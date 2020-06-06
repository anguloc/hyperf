<?php

namespace App\WebSocket\Controller;

use App\Model\NMessage;
use App\WebSocket\Lib\Constants;
use App\WebSocket\Lib\Core;
use App\WebSocket\Lib\User;
use Hyperf\Utils\Context;
use Hyperf\WebSocketServer\Context as WsContext;

class Common extends Controller
{

    public function ping()
    {
        $req = Context::get(Constants::REQUEST);

        Core::send([
            'pong' => 1,

        ]);

        return true;
    }


}