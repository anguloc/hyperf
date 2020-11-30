<?php

use Hyperf\Server\ServerFactory;
use Hyperf\Utils\ApplicationContext;
use Hyperf\Guzzle\ClientFactory;

if (!function_exists('container')) {
    function container()
    {
        return ApplicationContext::getContainer();
    }
}
if (!function_exists('redis')) {
    function redis()
    {
        return container()->get(\Redis::class);
    }
}
if (!function_exists('server')) {
    function server()
    {
        return container()->get(ServerFactory::class)->getServer()->getServer();
    }
}
if (!function_exists('guzzle')) {
    function guzzle(array $options = []): \GuzzleHttp\Client
    {
        $o = [
            // Disable exception
            GuzzleHttp\RequestOptions::HTTP_ERRORS => false,
            GuzzleHttp\RequestOptions::CONNECT_TIMEOUT => 5,
        ];
        $options = array_merge($o, $options);
        return container()->get(ClientFactory::class)->create($options);
    }
}
if (!function_exists('http_request')) {
    function http_request($url, $data = [], array $options = [])
    {
        /**
         * $mode
         * GuzzleHttp\RequestOptions::JSON
         * GuzzleHttp\RequestOptions::BODY
         * GuzzleHttp\RequestOptions::FORM_PARAMS
         * GuzzleHttp\RequestOptions::MULTIPART
         */
        if (empty($data)) {
            $resp = guzzle($options)->get($url);
        } else {
            $mode = $options['mode'] ?? GuzzleHttp\RequestOptions::JSON;
            $opts = [
                $mode => $data
            ];
            $resp = guzzle($options)->post($url, $opts);
        }

        if ($resp->getStatusCode() != 200) {
            return false;
        }
        return $resp->getBody()->getContents();
    }
}