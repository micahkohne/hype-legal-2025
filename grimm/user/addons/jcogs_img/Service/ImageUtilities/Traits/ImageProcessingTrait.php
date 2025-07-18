<?php

/**
 * ImageUtility Service Traits - ImageProcessingTrait
 * ==================================================
 * A collection of traits for the ImageUtility service
 * to manipulate images.
 * =============================================
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img
 * @version    1.4.16.1
 * @link       https://JCOGS.net/
 * @since      File available since Release 1.4.14
 */

namespace JCOGSDesign\Jcogs_img\Service\ImageUtilities\Traits;

// enshrined\svgSanitize\Sanitizer;
use enshrined\svgSanitize\Sanitizer;

// Imagine API
use Imagine\Factory;
use Imagine\Gd\Imagine;
use Imagine\Image;
use Imagine\Image\ImageInterface;
use Imagine\Image\Palette;
use Imagine\Image\Point;
use Imagine\Image\PointSigned;


// JCOGS Design
use JCOGSDesign\Jcogs_img\Library\HaarDetector;

trait ImageProcessingTrait {

    /**
     * @var array Settings array from jcogs_img:Settings
     */
    protected array $settings;

    /**
     * Calculate anti-aliased value for pixel during combination of two images
     * https://www.exorithm.com/exorithm-execute-algorithm-view-run-algorithm-antialias_pixel/
     * Modified by JCOGS Design for use as a general mask algorithm
     *
     * @param  object $image
     * @param  int $x // X of pixel to be anti-aliased
     * @param  int $y // Y of pixel to be anti-aliased
     * @param  int|null $colour // Anti-aliased pixel colour (default transparent)
     * @return object $image
     */
    public function antialias_pixel(object $image, int $x, int $y, ?int $colour = null): object|bool
    {
        // Check that X/Y within bounds of image passed
        if ($x >= imagesx($image) || $x < 0 || $y >= imagesy(image: $image) || $y < 0) {
            return $image;
        }
    
        // If colour not set, use the colour of current pixel
        if (! $colour) {
            $colour = imagecolorat($image, x: (int) $x, y: (int) $y);
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
        $pixel_count   = 0;

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
    
        // Apply target colour with transparency of existing pixel
        imagesetpixel($image, $x, $y, imagecolorallocatealpha($image, $r, $g, $b, round(num: 127 - $average_opacity / 8)));
    
        return $image;
    }

    /**
     * Converts a GD Image object to Imagine/Image object
     *
     * @param  object $gdimage
     * @return bool|ImageInterface
     */
    public function convert_GDImage_object_to_image($gdimage): bool|ImageInterface
    {
        // Make sure we got something 
        if (! $gdimage) {
            return false;
        }

        try {
            $imagine = (new Factory\ClassFactory())->createImage(
                handle: Factory\ClassFactoryInterface::HANDLE_GD,
                resource: $gdimage,
                palette: new Palette\RGB(),
                metadata: new Image\Metadata\MetadataBag(),
            );
        }
        catch (\Imagine\Exception\RuntimeException $e) {
            // Creation of image failed.
            ee('jcogs_img:Utilities')->debug_message(lang(line: 'jcogs_img_imagine_error'), $e->getMessage());
            return false;
        }
        return $imagine;
    }

    /**
     * Converts an image (on $path) to a GDImage object
     *
     * @param  string $type
     * @param  string $path
     * @return object|bool
     */
    public function convert_image_to_GDImage_object(string $type, string $path): bool|object
    {
        $return_image = false;
        try {
            switch (strtolower(string: $type)) {
                case 'avif':
                    $return_image = imagecreatefromavif(filename: $path);
                    break;
                case 'bmp':
                    $return_image = imagecreatefrombmp(filename: $path);
                    break;
                case 'gif':
                    $return_image = imagecreatefromgif(filename: $path);
                    break;
                case 'jpeg':
                    $return_image = imagecreatefromjpeg(filename: $path);
                    break;
                case 'jpg':
                    $return_image = imagecreatefromjpeg(filename: $path);
                    break;
                case 'png':
                    $return_image = imagecreatefrompng(filename: $path);
                    break;
                case 'wbmp':
                    $return_image = imagecreatefromwbmp(filename: $path);
                    break;
                case 'webp':
                    $return_image = imagecreatefromwebp(filename: $path);
                    break;
                case 'xbm':
                    $return_image = imagecreatefromxbm(filename: $path);
                    break;
                case 'xpm':
                    $return_image = imagecreatefromxpm(filename: $path);
                    break;
                }
            } catch (\Exception $e) {
                ee('jcogs_img:Utilities')->debug_message("Failed to convert image to GDImage object. Type: $type, Path: $path. Error: " . $e->getMessage());
                return false;
            }
                return $return_image;
    }

    /**
     * Checks image content to see if it is an HEIC image format
     * Uses method from php-heic-to-jpg from here https://github.com/MaestroError/php-heic-to-jpg
     * @param  string|null $image
     * @return bool
     */
    public function detect_heic(?string $image = null): bool
    {

        if (! $image || ! is_string($image))
            return false;

        $magicNumber = strtolower(trim(substr(substr($image, 0, 12), 8)));

        $heicMagicNumbers = [
            'heic', // official
            'mif1', // unofficial but can be found in the wild
            'ftyp', // 10bit images, or anything that uses h265 with range extension
            'hevc', // brands for image sequences
            'hevx', // brands for image sequences
            'heim', // multiview
            'heis', // scalable
            'hevm', // multiview sequence
            'hevs', // multiview sequence
        ];

        return in_array($magicNumber, $heicMagicNumbers);
    }

    /**
     * Utility function: Detect version of PNG file
     * PNG8 and PNG24 files can cause problems with GdImage imagemerge operations due
     * lack of a distinct transparency layer. This function works out what format a PNG
     * file is so appropriate steps can be taken.
     * Code from https://stackoverflow.com/a/57547867/6475781
     * Modified to work with image string rather than file
     *
     * @param string $image - a string version of image file
     * @return array|bool
     */
    public function detect_png_version(string $image): array|bool
    {

        // Test to see if we have a PNG. Look for PNG in the first 4 bytes and IHDR in the 13th to 16th bytes
        if (substr($image, 0, 4) !== chr(0x89) . 'PNG' || substr($image, 12, 4) !== 'IHDR') {
            // This is not a PNG
            return false;
        }

        // PNG actually stores Width and height integers in big-endian.
        $width  = unpack('N', substr($image, 16, 4))[1];
        $height = unpack('N', substr($image, 20, 4))[1];

        // Bit depth: 1 byte
        // Bit depth is a single-byte integer giving the number of bits per sample or
        // per palette index (not per pixel).
        //
        // Valid values are 1, 2, 4, 8, and 16, although not all values are allowed for all color types.
        $bitDepth = ord(substr($image, 24, 1));

        // Pixel format
        // https://en.wikipedia.org/wiki/Portable_Network_Graphics#Pixel_format

        // Color type is a single-byte integer that describes the interpretation of the image data.
        // Color type codes represent sums of the following values:
        // 1 (palette used), 2 (color used), and 4 (alpha channel used).
        //
        // Valid values are 0, 2, 3, 4, and 6.
        $colorType = ord(substr($image, 25, 1));

        $colorTypes = [
            0 => 'Greyscale',
            2 => 'Truecolour',
            3 => 'Indexed-colour',
            4 => 'Greyscale with alpha',
            6 => 'Truecolour with alpha',
        ];

        $colorTypeText = $colorTypes[$colorType] ?? 'Unknown';

        $pngType = '?';
        // Work out what sort of PNG we have based on bit depth and color type
        if ($bitDepth === 8 && $colorType === 3) {
            // If the bitdepth is 8 and the colortype is 3 (Indexed-colour) you have a PNG8
            $pngType = 'PNG8';
        } elseif ($bitDepth === 8 && $colorType === 2) {
            // If the bitdepth is 8 and the colortype is 2 (Truecolour) you have a PNG24
            $pngType = 'PNG24';
        } elseif ($bitDepth === 8 && $colorType === 6) {
            // If the bitdepth is 8 and colortype is 6 (Truecolour with alpha) you have a PNG32.
            $pngType = 'PNG32';
        }

        return [
            'width'         => $width,
            'height'        => $height,
            'bit-depth'     => $bitDepth,
            'colorType'     => $colorType,
            'colorTypeText' => $colorTypeText,
            'pngType'       => $pngType
        ];
    }

    /**
     * Utility function: Detect and sanitize SVG images
     * This will either return a sanitized SVG/XML string or bool false 
     * if XML parsing failed (usually due to a badly formatted file).
     * 
     * Uses SVG Sanitizer library from https://github.com/darylldoyle/svg-sanitizer
     *
     * @param string $image - a string version of image file
     * @return bool|string $image - a sanitized version of image file
     */
    public function detect_sanitize_svg(string $image): bool|string
    {

        if (empty($image)) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_SVG_missing'));
            return false;
        }

        try {
            // Create a new sanitizer instance
            $sanitizer = new Sanitizer();
    
            // Pass image to the sanitizer and get it back clean
            $cleanSVG = $sanitizer->sanitize($image);
    
            if ($cleanSVG) {
                // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_SVG_valid'));
                return $cleanSVG;
            } else {
                // ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_SVG_invalid'));
                return false;
            }
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_SVG_sanitization_error'), $e->getMessage()));
            return false;
        }
    }

