<?php

namespace App\WebSocket\Controller;


use App\Util\Logger;

abstract class Controller
{

    protected static function log($message, array $context = array())
    {
        Logger::get('ws:', WS_LOG)->info($message, $context);
    }

}