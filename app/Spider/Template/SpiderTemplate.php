<?php

namespace App\Spider\Template;


interface SpiderTemplate
{
    public function parse(Array $data);
}