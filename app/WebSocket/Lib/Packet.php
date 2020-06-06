<?php

namespace App\WebSocket\Lib;


use App\WebSocket\Exception\ParamException;

class Packet
{
    /*
     前面4字节表示长度
     跟两字节操作码
     再后面就是数据
     */

    /**
     * 打包数据
     *
     * @param $opcode
     * @param $data
     * @return string
     */
    public static function encode($opcode, $data)
    {
//        $data = json_encode($data, JSON_UNESCAPED_UNICODE);
        $data = msgpack_pack($data);
        return pack('N', strlen($data) + 2) . pack("n", $opcode) . $data;
    }

    /**
     * 数据解包
     *
     * @param string $data
     * @return array
     */
    public static function decode(string $data)
    {
        $header = substr($data, 0, 4);
        if (strlen($header) != 4) {
            throw new ParamException();
        }
        $len = unpack("Nlen", $header)['len'];
        $req = substr($data, 6);
        if (strlen($req) + 2 != $len) {
            throw new ParamException();
        }

        return [
            current(unpack('n', substr($data, 4, 6))),
            msgpack_unpack($req)
        ];
    }

}