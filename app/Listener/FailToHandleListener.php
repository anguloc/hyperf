<?php

declare(strict_types=1);

namespace App\Listener;

use Hyperf\Event\Annotation\Listener;
use Psr\Container\ContainerInterface;
use Hyperf\Event\Contract\ListenerInterface;
use Hyperf\ExceptionHandler\Formatter\FormatterInterface;
use Hyperf\Logger\LoggerFactory;
use Hyperf\Command\Event\FailToHandle;

/**
 * @Listener
 */
class FailToHandleListener implements ListenerInterface
{
    /**
     * @var ContainerInterface
     */
    private $container;

    /**
     * @var \Psr\Log\LoggerInterface
     */
    protected $logger;


    public function __construct(ContainerInterface $container, LoggerFactory $loggerFactory)
    {
        $this->container = $container;
        $this->logger = $loggerFactory->get('fail to handle listener:');
    }

    public function listen(): array
    {
        return [
            FailToHandle::class,
        ];
    }

    public function process(object $event)
    {
//        $a = get_class($event);
//        $b = $event instanceof FailToHandle;

        if ($event instanceof FailToHandle) {
            $this->logger->info($event->getThrowable()->getMessage());
        }
    }
}
