#!/usr/bin/env bash
nohup php -d swoole.use_shortname=Off bin/hyperf.php start &>/var/www/html/hyperf/runtime/logs/run.log 2>&1 &