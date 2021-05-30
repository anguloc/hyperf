#!/usr/bin/env bash
# 用于测试demo

cd $(dirname $0)

php -d swoole.use_shortname=Off $(pwd)/hyperf.php test