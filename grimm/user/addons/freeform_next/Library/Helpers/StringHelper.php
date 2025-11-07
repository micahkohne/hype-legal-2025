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

class StringHelper
{
    /**
     * Replaces all of "{someKey}" occurrences in $string
     * with their respective value counterparts from $values array
     *
     * @param string $string
     *
     * @return string
     */
    public static function replaceValues($string, array $values)
    {
        foreach (self::flattenArrayValues($values) as $key => $value) {
            $string = preg_replace("/\{$key\}/", $value, $string);
        }

        return $string;
    }

    /**
     * @return array
     */
    public static function flattenArrayValues(array $values): array
    {
        $return = [];

        foreach ($values as $key => $value) {
            if (is_array($value)) {
                $value = implode(", ", $value);
            }

            $return[$key] = $value;
        }

        return $return;
    }

    /**
     * Splits an underscored of camelcased string into separate words
     *
     * @param string $string
     *
     * @return string
     */
    public static function humanize($string): string
    {
        $string = trim(strtolower(preg_replace(['/([A-Z])/', "/[_\\s]+/"], ['_$1', ' '], $string)));

        return $string;
    }

    /**
     * Turns every first letter in every word in the string into a camel cased letter
     *
     * @param string $string
     * @param string $delimiter
     *
     * @return string
     */
    public static function camelize($string, $delimiter = " "): string
    {
        $stringParts = explode($delimiter, $string);
        $camelized   = array_map("ucwords", $stringParts);

        return implode($delimiter, $camelized);
    }

    /**
     * @param string $string
     *
     * @return mixed|string
     */
    public static function toCamelCase($string, array $noStrip = []): string
    {
        // non-alpha and non-numeric characters become spaces
        $string = preg_replace('/[^a-z0-9' . implode("", $noStrip) . ']+/i', ' ', $string);
        $string = trim($string);
        // uppercase the first character of each word
        $string = ucwords($string);
        $string = str_replace(' ', '', $string);
        $string = lcfirst($string);

        return $string;
    }

    /**
     * Truncates a given string if it exceeds the length of $truncateLength and $truncator length combined
     * Returns the truncated string
     *
     * @param int    $truncateLength
     *
     * @return string
     */
    public static function truncateString($string, $truncateLength = 30, string $truncator = '...'): string
    {
        $string       = trim($string);
        $stringLength = strlen($string);

        if ($stringLength > $truncateLength + strlen($truncator)) {
            $string = trim(substr($string, 0, $truncateLength)) . $truncator;
        }

        return $string;
    }

    /**
     * @param string       $glue
     * @param array|string $data
     *
     * @return string
     */
    public static function implodeRecursively($glue, $data)
    {
        if (!is_array($data)) {
            return $data;
        }

        $pieces = [];
        foreach ($data as $item) {
            if (is_array($item)) {
                $pieces[] = self::implodeRecursively($glue, $item);
            } else {
                $pieces[] = $item;
            }
        }

        return implode($glue, $pieces);
    }
}
