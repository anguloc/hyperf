<?php

namespace App\Spider;

use App\Spider\Lib\Spider;
use Hyperf\Guzzle\ClientFactory;

class MysteriesLord implements Spider
{
    protected $targetUrl = 'https://book.qidian.com/info/1010868264#Catalog';

    protected $stopClosure;

    public function run()
    {
        try {
            $client = container()->get(ClientFactory::class)->create();
            $resp = $client->get($this->targetUrl);

            $cookie = $resp->getHeader('set-cookie');
//            $cookie

            print_r($cookie);

            $this->stop();

//            file_put_contents('d.log', $resp->getBody()->getContents());
        }catch (\Exception $e){
            dd($e);
        }
    }

    protected function stop()
    {
        call_user_func($this->stopClosure);
    }

    public function stopRegister(\Closure $func):Spider
    {
        $this->stopClosure = $func;
        return $this;
    }
}