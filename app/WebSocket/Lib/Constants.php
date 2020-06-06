<?php

namespace App\WebSocket\Lib;


class Constants
{
    /**
     * 交互数据操作码
     */
    const OPCODE = 'opcode';

    /**
     * ws交互数据
     */
    const REQUEST = 'request';

    /**
     * token前缀
     */
    const TOKEN_PREFIX = 'token_';

    /**
     * uid到fd缓存前缀
     */
    const UID_FD_PREFIX = 'uid_fd_prefix_';

    /**
     * fd信息缓存前缀
     */
    const FD_INFO_PREFIX = 'fd_info_prefix_';

    const FD_INFO_UID_DDL = 'uid';
}