<?php

namespace App\Spider\Lib;

class QiDianToken
{
    const CODE_URL = "https://www.qidian.com/ajax/Help/getCode?_csrfToken=";

    const CACHE_KEY = 'qidian_token';
    const TIMEOUT = 3600 * 8;

    public static function getToken()
    {
        $redis = redis();
        $cache_key = self::CACHE_KEY;
        $ttl = self::TIMEOUT;

        if ($redis->exists($cache_key)) {
            return $redis->get($cache_key);
        }
        $token = self::getNewToken();
        if ($token === false) {
            return false;
        }
        $redis->set($cache_key, $token, ['nx', 'ex' => $ttl]);
        return $token;
    }

    private static function getNewToken()
    {
        $client = guzzle([
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36'
            ],
        ]);

        $resp = $client->get(self::CODE_URL);
        if ($resp->getStatusCode() != 200) {
            return false;
        }
        $cookie = implode(';', $resp->getHeader('set-cookie'));
        $b = preg_match("/_csrfToken=(.*);/isxU", $cookie, $match);
        if ($b === false || !isset($match[1])) {
            return false;
        }
        return $match[1];
    }
}