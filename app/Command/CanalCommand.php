<?php

declare(strict_types=1);

namespace App\Command;

use App\Command\Lib\BaseCommand;
use Hyperf\Command\Annotation\Command;
use Psr\Container\ContainerInterface;
use xingwenge\canal_php\CanalConnectorFactory;
use xingwenge\canal_php\CanalClient;
use xingwenge\canal_php\Fmt;

/**
 * @Command
 */
class CanalCommand extends BaseCommand
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    protected $coroutine = false;

    protected static $daemon = true;

    protected $nums = 1;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
        parent::__construct();
        $this->setName("canal");
    }

    public function configure()
    {
        $this->setDescription('canal试用');
    }

    public function handle()
    {
        set_error_handler(function(){
            print_r(func_get_args());
        });
        set_exception_handler(function(){
            print_r(func_get_args());
        });


        try {
            $client = CanalConnectorFactory::createClient(CanalClient::TYPE_SOCKET_CLUE);
//            $client = CanalConnectorFactory::createClient(CanalClient::TYPE_SWOOLE);

            $client->connect(HOST_1, 22222);
            // 不知道怎么搞的 密码验证一直有问题
            // https://github.com/alibaba/canal/issues/2447
            // https://github.com/alibaba/canal/issues/1214
            // issue上只有这两个有关，一个是说升级客户端解决，他们是java
            // 一个实际操作没有解决
            $client->checkValid('canal','E3619321C1A937C46A0D8BD1DAC39F93B27D4458');
            $client->subscribe("1001", "example", ".*\\..*");
            # $client->subscribe("1001", "example", "db_name.tb_name"); # 设置过滤

            while (true) {
//                $message = $client->getWithoutAck(100);
                $message = $client->get(100);
                if ($entries = $message->getEntries()) {
                    foreach ($entries as $entry) {
                        Fmt::println($entry);
                    }
                    $client->rollback($message->getId());
                }
                sleep(3);
            }

            $client->disConnect();
        } catch (\Exception $e) {
            echo $e->getMessage(), PHP_EOL;
        }
    }

}
