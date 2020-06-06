<?php

namespace App\WebSocket\Controller;


use App\WebSocket\Lib\Core;

class Home extends Controller
{

    public function __invoke()
    {
        Core::send([
            'init' => 1,

        ]);
    }


}