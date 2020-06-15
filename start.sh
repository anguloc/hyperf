#!/usr/bin/env bash
nohup php bin/hyperf.php start &>/var/www/html/hyperf/runtime/logs/run.log 2>&1 &