    /**
     * Generate points for rotated regular polygon
     *
     * @param int $x
     * @param int $y
     * @param int $radius
     * @param int $vertices (default 3)
     * @param int $rotation (default 0)
     * @return Point[]
     */
    public function draw_rotated_polygon(int $x, int $y, int $radius, int $vertices = 3, int $rotation = 0): array
    {
        // $x, $y -> Position in the image
        // $radius -> Radius of circle enclosing the polygon
        // $spikes -> Number of vertices
        // $rotation -> Rotation of the polygon

        // Ensure the number of vertices is greater than 2
        if ($vertices < 3) {
            throw new \InvalidArgumentException("A polygon must have at least 3 vertices.");
        }

        // Calculate the angle between vertices
        $angle = 360 / $vertices;

        // Initialize the coordinates array
        $coordinates = [];

        // Calculate the coordinates of each vertex
        for ($i = 0; $i < $vertices; $i++) {
            $vertexX = (int) round($x + ($radius * cos(deg2rad(270 - $angle * $i + $rotation))), 0);
            $vertexY = (int) round($y + ($radius * sin(deg2rad(270 - $angle * $i + $rotation))), 0);
            $coordinates[] = new Point($vertexX, $vertexY);
        }
        // Return the coordinates
        return $coordinates;
    }

