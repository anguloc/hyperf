<?php

declare(strict_types=1);

namespace App\Exception;

use App\Constants\ErrorCode;

class ParamException extends Exception
{
    public function __construct()
    {
        parent::__construct(ErrorCode::PARAM_ERROR);
    }

}
