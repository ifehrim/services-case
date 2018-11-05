<?php
/**
 * Created by PhpStorm.
 * User: fehrim
 * Date: 2018/11/1
 * Time: 21:30
 */

namespace Frame\Services\Protocols;

/**
 * Text Protocol.
 */
class Text
{
    /**
     * Check the integrity of the package.
     *
     * @param string        $buffer
     * @return int
     */
    public static function input($buffer)
    {
        //  Find the position of  "\n".
        $pos = strpos($buffer, "\n");
        // No "\n", packet length is unknown, continue to wait for the data so return 0.
        if ($pos === false) {
            return 0;
        }
        // Return the current package length.
        return $pos + 1;
    }

    /**
     * Encode.
     *
     * @param string $buffer
     * @return string
     */
    public static function encode($buffer)
    {
        // Add "\n"
        return $buffer . "\n";
    }

    /**
     * Decode.
     *
     * @param string $buffer
     * @return string
     */
    public static function decode($buffer)
    {
        // Remove "\n"
        return trim($buffer);
    }
}
