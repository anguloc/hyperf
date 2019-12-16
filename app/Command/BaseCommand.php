<?php

declare(strict_types=1);

namespace App\Command;

use Hyperf\Command\Command as HyperfCommand;
use Hyperf\Di\Annotation\Inject;
use Psr\EventDispatcher\EventDispatcherInterface;

abstract class BaseCommand extends HyperfCommand
{
    /**
     * @Inject()
     * @var EventDispatcherInterface
     */
//    protected $eventDispatcher;

}