    /**
     * Generate points for rotated star
     * With inspiration from examples on
     * http://www.php.net/manual/en/function.imagefilledpolygon.php
     *
     * @param int $x
     * @param int $y
     * @param int $radius
     * @param int $spikes
     * @param float $split
     * @param int $rotation
     * @return Point[]
     */
    public function draw_rotated_star(int $x, int $y, int $radius, int $spikes = 5, float $split = 0.5, int $rotation = 0): array
    {

        // $x, $y -> Position in the image
        // $radius -> Radius of the star
        // $spikes -> Number of spikes
        // $split -> Factor to determine the inner shape of the star
        // $rotation -> Rotation of the star

    // Ensure the number of spikes is greater than 2
    if ($spikes < 3) {
        throw new \InvalidArgumentException("A star must have at least 3 spikes.");
    }

    // Calculate the angle between spikes
    $angle = 360 / $spikes;

    // Initialize the coordinates array
    $coordinates = [];

    // Calculate the coordinates of the outer shape of the star
    $outer_shape = [];
    for ($i = 0; $i < $spikes; $i++) {
        $vertexX = (int) round($x + ($radius * cos(deg2rad(270 - $angle * $i + $rotation))), 0);
        $vertexY = (int) round($y + ($radius * sin(deg2rad(270 - $angle * $i + $rotation))), 0);
        $outer_shape[] = ['x' => $vertexX, 'y' => $vertexY];
    }

    // Calculate the coordinates of the inner shape of the star
    $inner_shape = [];
    for ($i = 0; $i < $spikes; $i++) {
        $vertexX = (int) round($x + ($split * $radius * cos(deg2rad(270 - 180 - $angle * $i + $rotation))), 0);
        $vertexY = (int) round($y + ($split * $radius * sin(deg2rad(270 - 180 - $angle * $i + $rotation))), 0);
        $inner_shape[] = ['x' => $vertexX, 'y' => $vertexY];
    }

    // Bring the coordinates in the right order
    foreach ($inner_shape as $key => $value) {
        if ($key == (floor($spikes / 2) + 1)) {
            break;
        }
        $inner_shape[] = $value;
        unset($inner_shape[$key]);
    }

    // Reset the keys
    $inner_shape = array_values($inner_shape);

    // "Merge" outer and inner shape
    foreach ($outer_shape as $key => $value) {
        $coordinates[] = new Point($outer_shape[$key]['x'], $outer_shape[$key]['y']);
        $coordinates[] = new Point($inner_shape[$key]['x'], $inner_shape[$key]['y']);
    }

    // Return the coordinates
    return $coordinates;
    }

