<?php
/**
 * Created by PhpStorm.
 * User: fehrim
 * Date: 2018/11/1
 * Time: 21:30
 */

namespace Frame\Services\Protocols;

/**
 * Frame Protocol.
 */
class Frame
{
    /**
     * Check the integrity of the package.
     *
     * @param string        $buffer
     * @return int
     */
    public static function input($buffer)
    {
        if (strlen($buffer) < 4) {
            return 0;
        }
        $unpack_data = unpack('N'.'total_length', $buffer);
        return $unpack_data['total_length'];
    }

    /**
     * Decode.
     *
     * @param string $buffer
     * @return string
     */
    public static function decode($buffer)
    {
        return substr($buffer, 4);
    }

    /**
     * Encode.
     *
     * @param string $buffer
     * @return string
     */
    public static function encode($buffer)
    {
        $total_length = 4 + strlen($buffer);
        return pack('N', $total_length) . $buffer;
    }
}
