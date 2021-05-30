<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://hyperf.wiki
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf/hyperf/blob/master/LICENSE
 */
use Hyperf\HttpServer\Router\Router;

Router::addRoute(['GET', 'POST', 'HEAD'], '/', 'App\Controller\IndexController@index');
Router::addRoute(['GET', 'POST'], '/getToken', 'App\Controller\IndexController@getToken');
Router::addRoute(['GET', 'POST'], '/sendToUid', 'App\Controller\WSAdminController@sendToUid');

Router::get('/favicon.ico', function () {
    return '';
});

Router::addServer('ws', function () {
    Router::get('/', App\WebSocket\RegisterService::class);
    Router::get('/term', App\WebSocket\RegisterService::class);
});

