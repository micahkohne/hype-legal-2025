<?php

/**
 * ImageUtility Service Traits - ColourManagementTrait
 * ===================================================
 * A collection of traits for the ImageUtility service
 * to manipulate image colours.
 * =============================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.2
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.4.14
 */

namespace JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits;

// Imagine API
use Imagine\Image\Palette\Color\ColorInterface;
use Imagine\Image\Palette;

trait ColourManagementTrait {

    /**
     * Utility function: Converts rgb or rgba into GdImage three / four digit colour
     *
     * @param string $colour_string
     * @return array|bool $colours
     */
    public function convert_rgba_to_GdImage_format(string $colour_string): array|bool
    {
        if (preg_match('/rgba\((.*)\)/', $colour_string, $matches)) {
            $values    = explode(',', $matches[1]);
            $values[3] = (int) ((1 - $values[3]) * 127);
            return $values;
        }
        elseif (preg_match('/rgb\((.*)\)/', $colour_string, $matches)) {
            $values = explode(',', $matches[1]);
            // set opacity value to 1 to imply opaque and so return four values
            $values[3] = 127;
            return $values;
        }
        else {
            return false;
        }
    }

    /**
     * Utility function: Converts rgb or rgba into Imagine Color Palette
     *
     * @param string $colour_string
     * @return ColorInterface|bool $colours
     */
    public function convert_rgba_to_Imagine_RGB(string $colour_string): bool|ColorInterface
    {
        if (preg_match('/rgba\((.*)\)/', $colour_string, $matches)) {
            $values    = explode(',', $matches[1]);
            $values[3] = (int) ($values[3] * 100);
        }
        elseif (preg_match('/rgb\((.*)\)/', $colour_string, $matches)) {
            $values = explode(',', $matches[1]);
            // set opacity value to 1 to imply opaque and so return four values
            $values[3] = 100;
        }
        else {
            return false;
        }
        $colors = [[(int) $values[0], (int) $values[1], (int) $values[2]], (int) $values[3]];

        return (new Palette\RGB())->color(...$colors);
    }

    /**
     * Utility function: convert an hsl value to an RGB format
     * from https://gist.github.com/brandonheyer/5254516
     * 
     * @param float $h // H value in HSL
     * @param float $s // S value in HSL
     * @param float $l // L value in HSL
     * @return array
     */
    function hslToRgb(float $h, float $s, float $l): array
    {

        $h = min(max($h, 0), 360);
        $s = min(max($s, 0), 1);
        $l = min(max($l, 0), 1);

        $r = 0;
        $g = 0;
        $b = 0;

        $c = (1 - abs(2 * $l - 1)) * $s;
        $x = $c * (1 - abs(fmod(($h / 60), 2) - 1));
        $m = $l - ($c / 2);

        if ($h < 60) {
            $r = $c;
            $g = $x;
            $b = 0;
        }
        else if ($h < 120) {
            $r = $x;
            $g = $c;
            $b = 0;
        }
        else if ($h < 180) {
            $r = 0;
            $g = $c;
            $b = $x;
        }
        else if ($h < 240) {
            $r = 0;
            $g = $x;
            $b = $c;
        }
        else if ($h < 300) {
            $r = $x;
            $g = 0;
            $b = $c;
        }
        else {
            $r = $c;
            $g = 0;
            $b = $x;
        }

        $r = ($r + $m) * 255;
        $g = ($g + $m) * 255;
        $b = ($b + $m) * 255;

        return array(floor($r), floor($g), floor($b));
    }

    /**
     * Utility function: convert an rgb value to an HSL format
     * from https://gist.github.com/brandonheyer/5254516
     * 
     * @param int $r // R value in RGB
     * @param int $g // G value in RGB
     * @param int $b // B value in RGB
     * @return array
     */
    public function rgbToHsl(int $r, int $g, int $b): array
    {
        // Normalize RGB values to the range 0-255
        $r = min(max($r, 0), 255) / 255;
        $g = min(max($g, 0), 255) / 255;
        $b = min(max($b, 0), 255) / 255;
    
        $max = max($r, $g, $b);
        $min = min($r, $g, $b);
    
        $h = 0;
        $s = 0;
        $l = ($max + $min) / 2;
        $d = $max - $min;
    
        if ($d != 0) {
            $s = $d / (1 - abs(2 * $l - 1));
    
            switch ($max) {
                case $r:
                    $h = 60 * fmod((($g - $b) / $d), 6);
                    if ($b > $g) {
                        $h += 360;
                    }
                    break;
    
                case $g:
                    $h = 60 * (($b - $r) / $d + 2);
                    break;
    
                case $b:
                    $h = 60 * (($r - $g) / $d + 4);
                    break;
            }
        }
    
        return [round($h, 3), round($s, 3), round($l, 3)];
    }

