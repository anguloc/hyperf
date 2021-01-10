#!/usr/bin/env bash
nohup php -d swoole.use_shortname=Off $(cd $(dirname $0); pwd)/hyperf.php start &>$(cd $(dirname $(dirname $0)); pwd)/runtime/logs/run.log 2>&1 &