    /**
     * Processes and returns encoded image as data-url string
     *
     * @param  object|null $image
     * @param  string|null $format
     * @param  string|null $quality
     * @return string
     */
    public function encode_base64(?object $image = null, ?string $format = null, ?string $quality = null): string
    {

        if (is_null($image) || is_null($format) || is_null($quality)) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_encode_too_few_params'));
            return '';
        }
        try {
            $encodedImage = base64_encode((string) $image->get($format, ['quality' => $quality]));
            return sprintf('data:%s;base64,%s', $format, $encodedImage);
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_encode_error'), $e->getMessage()));
            return '';
        }
        }

    /**
     * Face detection - heavily based on HAARPHP example code.
     * https://github.com/foo123/HAARPHP
     * Returns an array of arrays - 
     *  - first entry gives x y width height of bounding box
     *  - each subsequent entry gives x, y, width, height of detected faces
     *
     * @param  object|null $image
     * @param  int         $sensitivity
     * @param  array|null  $cascade
     * @return array|bool
     */
    public function face_detection(?object $image = null, int $sensitivity = 3, ?array $cascade = null): array|bool
    {
        ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_face_detect_start'), $sensitivity));
        // Start a timer for this operation run
        $time_start = microtime(true);

        try {
            if (is_null($image) || !is_object($image)) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_face_detect_too_few_params'));
                return false;
            }

            if (is_null($cascade)) {
                require PATH_THIRD . "jcogs_img/Library/haarcascade_frontalface_alt.php";
                $cascade = $haarcascade_frontalface_alt;
            }

            if (!is_array($cascade) || empty($cascade) || !isset($cascade['stages'])) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_face_detect_invalid_cascade'));
                return false;
            }

            $sensitivity = min(max(1, $sensitivity), 9); // Normalise value to range 1-9
            $sensitivity = ($sensitivity - 3); // 3 == 0
            $scale       = (5 + $sensitivity) / 15; // Normalise based on $sensitivity 1 => 20%, 9 => 93%.
            // Create a new detector
            $faceDetector = new HaarDetector($cascade);

            // Look for faces
            $found = $faceDetector
                // normalise image to some standard dimensions eg. 150 px width 
                // provides performance / accuracy trade-off
                // $sensitivity 
                ->image($image, $scale)
                ->cannyThreshold(array('low' => 80, 'high' => 200))
                ->detect(1, 1.1, 0.12, 1, 0.2, false);

            // if detected
            if ($found) {
                // $numFeatures = count($faceDetector->objects);
                $collection = array('min-x' => null, 'min-y' => null, 'max-x' => null, 'max-y' => null);
                // create array of summary found image from original image
                $detectedFaces = array_map(

                    function ($face) use (&$collection) {
                        $detectedFace        = array(
                            'x'      => $face->x,
                            'y'      => $face->y,
                            'width'  => $face->width,
                            'height' => $face->height
                        );
                        $collection['min-x'] = is_null($collection['min-x']) ? $face->x : min($face->x, $collection['min-x']);
                        $collection['min-y'] = is_null($collection['min-y']) ? $face->y : min($face->y, $collection['min-y']);
                        $collection['max-x'] = is_null($collection['max-x']) ? $face->x + $face->width : max($face->x + $face->width, $collection['max-x']);
                        $collection['max-y'] = is_null($collection['max-y']) ? $face->y + $face->height : max($face->y + $face->height, $collection['max-y']);
                        return $detectedFace;
                    }, $faceDetector->objects);
                // Write to log
                ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_face_detect_ends'), microtime(true) - $time_start));
                // Work out width of containing shape and insert into return matrix
                return array_merge([
                    [
                        'x'      => $collection['min-x'],
                        'y'      => $collection['min-y'],
                        'width'  => $collection['max-x'] - $collection['min-x'],
                        'height' => $collection['max-y'] - $collection['min-y']
                    ]
                ], $detectedFaces);
            }
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_face_detect_none_found'), microtime(true) - $time_start));
            return false;
        } catch (\Exception $e) {
            ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_face_detect_error'), $e->getMessage()));
            return false;
        }
    }

    /**
     * From https://stackoverflow.com/a/54827140/6475781
     * Estimates, if image has pixels with transparency. It shrinks image to 64 times smaller
     * size, if necessary, and searches for the first pixel with non-zero alpha byte.
     * If image has 1% opacity, it will be detected. If any block of 8x8 pixels has at least
     * one semi-opaque pixel, the block will trigger positive result. There are still cases,
     * where image with hardly noticeable transparency will be reported as non-transparent,
     * but it's almost always safe to fill such image with monotonic background.
     *
     * Icons with size <= 64x64 (or having square <= 4096 pixels) are fully scanned with
     * absolutely reliable result.
     *
     * @param  object $image // GDImage object or resource... 
     * @return bool
     */
    public function hasTransparency($image): bool
    {

        if (! is_resource($image) && ! is_object($image)) {
            throw new \InvalidArgumentException("Image resource expected. Got: " . gettype($image));
        }

        $shrinkFactor      = 64.0;
        $minSquareToShrink = 64.0 * 64.0;

        $width  = imagesx($image);
        $height = imagesy($image);
        $square = $width * $height;

        if ($square <= $minSquareToShrink) {
            [$thumb, $thumbWidth, $thumbHeight] = [$image, $width, $height];
        }
        else {
            $thumbSquare = $square / $shrinkFactor;
            $thumbWidth  = (int) round($width / sqrt($shrinkFactor));
            $thumbWidth < 1 and $thumbWidth = 1;
            $thumbHeight = (int) round($thumbSquare / $thumbWidth);
            $thumb       = imagecreatetruecolor($thumbWidth, $thumbHeight);
            imagealphablending($thumb, false);
            imagecopyresized($thumb, $image, 0, 0, 0, 0, $thumbWidth, $thumbHeight, $width, $height);
        }

        for ($i = 0; $i < $thumbWidth; $i++) {
            for ($j = 0; $j < $thumbHeight; $j++) {
                if (imagecolorat($thumb, $i, $j) & 0x7F000000) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * PNG ALPHA CHANNEL SUPPORT for imagecopymerge(); 
     * This is a function like imagecopymerge but it handle alpha channel well!!! 
     * From http://www.php.net/manual/en/function.imagecopymerge.php
     *
     * @param  object $dst_im
     * @param  object $src_im
     * @param  int $dst_x
     * @param  int $dst_y
     * @param  int $src_x
     * @param  int $src_y
     * @param  int $src_w
     * @param  int $src_h
     * @param  float $pct
     * @return bool|object
     */
    public static function imagecopymerge_alpha($dst_im, $src_im, $dst_x, $dst_y, $src_x, $src_y, $src_w, $src_h, $pct): bool|object
    {
        if (! isset($pct)) {
            return false;
        }
        $pct /= 100;
        // Get image width and height 
        $w = imagesx($src_im);
        $h = imagesy($src_im);

        // Turn alpha blending off 
        imagealphablending($src_im, false);

        // Find the most opaque pixel in the image (the one with the smallest alpha value) 
        $minalpha = 127;
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                $alpha = (imagecolorat($src_im, $x, $y) >> 24) & 0xFF;
                if ($alpha < $minalpha) {
                    $minalpha = $alpha;
                }
            }
        }

        //loop through image pixels and modify alpha for each 
        for ($x = 0; $x < $w; $x++) {
            for ($y = 0; $y < $h; $y++) {
                //get current alpha value (represents the TRANSPARENCY!) 
                $colorxy = imagecolorat($src_im, $x, $y);
                $alpha   = ($colorxy >> 24) & 0xFF;

                //calculate new alpha 
                if ($minalpha !== 127) {
                    $alpha = 127 + 127 * $pct * ($alpha - 127) / (127 - $minalpha);
                }
                else {
                    $alpha += 127 * $pct;
                }
                $alpha = (int) $alpha;

                //get the color index with new alpha 
                $alphacolorxy = imagecolorallocatealpha($src_im, ($colorxy >> 16) & 0xFF, ($colorxy >> 8) & 0xFF, $colorxy & 0xFF, $alpha < 127 ? $alpha : 127);
                
                //set pixel with the new color + opacity 
                if (! imagesetpixel($src_im, $x, $y, $alphacolorxy)) {
                    return false;
                }
            }
        }

        // Copy the image
        imagecopy($dst_im, $src_im, (int) $dst_x, (int) $dst_y, (int) $src_x, (int) $src_y, (int) $src_w, (int) $src_h);
        return $dst_im;
    }

    /**
     * Detects animated GIF from given file pointer resource or filename.
     * Derived from code at https://stackoverflow.com/a/42191495/6475781
     * This version works on file in memory rather than on disk.
     *
     * @param resource|string $file File pointer resource or filename
     * @return int|bool
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
     * Tests to see if pssed object is a GD resource or GDImage object
     *
     * @param  mixed  $var
     * @return bool
     */
    public function is_gd_image($var): bool
    {
        if (is_object($var) && get_class($var) === "GdImage") {
            return true;
        }
    
        if (is_resource($var) && get_resource_type($var) === "gd") {
            return true;
        }
    
        return false;
    }

    /**
     * Checks to see if co-ordinates given are over an opaque pixel in an image
     *
     * @param  resource|\GdImage  $image
     * @param  int  $x
     * @param  int  $y
     * @return int|bool
     */
    public function is_pixel_opaque($image, int $x, int $y): bool
    {

        $x = round($x, 0);
        $y = round($y, 0);
        if ($this->is_gd_image($image) && $x >= 0 && $x < imagesx($image) && $y >= 0 && $y < imagesy($image)) {
            return (imagecolorat($image, $x, $y) & 0x7F000000) >> 24 == 0;
        }
        else {
            return false;
        }
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
     * Save a file
     *
     * @param object $image
     * @param string $adapter
     * @return bool
     */
    public function save(?\JCOGSDesign\Jcogs_img\Library\JcogsImage $image, ?\League\Flysystem\FilesystemAdapter $adapter = null)
    {
        // Check if local_path is set
        if (!property_exists($image, 'local_path') || empty($image->local_path)) {
            if (property_exists($image->ident, 'output')) {
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_save_path_missing'), [
                    'path($image->params->cache_dir)' => ee('jcogs_img:Utilities')->path($image->params->cache_dir),
                    '$image->ident->output' => $image->ident->output,
                    '$image->params->save_as' => $image->params->save_as,
                    '$image->params->src' => $image->params->src
                ]);
            }
            return false;
        }
    
        // Start a timer for this operation run
        $time_start = microtime(true);
    
        // Set image quality options
        $image_options = $this->_get_image_options($image);
    
        // Set JPG Interlace if required
        if (!$image->flags->using_cache_copy && in_array($image->params->save_as, ['jpg', 'jpeg']) && strtolower(substr($image->params->interlace, 0, 1)) === 'y') {
            $image->processed_image->interlace(ImageInterface::INTERLACE_LINE);
        }
    
        // Handle SVG and animated GIF images
        if ($image->flags->svg) {
            $image->params->save_as = 'svg';
        } elseif ($image->flags->animated_gif) {
            $image->params->save_as = 'gif';
        }
    
        // Set background color for opaque formats
        if (!in_array($image->params->save_as, ['avif', 'gif', 'png', 'webp']) && !$image->flags->svg) {
            $image = $this->_set_background_color($image);
        }
    
        // Check if directory is available
        if (!$this->directoryExists($image->params->cache_dir, true)) {
            return false;
        }
    
        // Save the image
        if (!$this->_save_image($image, $image_options)) {
            return false;
        }
    
        // Write to log
        ee('jcogs_img:Utilities')->debug_message(sprintf(lang('jcogs_img_saved'), microtime(true) - $time_start));
        return true;
    }

    /**
     * Get image options based on the image parameters and settings.
     *
     * This method determines the appropriate image options such as quality and 
     * lossless settings based on the image format and provided parameters.
     *
     * @param object $image An object containing image parameters and settings.
     *                       - params: An object containing image parameters.
     *                         - save_as: The format to save the image as (e.g., 'avif', 'png', 'jpg', 'jpeg', 'webp').
     *                         - quality: The desired quality of the image (optional).
     *                       - settings: An array containing default image settings.
     *                         - img_cp_jpg_default_quality: Default quality for JPG images.
     *                         - img_cp_png_default_quality: Default quality for PNG images.
     * @return array An associative array containing the image options.
     *               - quality: The quality of the image (integer).
     *               - webp_lossless: A boolean indicating if the WebP image should be lossless (only for WebP format).
     */
    private function _get_image_options($image): array
    {
        $image_options = [];
        switch ($image->params->save_as) {
            case 'avif':
                $image_options['quality'] = (int) min(max($image->params->quality ?? $image->settings['img_cp_jpg_default_quality'], 0), 100);
                break;
            case 'png':
                $image_options['quality'] = (int) min(max($image->settings['img_cp_png_default_quality'], 0), 9);
                break;
            case 'jpg':
            case 'jpeg':
                $image_options['quality'] = (int) min(max($image->params->quality ?? $image->settings['img_cp_jpg_default_quality'], 0), 100);
                break;
            case 'webp':
                if (defined('IMG_WEBP_LOSSLESS') && ($image->params->quality == 100 || $image->params->quality == 'lossless')) {
                    $image_options['webp_lossless'] = true;
                    $image_options['quality'] = 100;
                } else {
                    $image_options['quality'] = (int) min(max($image->params->quality ?? $image->settings['img_cp_jpg_default_quality'], 0), 100);
                }
                break;
        }
        return $image_options;
    }

    /**
     * Process source to see if we can determine format to save image in.
     * There might be a save_type param, but if not get hold of filetype of source file and either use that 
     * or the default set in settings
     * 
     * If nothing set, use jpg.
     * 
     * @param string $src
     * @param string $save_type
     * @param bool $do_browser_checks
     * @return string
     */
    private function _get_save_as(string $src = '', string $save_type = '', bool $do_browser_checks = true): string
    {   
        // If we have a src, parse it to get the file extension
        $parsed_url = parse_url($src);
        $file_info = $parsed_url && array_key_exists('path', $parsed_url) ? pathinfo($parsed_url['path']) : [];
        $extension = array_key_exists('extension', $file_info) ? $file_info['extension'] : $this->settings['img_cp_default_image_format'];

        // If 'save_type' set to something other than 'source' then set save_as to that (target) format, otherwise choose a target format based on img_cp_default_image_format setting.
        $save_as = $save_type && $save_type != 'source' ? $save_type : $extension;

        // If we get here and save_as is set to 'source' then set to jpg (as default)
        if($save_as == '' || $save_as == 'source') {
            $save_as =  'jpg';
        }

        // Now normalise the file format (by checking that target format is valid for 
        // this server and supported by destination browser
        // If format is not valid, and original image (extension) is webp, png or gif, choose png
        // to preserve transparency if present, otherwise choose jpg
        // First check to see whether we should be checking browser capabilities
        if ($do_browser_checks) {
            // Now check to see if we have a valid server image format
            if (!$this->validate_server_image_format($save_as) ||  !$this->validate_browser_image_format($save_as)) {
                // We have an invalid format, so adjust to one of our fallback defaults
                if (isset($file_info['extension']) && in_array($file_info['extension'], ['webp', 'png', 'gif'])) {
                    $save_as = 'png';
                } else {
                    $save_as = 'jpg';
                }
            }
        }

        // If image is an animated gif and we are not overriding file type, we need to update save_type / save_as to gif
        if ($extension === 'gif' && $this->settings['img_cp_ignore_save_type_for_animated_gifs'] == 'y') {
            $save_as = 'gif';
        }

        // Due to how we handle SVGs, if original image is .svg we need to reset save_type / save_as to svg
        if ($extension === 'svg') {
            $save_as = 'svg';
        }
        return $save_as;
    }

    private function _processFaceData($face, array &$collection): array
    {
        $detectedFace = [
            'x'      => $face->x,
            'y'      => $face->y,
            'width'  => $face->width,
            'height' => $face->height
        ];
        
        $collection['min-x'] = is_null($collection['min-x']) ? $face->x : min($face->x, $collection['min-x']);
        $collection['min-y'] = is_null($collection['min-y']) ? $face->y : min($face->y, $collection['min-y']);
        $collection['max-x'] = is_null($collection['max-x']) ? $face->x + $face->width : max($face->x + $face->width, $collection['max-x']);
        $collection['max-y'] = is_null($collection['max-y']) ? $face->y + $face->height : max($face->y + $face->height, $collection['max-y']);
        
        return $detectedFace;
    }

    /**
     * Saves the processed image to the specified path based on the image type.
     *
     * @param object $image An object containing image data and flags.
     * @param array $image_options Options for saving the image.
     * @return bool Returns true if the image is saved successfully, false otherwise.
     */
    private function _save_image($image, $image_options): bool
    {
        $save_path_info = pathinfo($image->local_path);

        // Ensure dirname and filename are present from pathinfo
        if (empty($save_path_info['dirname']) || empty($save_path_info['filename'])) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_invalid_save_path_components'), $image->local_path);
            return false;
        }
        $save_path_start = $save_path_info['dirname'] . '/' . $save_path_info['filename'];
        
        $save_image_content = null;
        $final_save_path = '';

        // Setup image content to save based on the image type
        if ($image->flags->svg || $image->flags->animated_gif) {
            // For SVGs and animated GIFs we save $image->source_image_raw
            // It should not be null or false.
            if (is_string($image->source_image_raw) && !empty(trim($image->source_image_raw))) {
                $save_image_content = $image->source_image_raw;
            } else {
                // This indicates an issue: its flagged as svg or gif but the image is missing
                ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_svg_save_error_no_content'), [
                    'local_path' => $image->local_path,
                    'source_svg_type' => gettype($image->source_image_raw),
                    'source_svg_empty' => empty(trim($image->source_image_raw ?? ''))
                ]);
                return false; // Cannot save without valid content
            }
        } else {
            // For other image types, get the content from the processed_image Imagine object.
            if ($image->processed_image instanceof ImageInterface) {
                try {
                    $save_image_content = $image->processed_image->get($image->params->save_as, $image_options);
                } catch (\Imagine\Exception\Exception $e) { // Catch specific Imagine exceptions
                    ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_get_error'), $e->getMessage());
                    return false;
                }
            } else {
                 ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_save_error_no_processed_image'), $image->local_path);
                 return false;
            }
        }

        // Final check: ensure we have content and a path to save to
        if (empty($save_image_content) || empty($image->local_path)) {
            // This indicates an issue: either the content is empty or the path is not set
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_save_error_missing_content_or_path'), [
                'has_content' => !empty($save_image_content),
                'final_save_path' => $final_save_path ?? 'not_set',
                'image_flags_svg' => $image->flags->svg ?? 'not_set',
                'image_flags_animated_gif' => $image->flags->animated_gif ?? 'not_set'
            ]);
            return false;
        }

        // Now save the image
        if (!$this->write($image->local_path, $save_image_content)) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_save_failed'), $image->local_path);
            return false;
        }

        // Fire the hook
        if (ee()->extensions->active_hook('jcogs_img_saved')) {
            ee()->extensions->call('jcogs_img_saved', $image->params->save_as);
        }
    return true;
    }
    
    /**
     * Sets the background color of the given image.
     *
     * @param object $image The image object containing the processed image and parameters.
     * @return object|false The modified image object with the new background color, or false on failure.
     */
    private function _set_background_color($image)
    {
        try {
            $temp_image = (new Imagine())->create($image->processed_image->getSize(), is_string($image->params->bg_color) ? $this->validate_colour_string($image->params->bg_color) : $image->params->bg_color);
        } catch (\Imagine\Exception\RuntimeException $e) {
            ee('jcogs_img:Utilities')->debug_message(lang('jcogs_img_imagine_error'), $e->getMessage());
            return false;
        }
        $temp_image->paste($image->processed_image, new PointSigned(0, 0));
        $image->processed_image = $temp_image->copy();
        unset($temp_image);
        return $image;
    }

}