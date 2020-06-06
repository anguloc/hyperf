<?php

declare(strict_types=1);
/**
 * This file is part of Hyperf.
 *
 * @link     https://www.hyperf.io
 * @document https://doc.hyperf.io
 * @contact  group@hyperf.io
 * @license  https://github.com/hyperf-cloud/hyperf/blob/master/LICENSE
 */

namespace App\Constants;

use Hyperf\Constants\AbstractConstants;
use Hyperf\Constants\Annotation\Constants;

/**
 * @Constants
 */
class ErrorCode extends AbstractConstants
{
    /**
     * @Message("Server Error！")
     */
    const SERVER_ERROR = 500;

    /**
     * @Message("提交数据错误或缺失");
     */
    const PARAM_ERROR = 10001;

    /**
     * @Message("服务异常");
     */
    const LOGIC_ERROR = 10002;

    /**
     * @Message("登录认证失败");
     */
    const CLOSE_ERROR = 10003;
}
