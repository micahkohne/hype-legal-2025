<?php

/**
 * JCOGS Image Pro - Image Processing Service
 * ==========================================
 * Phase 2: Native implementation for image processing operations
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 2 Native Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use enshrined\svgSanitize\Sanitizer;
use JCOGSDesign\JCOGSImagePro\Library\HaarDetector;
use JCOGSDesign\JCOGSImagePro\Contracts\SettingsInterface;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

/**
 * ImageProcessingService
 * 
 * Provides image processing operations for JCOGS Image Pro.
 * Migrated from ImageProcessingTrait with improved architecture using direct service access.
 */
class ImageProcessingService
{
    private ColourManagementService $colour_service;
    private Utilities $utilities_service;
    private SettingsInterface $settings;

    public function __construct()
    {
        // Use ServiceCache for optimal performance
        $this->colour_service = ServiceCache::colour();
        $this->utilities_service = ServiceCache::utilities();
        $this->settings = ServiceCache::settings();
    }

    /**
     * Calculate anti-aliased value for pixel during combination of two images
     * 
     * Based on algorithm from https://www.exorithm.com/exorithm-execute-algorithm-view-run-algorithm-antialias_pixel/
     * Modified by JCOGS Design for use as a general mask algorithm
     *
     * @param object $image GD image resource
     * @param int $x X coordinate of pixel to be anti-aliased
     * @param int $y Y coordinate of pixel to be anti-aliased
     * @param int|null $colour Anti-aliased pixel colour (default transparent)
     * @return object|bool Modified image or false on failure
     */
    public function antialias_pixel(object $image, int $x, int $y, ?int $colour = null): object|bool
    {
        // Check that X/Y within bounds of image passed
        if ($x >= imagesx($image) || $x < 0 || $y >= imagesy($image) || $y < 0) {
            return $image;
        }
    
        // If colour not set, use the colour of current pixel
        if (!$colour) {
            $colour = imagecolorat($image, $x, $y);
        }
    
        // Get the colour we are going to set pixel to 
        $c = imagecolorsforindex($image, $colour);
        $r = $c['red'];
        $g = $c['green'];
        $b = $c['blue'];
        $t = $c['alpha'];

        // Get image dimensions
        $image_width = imagesx($image);
        $image_height = imagesy($image);
    
        // Get average opacity of surrounding 9 pixels (or whatever number is available)
        $opacity_count = 0;
        $pixel_count = 0;

        for ($i = -1; $i <= 1; $i++) {
            for ($j = -1; $j <= 1; $j++) {
                $pixel_x = $x + $i;
                $pixel_y = $y + $j;
        
                // Check if the pixel is within the image bounds
                if ($pixel_x >= 0 && $pixel_x < $image_width && $pixel_y >= 0 && $pixel_y < $image_height) {
                    // Calculate the opacity of the pixel
                    $opacity = (imagecolorat($image, $pixel_x, $pixel_y) & 0x7F000000) >> 24;
                    $opacity_count += $opacity;
                    $pixel_count++;
                }
            }
        }
        
        // Average opacity - 1 if all pixels opaque, 0 if all transparent
        $average_opacity = $opacity_count / $pixel_count;
    
        // Set the new colour with averaged opacity
        $new_colour = imagecolorallocatealpha($image, $r, $g, $b, (int) $average_opacity);
        imagesetpixel($image, $x, $y, $new_colour);
    
        return $image;
    }

    /**
     * Returns a semi-transparent image to overlay on images when in demo mode
     * The image is saved as a base64 encoded png file, without the mime type and base 64 header
     * added in to make it into a Data URI format image (i.e. 'data:image/png;base64,$imagedata')
     *
     * @return string
     */
    public function demo_image(): string
    {

        return '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';
    }

    /**
     * Checks if image content is HEIC format
     * 
     * Uses method from php-heic-to-jpg: https://github.com/MaestroError/php-heic-to-jpg
     *
     * @param string|null $image Image content
     * @return bool True if HEIC format
     */
    public function detect_heic(?string $image = null): bool
    {
        if (!$image || !is_string($image)) {
            return false;
        }

        $magic_number = strtolower(trim(substr(substr($image, 0, 12), 8)));

        $heic_magic_numbers = [
            'ftyp',
            'heic',
            'heix',
            'hevc',
            'hevx',
            'heim',
            'heis',
            'hevm',
            'hevs',
            'mif1',
            'msf1'
        ];

        return in_array($magic_number, $heic_magic_numbers);
    }

