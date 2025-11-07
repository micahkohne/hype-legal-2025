<?php
/**
 * Freeform for ExpressionEngine
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2025, Solspace, Inc.
 * @link          https://docs.solspace.com/expressionengine/freeform/v3/
 * @license       https://docs.solspace.com/license-agreement/
 */

namespace Solspace\Addons\FreeformNext\Library\Helpers;

use Hashids\Hashids;

class HashHelper
{
    public const SALT       = "composer";
    public const MIN_LENGTH = 9;

    private static ?Hashids $hashids = null;

    /**
     * @param int $id
     *
     * @return string
     */
    public static function hash($id): string
    {
        return self::getHashids()->encode($id);
    }

    /**
     * @param string $hash
     *
     * @return int
     */
    public static function decode($hash)
    {
        $idList = self::getHashids()->decode($hash);

        return array_pop($idList);
    }

    /**
     * @param string $hash
     *
     * @return array
     */
    public static function decodeMultiple($hash): array
    {
        return self::getHashids()->decode($hash);
    }

    /**
     * @param int   $length
     * @param int   $offset
     * @return string
     */
    public static function sha1(mixed $value, ?int $length = null, ?int $offset = 0): string
    {
        $hash = sha1($value);

        if ($length) {
            return substr($hash, $offset, $length);
        }

        return $hash;
    }

    /**
     * @return Hashids
     */
    private static function getHashids()
    {
        if (is_null(self::$hashids)) {
            self::$hashids = new Hashids(self::SALT, self::MIN_LENGTH);
        }

        return self::$hashids;
    }
}
