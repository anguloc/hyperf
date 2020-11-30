<?php

namespace App\WebSocket\Lib;

use App\WebSocket\Exception\CloseException;
use App\WebSocket\Exception\LogicException;
use App\WebSocket\Exception\ParamException;
use Swoole\Server;
use Hyperf\Utils\Context;
use Hyperf\WebSocketServer\Context as WsContext;
use Psr\Http\Message\ServerRequestInterface;


class User
{
    const ADMIN_USER = -1;// admin
    const Z_USER = -2;
    const X_USER = -3;

    protected static $map = [
        '7b2e9f54cdff413fcde01f330af6896c3cd7e6cd' => self::ADMIN_USER,
        'f4f803e882dd4d6efb07041f9fabd00805c47f87' => self::Z_USER,
        'fa60ce20b1442fcdbaa626556aa6cc2afed028ba' => self::X_USER,
    ];

    /**
     * 绑定用户数据
     */
    public static function bind()
    {
        $request = Context::get(ServerRequestInterface::class);
        $query = $request->getQueryParams();
        $token = $query['token'] ?? '';
        $fd = Context::get(WsContext::FD);
        if (empty($token) || !$fd || $fd <= 0) {
            throw new CloseException();
        }

        $sha = sha1($token);
        if (isset(self::$map[$sha])) {
            $user = json_encode([
                'uid' => self::$map[$sha]
            ]);
        } elseif (!$user = redis()->get($token)) {
            throw new CloseException();
        }
        $user = json_decode($user, true);

        if (!isset($user['uid'])) {
            throw new CloseException();
        }

        // TODO 需要增加集群模式

        redis()->sAdd(self::getUidFdPrefix($user['uid']), $fd);
        self::setWSUid($user['uid']);
    }

    /**
     * 解绑用户数据
     *
     * @return bool
     */
    public static function unBind()
    {
        $fd = Context::get(WsContext::FD);
        if (!$fd || $fd <= 0) {
            return false;
        }

        $uid = self::getWSUid();
        self::unsetWSUid();

        if (!$uid) {
            return false;
        }

        // TODO 可以调整成集群模式
        $b = redis()->sRem(self::getUidFdPrefix($uid), $fd);

        return true;
    }

    /**
     * 当前节点内根据uid获取fd
     *
     * @param $uid
     * @return array|bool
     */
    public static function getFdByUid($uid)
    {
        if (!$uid) {
            return false;
        }
        return redis()->sMembers(self::getUidFdPrefix($uid));
    }

    /**
     * 将连接级里面的数据放到当前协程上下文里面来
     */
    public static function changeUid()
    {
        $uid = self::getWSUid();
        if (!$uid) {
            throw new CloseException();
        }
        self::setUid($uid);
    }

    /**
     * 从当前协程中获取uid
     *
     * @return mixed|null|string
     */
    public static function getUid()
    {
        return Context::get(Constants::FD_INFO_UID_DDL);
    }

    /**
     * 给当前协程设置uid
     *
     * @param $uid
     * @return mixed
     */
    public static function setUid($uid)
    {
        return Context::set(Constants::FD_INFO_UID_DDL, $uid);
    }

    /**
     * 获取当前连接级别的uid
     * p.s. 可能会获取不到 因为当前协程一旦yield后可能会触发close
     *
     * @return array|mixed
     */
    protected static function getWSUid()
    {
        return WsContext::get(Constants::FD_INFO_UID_DDL);
    }

    /**
     * 设置当前连接级别的uid
     *
     * @param $uid
     * @return mixed
     */
    protected static function setWSUid($uid)
    {
        return WsContext::set(Constants::FD_INFO_UID_DDL, $uid);
    }

    /**
     * 删除当前连接级别的uid
     */
    protected static function unsetWSUid()
    {
        WsContext::destroy(Constants::FD_INFO_UID_DDL);
    }

    public static function isAdminUser($token)
    {
        if (empty($token)) {
            return false;
        }
        return isset(self::$map[sha1($token)]);
    }

    protected static function getUidFdPrefix($uid)
    {
        return Constants::UID_FD_PREFIX . $uid;
    }

    protected static function getFdInfoPrefix($fd)
    {
        return Constants::FD_INFO_PREFIX . $fd;
    }
}