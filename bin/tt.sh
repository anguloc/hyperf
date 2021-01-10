#!/usr/bin/env bash
# 用于测试demo

if [ ! -n "$1" ]; then
    echo "invalid param"
    exit
fi

php -d swoole.use_shortname=Off hyperf.php $1