    /**
     * Detect PNG version and transparency information
     * 
     * PNG8 images have indexed color palettes and may use transparency via a tRNS chunk.
     * PNG24 images use full RGB color but lack a distinct transparency layer.
     *
     * @param string $image Image file path or content
     * @return array|bool PNG information array or false on failure
     */
    public function detect_png_version(string $image): array|bool
    {
        // Check if $image is a file path or image content
        if (file_exists($image)) {
            $content = file_get_contents($image);
        } else {
            $content = $image;
        }

        if (!$content) {
            return false;
        }

        // PNG signature check
        if (substr($content, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return false;
        }

        $pos = 8; // Skip PNG signature
        $has_transparency = false;
        $color_type = null;
        $bit_depth = null;

        while ($pos < strlen($content)) {
            // Read chunk length (4 bytes, big-endian)
            if ($pos + 4 > strlen($content)) break;
            $chunk_length = unpack('N', substr($content, $pos, 4))[1];
            $pos += 4;

            // Read chunk type (4 bytes)
            if ($pos + 4 > strlen($content)) break;
            $chunk_type = substr($content, $pos, 4);
            $pos += 4;

            // Process specific chunks
            switch ($chunk_type) {
                case 'IHDR':
                    // Image header chunk
                    if ($chunk_length >= 10) {
                        $ihdr_data = substr($content, $pos, $chunk_length);
                        $bit_depth = ord($ihdr_data[8]);
                        $color_type = ord($ihdr_data[9]);
                    }
                    break;

                case 'tRNS':
                    // Transparency chunk
                    $has_transparency = true;
                    break;

                case 'IEND':
                    // End of image
                    break 2;
            }

            // Skip chunk data and CRC (4 bytes)
            $pos += $chunk_length + 4;
        }

        // Determine PNG type
        $png_type = 'unknown';
        if ($color_type !== null) {
            switch ($color_type) {
                case 0: // Grayscale
                    $png_type = $bit_depth <= 8 ? 'png8' : 'png16';
                    break;
                case 2: // RGB
                    $png_type = 'png24';
                    break;
                case 3: // Indexed
                    $png_type = 'png8';
                    break;
                case 4: // Grayscale + Alpha
                    $png_type = 'png16';
                    $has_transparency = true;
                    break;
                case 6: // RGB + Alpha
                    $png_type = 'png32';
                    $has_transparency = true;
                    break;
            }
        }

        return [
            'type' => $png_type,
            'has_transparency' => $has_transparency,
            'color_type' => $color_type,
            'bit_depth' => $bit_depth
        ];
    }

    /**
     * Detect and sanitize SVG content
     *
     * @param string $image SVG content
     * @return bool|string Sanitized SVG or false on failure
     */
    public function detect_sanitize_svg(string $image): bool|string
    {
        // Simple SVG detection
        if (strpos($image, '<svg') === false) {
            return false;
        }

        try {
            $sanitizer = new Sanitizer();
            $sanitized = $sanitizer->sanitize($image);
            return $sanitized !== false ? $sanitized : false;
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("SVG sanitization failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Generate rotated polygon coordinates
     *
     * @param int $x Center X coordinate
     * @param int $y Center Y coordinate  
     * @param int $radius Radius from center
     * @param int $vertices Number of vertices (default 3 for triangle)
     * @param int $rotation Rotation angle in degrees
     * @return array Array of polygon coordinates
     */
    public function draw_rotated_polygon(int $x, int $y, int $radius, int $vertices = 3, int $rotation = 0): array
    {
        $points = [];
        $angle_step = 360 / $vertices;
        
        for ($i = 0; $i < $vertices; $i++) {
            $angle = deg2rad($i * $angle_step + $rotation);
            $points[] = $x + ($radius * cos($angle));
            $points[] = $y + ($radius * sin($angle));
        }
        
        return $points;
    }

    /**
     * Generate rotated star coordinates
     *
     * @param int $x Center X coordinate
     * @param int $y Center Y coordinate
     * @param int $radius Outer radius
     * @param int $spikes Number of star spikes (default 5)
     * @param float $split Inner/outer radius ratio (default 0.5)
     * @param int $rotation Rotation angle in degrees
     * @return array Array of star coordinates
     */
    public function draw_rotated_star(int $x, int $y, int $radius, int $spikes = 5, float $split = 0.5, int $rotation = 0): array
    {
        $points = [];
        $angle_step = 360 / ($spikes * 2);
        $inner_radius = $radius * $split;
        
        for ($i = 0; $i < $spikes * 2; $i++) {
            $angle = deg2rad($i * $angle_step + $rotation);
            $current_radius = ($i % 2 === 0) ? $radius : $inner_radius;
            
            $points[] = $x + ($current_radius * cos($angle));
            $points[] = $y + ($current_radius * sin($angle));
        }
        
        return $points;
    }

    /**
     * Encode image as base64 string
     *
     * @param object|null $image GD image resource
     * @param string|null $format Output format (jpeg, png, gif, webp)
     * @param string|null $quality Quality for lossy formats
     * @return string Base64 encoded image
     */
    public function encode_base64(?object $image = null, ?string $format = null, ?string $quality = null): string
    {
        if (!$image || !$this->is_gd_image($image)) {
            return '';
        }

        $format = $format ?: 'png';
        $quality = $quality ? (int)$quality : 85;

        ob_start();
        
        switch (strtolower($format)) {
            case 'jpeg':
            case 'jpg':
                imagejpeg($image, null, $quality);
                $mime_type = 'image/jpeg';
                break;
            case 'png':
                imagepng($image, null, floor($quality / 10));
                $mime_type = 'image/png';
                break;
            case 'gif':
                imagegif($image);
                $mime_type = 'image/gif';
                break;
            case 'webp':
                imagewebp($image, null, $quality);
                $mime_type = 'image/webp';
                break;
            default:
                imagepng($image);
                $mime_type = 'image/png';
        }
        
        $image_data = ob_get_contents();
        ob_end_clean();
        
        return 'data:' . $mime_type . ';base64,' . base64_encode($image_data);
    }

    /**
     * Face detection using Haar cascades
     *
     * @param object|null $image GD image resource
     * @param int $sensitivity Detection sensitivity (1-5, higher = more sensitive)
     * @param array|null $cascade Custom cascade data
     * @return array|bool Array of detected faces or false on failure
     */
    public function face_detection(?object $image = null, int $sensitivity = 3, ?array $cascade = null): array|bool
    {
        if (!$image || !$this->is_gd_image($image)) {
            return false;
        }

        try {
            // Check if HaarDetector class exists and is usable
            if (!class_exists('\JCOGSDesign\JCOGSImagePro\Library\HaarDetector')) {
                $this->utilities_service->debug_message("HaarDetector class not available");
                return false;
            }

            $detector = new HaarDetector();
            
            // Placeholder for face detection implementation
            // In a real implementation, this would interface with the actual detection library
            $faces = $detector->detect($image, $cascade, $sensitivity);
            
            if (!is_array($faces)) {
                return false;
            }

            // Process face data
            $collection = [
                'faces' => [],
                'count' => 0,
                'min-x' => null,
                'min-y' => null,
                'max-x' => null,
                'max-y' => null
            ];

            if (!empty($faces)) {
                foreach ($faces as $face) {
                    $collection['faces'][] = $this->_processFaceData($face, $collection);
                }
                $collection['count'] = count($faces);
            }

            return $collection;
            
        } catch (\Exception $e) {
            $this->utilities_service->debug_message("Face detection failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Check if image has transparency
     *
     * @param object $image GD image resource
     * @return bool True if has transparency
     */
    public function hasTransparency($image): bool
    {
        if (!$this->is_gd_image($image)) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Check a sampling of pixels for transparency
        $sample_size = 10; // Check every 10th pixel
        
        for ($x = 0; $x < $width; $x += $sample_size) {
            for ($y = 0; $y < $height; $y += $sample_size) {
                $rgba = imagecolorat($image, $x, $y);
                $alpha = ($rgba & 0x7F000000) >> 24;
                
                // If alpha is not 0, we have transparency
                if ($alpha > 0) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Enhanced imagecopymerge with proper alpha channel handling
     * 
     * This function handles alpha channel blending correctly unlike the standard imagecopymerge
     *
     * @param resource $dst_im Destination image
     * @param resource $src_im Source image  
     * @param int $dst_x Destination X coordinate
     * @param int $dst_y Destination Y coordinate
     * @param int $src_x Source X coordinate
     * @param int $src_y Source Y coordinate
     * @param int $src_w Source width
     * @param int $src_h Source height
     * @param int $pct Opacity percentage (0-100)
     * @return bool|object Modified destination image or false on failure
     */
    public static function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct): bool|object
    {
        if (!is_resource($dst_im) && !($dst_im instanceof \GdImage)) {
            return false;
        }
        
        if (!is_resource($src_im) && !($src_im instanceof \GdImage)) {
            return false;
        }

        // Create a cut resource
        $cut = imagecreatetruecolor($src_w, $src_h);
        
        // Copy relevant section from destination to cut resource
        imagecopy($cut, $dst_im, 0, 0, $dst_x, $dst_y, $src_w, $src_h);
        
        // Copy relevant section from source to cut resource  
        imagecopy($cut, $src_im, 0, 0, $src_x, $src_y, $src_w, $src_h);
        
        // Insert cut resource back into destination
        imagecopymerge($dst_im, $cut, $dst_x, $dst_y, 0, 0, $src_w, $src_h, $pct);
        
        // Clean up
        imagedestroy($cut);
        
        return $dst_im;
    }

    /**
     * Check if image is an animated GIF
     * Detects animated GIF from given image content.
     * Derived from Legacy implementation - uses same simple and reliable approach.
     *
     * @param string $image Image content
     * @return bool True if animated GIF
     */
    public function is_animated_gif(string $image): bool
    {
        if (substr($image, 0, 3) !== "GIF") {
            // Not a GIF!
            return false;
        }
    
        // We use preg_match_all to count the markers for gif frames in the string
        // More than one and we've got an animated gif...
        // The test used is to look for the start of the 'application extension block' which
        // for animated gifs must always start with the bytes 21 F9 - if we get more than one 
        // we must have a layered GIF and so probably an animation.
        // Details here http://giflib.sourceforge.net/whatsinagif/animation_and_transparency.html
    
        $frames = preg_match_all('/\x21\xf9/', $image);
    
        return $frames > 1;
    }

    /**
     * Check if variable is a GD image resource
     *
     * @param mixed $var Variable to check
     * @return bool True if GD image
     */
    public function is_gd_image($var): bool
    {
        // PHP 8+ uses GdImage objects, older versions use resources
        if (class_exists('GdImage')) {
            return $var instanceof \GdImage;
        }
        
        return is_resource($var) && get_resource_type($var) === 'gd';
    }

    /**
     * Check if pixel at coordinates is opaque
     *
     * @param object $image GD image resource
     * @param int $x X coordinate
     * @param int $y Y coordinate
     * @return bool True if pixel is opaque
     */
    public function is_pixel_opaque($image, int $x, int $y): bool
    {
        if (!$this->is_gd_image($image)) {
            return false;
        }

        $width = imagesx($image);
        $height = imagesy($image);

        // Check bounds
        if ($x < 0 || $x >= $width || $y < 0 || $y >= $height) {
            return false;
        }

        $rgba = imagecolorat($image, $x, $y);
        $alpha = ($rgba & 0x7F000000) >> 24;

        // Alpha 0 = opaque, 127 = transparent
        return $alpha === 0;
    }

    /**
     * Wraps a string to a given number of pixels.
     * From here: https://www.php.net/manual/en/function.wordwrap.php#116467
     * Code modified slightly to update to php7.4 compatibility
     *
     * This function operates in a similar fashion as PHP's native wordwrap function; however,
     * it calculates wrapping based on font and point-size, rather than character count. This
     * can generate more even wrapping for sentences with a consider number of thin characters.
     *
     * @static $mult;
     * @param string $text - Input string.
     * @param int $width - Width, in pixels, of the text's wrapping area.
     * @param float $size - Size of the font, expressed in pixels.
     * @param string $font - Path to the typeface to measure the text with.
     * @param float $line_height - line height to use.
     * @return array $return[0] - The original string with wrapping.
     *               $return[1] - The estimated height of the text block based on rows*line_height
     */
    public function pixel_word_wrap(string $text, int $width, float $size, string $font, float $line_height): array
    {
        # Passed a blank value? Bail early.
        if (! $text || strlen($text) == 0)
            return ["", 0];

        # Check if imagettfbbox is expecting font-size to be declared in points or pixels.
        static $mult = 0.8;
        // if (!$mult) {
        //     $mult = version_compare(GD_VERSION, '2.0', '>=') ? .75 : 1;
        // }

        # See if text already fits the designated space without wrapping.
        $box = imageftbbox($size * $mult, 0, $font, $text);
        if (($box[2] - $box[0]) / $mult < $width)
            return [$text, 1];

        # Start measuring each line of our input and inject line-breaks when overflow's detected.
        $output     = '';
        $breakpoint = 0;
        $length     = 0;
        $row_count  = 1;

        $words      = preg_split('/\b(?=\S)|(?=\s)/', $text);
        $word_count = count($words);
        for ($i = 0; $i < $word_count; ++$i) {

            # Newline
            if (PHP_EOL === $words[$i]) {
                $row_count++;
                $length = 0;
            }

            # Strip any leading tabs.
            if (! $length) {
                $words[$i] = preg_replace('/^\t+/', '', $words[$i]);
            }

            $box = imageftbbox($size * $mult, 0, $font, $words[$i], ['linespacing' => $line_height]);
            $m   = ($box[2] - $box[0]);

            # This is one honkin' long word, so try to hyphenate it.
            if (($diff = $width - $m) <= 0) {
                $diff = abs($diff);

                # Figure out which end of the word to start measuring from. Saves a few extra cycles in an already heavy-duty function.
                if ($diff - $width <= 0)
                    for ($s = strlen($words[$i]); $s; --$s) {
                        $box = imageftbbox($size * $mult, 0, $font, substr($words[$i], 0, $s) . '-');
                        if ($width > (($box[2] - $box[0])) + $size) {
                            $breakpoint = $s;
                            break;
                        }
                    }
                else {
                    $word_length = strlen($words[$i]);
                    for ($s = 0; $s < $word_length; ++$s) {
                        $box = imageftbbox($size * $mult, 0, $font, substr($words[$i], 0, $s + 1) . '-');
                        if ($width < (($box[2] - $box[0])) + $size) {
                            $breakpoint = $s;
                            break;
                        }
                    }
                }

                if (isset($breakpoint)) {
                    $w_l = substr($words[$i], 0, $s + 1) . '-';
                    $w_r = substr($words[$i], $s + 1);

                    $words[$i] = $w_l;
                    array_splice($words, $i + 1, 0, $w_r);
                    ++$word_count;
                    $box = imageftbbox($size * $mult, 0, $font, $w_l);
                    $m   = ($box[2] - $box[0]);
                }
            }
            # If there's no more room on the current line to fit the next word, start a new line.
            if ($length > 0 && $length + $m >= $width) {
                $output .= PHP_EOL;
                $row_count++;
                $length = 0;

                # If the current word is just a space, don't bother. Skip (saves a weird-looking gap in the text).
                if (' ' === $words[$i])
                    continue;
            }
            # Write another word and increase the total length of the current line.
            $output .= $words[$i];
            $length += $m;
        }
        # Get dimensions of text box
        $box = imageftbbox($size * $mult, 0, $font, $output, ['linespacing' => $line_height]);
        return [$output, $row_count];
    }

    /**
     * Process individual face data for collection
     *
     * @param object $face Face detection result
     * @param array $collection Reference to collection array
     * @return array Processed face data
     */
    private function _processFaceData($face, array &$collection): array
    {
        $detected_face = [
            'x' => $face->x,
            'y' => $face->y,
            'width' => $face->width,
            'height' => $face->height
        ];
        
        $collection['min-x'] = is_null($collection['min-x']) ? $face->x : min($face->x, $collection['min-x']);
        $collection['min-y'] = is_null($collection['min-y']) ? $face->y : min($face->y, $collection['min-y']);
        $collection['max-x'] = is_null($collection['max-x']) ? $face->x + $face->width : max($face->x + $face->width, $collection['max-x']);
        $collection['max-y'] = is_null($collection['max-y']) ? $face->y + $face->height : max($face->y + $face->height, $collection['max-y']);
        
        return $detected_face;
    }

    // Migration complete - all 17 methods from ImageProcessingTrait successfully migrated
    // Using direct service access pattern for optimal performance
}
