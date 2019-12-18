<?php

namespace App\Util;

use Hyperf\Utils\ApplicationContext;
use Hyperf\Logger\LoggerFactory;
use Psr\Log\LoggerInterface;

class Logger
{
    public static function get(string $name = '', string $group = 'default'):LoggerInterface
    {
        return ApplicationContext::getContainer()->get(LoggerFactory::class)->get($name, $group);
    }
}