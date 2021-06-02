<?php

namespace App\Spider\Template;


interface SpiderTemplate
{
    public function parse(array $data);
}