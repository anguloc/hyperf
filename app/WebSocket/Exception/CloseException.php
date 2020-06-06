<?php

declare(strict_types=1);

namespace App\WebSocket\Exception;

use App\Constants\ErrorCode;
use App\Exception\Exception;

class CloseException extends Exception
{

    public function __construct()
    {
        parent::__construct(ErrorCode::CLOSE_ERROR);
    }
}
