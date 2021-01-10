<?php

namespace App\Spider\Lib;

class QiDianHelper
{
    // 获取code
    const CODE_URL = "https://www.qidian.com/ajax/Help/getCode?_csrfToken=";

    // 目录
    const INDEX_URL = 'https://www.qidian.com/ajax/book/category?_csrfToken=';

    const CACHE_KEY = 'qidian_token';
    const TIMEOUT = 3600 * 8;

    public static function getToken($refresh = false)
    {
        $redis = redis();
        $cache_key = self::CACHE_KEY;
        $ttl = self::TIMEOUT;

        if (!$refresh && $redis->exists($cache_key)) {
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
//        $client->get(self::CODE_URL . $match[1]);
        return $match[1];
    }

    public static function getIndex($book_id)
    {
        if ($book_id<=0) {
            return false;
        }
        $token = self::getToken();
        if (!$token) {
            return false;
        }

        $url = self::INDEX_URL .$token . '&bookId=' . $book_id;
        return http_request($url, [], [
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/80.0.3987.149 Safari/537.36'
            ],
        ]);
    }
}