    /**
     * Utility function: Normalises colour strings / rgba forms to Imagine RGB colour pallette 
     * If colour not valid or not set returns default background colour
     * Uses some code inspired by that found here https://mekshq.com/how-to-convert-hexadecimal-color-code-to-rgb-or-rgba-using-php
     *
     * @param string $colour
     * @param float $opacity
     * @return ColorInterface|string
     */
    public function validate_colour_string(?string $colour = null, float $opacity = 1): ColorInterface|string
    {
        // Check to see if we have been here before... 
        if (! is_string($colour)) {
            return $colour;
        }

        $default = isset(static::$valid_params['bg_color']) && static::$valid_params['bg_color'] != '' ? static::$valid_params['bg_color'] : ee('jcogs_img:Settings')::$settings['img_cp_default_bg_color'];

        //Use default if no color provided
        $colour = $colour ?? $default;

        //Sanitize $colour if "#" is provided 
        // Need to check for hex and rgb and rgba forms.
        if (stripos($colour, '#') !== false) {
            $colour = substr($colour, 1);
        }

        // Check to see if colour is in rgb(a) format
        // If opacity given use that rather than any opacity given in call
        if (strtolower(substr($colour, 0, 4)) == 'rgba') {
            if (preg_match('/rgba\((.*)\)/', $colour, $matches) == 0) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_invalid_colour_string'), $colour);
                return $this->validate_colour_string($default);
            }
            $rgb = explode(',', $matches[1]);
            if (count($rgb) == 4) {
                $opacity = trim(array_pop($rgb));
            }
            else {
                $opacity = $opacity ?? 1;
            }
        }
        elseif (strtolower(substr($colour, 0, 3)) == 'rgb') {
            // Check to see if colour is in rgb format
            if (preg_match('/rgb\((.*)\)/', $colour, $matches) == 0) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_invalid_colour_string'), $colour);
                return $this->validate_colour_string($default);
            }
            $rgb = explode(',', $matches[1]);
        }
        elseif (strlen($colour) == 8) {
            //Check if colour is in hexadecimal format and has 8, 6, 4 or 3 characters and get values
            //If colour has 8 or 4 then use that in preference to any opacity value given in call
            $rgb     = array_map('hexdec', array($colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5]));
            $opacity = round(hexdec($colour[6] . $colour[7]) / 255, 2);
        }
        elseif (strlen($colour) == 6) {
            $rgb = array_map('hexdec', array($colour[0] . $colour[1], $colour[2] . $colour[3], $colour[4] . $colour[5]));
        }
        elseif (strlen($colour) == 4) {
            $rgb     = array_map('hexdec', array($colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2]));
            $opacity = round(hexdec($colour[4] . $colour[4]) / 255, 2);
        }
        elseif (strlen($colour) == 3) {
            $rgb = array_map('hexdec', array($colour[0] . $colour[0], $colour[1] . $colour[1], $colour[2] . $colour[2]));
        }
        else {
            return $this->validate_colour_string($default);
        }

        // Normalize rgb values
        $rgb = array_map($this->_normalizeRgbValue(...), $rgb);

        // Normalize opacity value
        $opacity = max(0, min(1, $opacity));

        // Now scale opacity to 0-100 range to suit Imagine library
        $opacity = (int) round($opacity * 100, 0);
        return (new Palette\RGB())->color([$rgb[0], $rgb[1], $rgb[2]], $opacity);
    }

    private function _normalizeRgbValue(int $value): int
    {
        return max(0, min(255, $value));
    }
}