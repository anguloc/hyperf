<?php

namespace App\Spider\Template;


use App\Exception\ParamException;

class QiDianIndex implements SpiderTemplate
{
    public function parse(Array $data)
    {
        if (empty($data) || !is_array($data) || !isset($data['data'])) {
            throw new ParamException();
        }
        $data = $data['data'];

        $res = [];
        foreach ($data['vs'] as $datum) {
            if (!isset($datum['cs'])) {
                continue;
            }

            foreach ($datum['cs'] as $item) {
                $res[] = $item['cN'] ?? 'fail title';
            }
        }

        return $res;
    }
}