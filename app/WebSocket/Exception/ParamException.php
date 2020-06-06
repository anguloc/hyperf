<?php

declare(strict_types=1);

namespace App\WebSocket\Exception;

use App\Constants\ErrorCode;
use App\Exception\Exception;

class ParamException extends Exception
{
    public function __construct()
    {
        parent::__construct(ErrorCode::PARAM_ERROR);
    }

}
