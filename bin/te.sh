#!/usr/bin/env bash
# 用于测试demo

BASE_PATH=$(cd $(dirname $(dirname $0)); pwd)

echo "base path:" $(cd $(dirname $0); pwd)

php -d swoole.use_shortname=Off ${BASE_PATH}/bin/hyperf.php test