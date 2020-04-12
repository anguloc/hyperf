<?php

namespace App\Spider\Lib;

interface Spider
{
    public function run();
    public function stopRegister(\Closure $func):Spider;

}