<?php

/**
 * JCOGS Image Pro - Crop Parameter Package
 * ========================================
 * Sophisticated form interface for crop parameter
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Parameter Package Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service\ParameterPackages;

/**
 * Crop Parameter Package
 * 
 * Provides sophisticated 4-field interface for crop parameter instead of requiring
 * users to construct complex pipe-separated parameter strings manually.
 * 
 * Crop Parameter Format (from JCOGS documentation):
 * crop="[yes|no|face_detect[|left|center|right|face_detect],[bottom|center|top|face_detect][|<integer>,<integer>[|[yes|no[|<integer>]]]]"
 * 
 * Components:
 * 1. Crop mode: yes, no, or face_detect
 * 2. Position: Horizontal (left, center, right, face_detect) and vertical (bottom, center, top, face_detect)
 * 3. Offset: Horizontal and vertical adjustments (can be pixels, %, negative values)
 * 4. Smart scaling: yes or no (default: yes)
 * 
 * Examples:
 * - crop="yes" - Default center crop with smart scaling
 * - crop="yes|center,top" - Crop from center-top position
 * - crop="yes|right,bottom|20,-20|no" - Crop from right-bottom with offset, no smart scaling
 * - crop="face_detect" - Auto-crop focused on detected faces
 * 
 * Documentation: https://jcogs.net/documentation/jcogs_img/jcogs_img-parameters#jcogs-image-parameter-crop
 */
class CropParameterPackage extends AbstractParameterPackage
{
    /**
     * Package identifier
     * 
     * @return string Package name
     */
    public function getName(): string 
    {
        return 'crop_parameter_package';
    }

    /**
     * Get the display label for this parameter package
     * 
     * @return string Package label
     */
    public function getLabel(): string
    {
        return 'Crop Parameters';
    }

    /**
     * Get the description for this parameter package
     * 
     * @return string Package description
     */
    public function getDescription(): string
    {
        return 'Controls for intelligent image cropping and positioning';
    }

    /**
     * Get the category for this parameter package
     * 
     * @return string Package category
     */
    public function getCategory(): string
    {
        return 'transformational';
    }

    /**
     * Parameters handled by this package
     * 
     * @return array List of parameter names
     */
    public function getParameters(): array 
    {
        return ['crop'];
    }

    /**
     * Priority for crop parameter package
     * Higher priority than general transformational package
     * 
     * @return int Priority order (lower numbers = higher priority)
     */
    public function getPriority(): int
    {
        return 19; // Higher than TransformationalParameterPackage (30)
    }

    /**
     * Get form fields for crop parameter
     * 
     * @param array $current_values Current parameter values  
     * @return array Flat associative array of form fields
     */
    protected function getPackageFormFields(array $current_values = []): array
    {
        // Get current crop value and parse into components
        $current_crop = $current_values['crop'] ?? 'no';
        $crop_parts = explode('|', $current_crop);
        
        return [
            'crop_enable' => [
                'type' => 'select',
                'label' => 'Crop Mode',
                'desc' => 'Choose how the image should be handled when it doesn\'t match the target dimensions',
                'value' => $crop_parts[0] ?? 'no',
                'choices' => [
                    'no' => 'No - Resize image to fit',
                    'yes' => 'Yes - Crop to exact dimensions',
                    'face_detect' => 'Face Detection - Focus on detected faces'
                ],
                'placeholder' => 'For example: yes'
            ],
            
            'crop_position' => [
                'type' => 'select',
                'label' => 'Crop Position',
                'desc' => 'Choose where to focus when cropping the image',
                'value' => $crop_parts[1] ?? 'center,center',
                'choices' => [
                    'center,center' => 'Center, Center (default)',
                    'left,top' => 'Left, Top',
                    'center,top' => 'Center, Top', 
                    'right,top' => 'Right, Top',
                    'left,center' => 'Left, Center',
                    'right,center' => 'Right, Center',
                    'left,bottom' => 'Left, Bottom',
                    'center,bottom' => 'Center, Bottom',
                    'right,bottom' => 'Right, Bottom',
                    'face_detect,face_detect' => 'Face Detection (automatic positioning)'
                ],
                'placeholder' => 'For example: center,center'
            ],
            
            'crop_offset' => [
                'type' => 'text',
                'label' => 'Crop Offset',
                'desc' => 'Fine-tune the crop position with pixel or percentage offsets (format: horizontal,vertical)',
                'value' => $crop_parts[2] ?? '0,0',
                'placeholder' => 'For example: 0,0 or 10px,-5px or 5%,-2%'
            ],
            
            'crop_smart_scaling' => [
                'type' => 'select',
                'label' => 'Smart Scaling',
                'desc' => 'Smart scaling optimizes the image before cropping to ensure better quality results',
                'value' => $crop_parts[3] ?? 'yes',
                'choices' => [
                    'yes' => 'Yes - Enable smart scaling (recommended)',
                    'no' => 'No - Use exact crop dimensions'
                ],
                'placeholder' => 'For example: yes'
            ]
        ];
    }

    /**
     * Process form data to generate crop parameter value
     * 
     * @param string $parameter_name Parameter name (should be 'crop')
     * @param array $form_data Form submission data
     * @return string Formatted crop parameter value
     */
    public function processParameterFromForm(string $parameter_name, array $form_data): string 
    {
        $crop_parts = [];

        // Part 1: Enable/Disable (Required)
        $crop_enable = trim($form_data['crop_enable'] ?? 'no');
        $crop_parts[] = $crop_enable;

        // If crop is disabled, return just 'no'
        if ($crop_enable === 'no') {
            return 'no';
        }

        // Part 2: Position (Always include when crop is enabled)
        $crop_position = trim($form_data['crop_position'] ?? 'center,center');
        $crop_parts[] = $crop_position;

        // Part 3: Offset (Always include when crop is enabled)
        $crop_offset = trim($form_data['crop_offset'] ?? '0,0');
        $crop_parts[] = $crop_offset;

        // Part 4: Smart Scaling (Always include when crop is enabled)
        $crop_smart_scaling = trim($form_data['crop_smart_scaling'] ?? 'yes');
        $crop_parts[] = $crop_smart_scaling;

        return implode('|', $crop_parts);
    }

    /**
     * Validate crop parameter value
     * 
     * @param string $param_name Parameter name (should be 'crop')
     * @param mixed $value Parameter value to validate
     * @return bool|string True if valid, error message if invalid
     */
    protected function validateParameter(string $param_name, $value)
    {
        if ($param_name !== 'crop') {
            return parent::validateParameter($param_name, $value);
        }

        // Allow empty values (will be handled as 'no')
        if (empty($value)) {
            return true;
        }

        $crop_value = trim((string)$value);
        
        // 'no' or 'none' are always valid
        if (in_array(strtolower($crop_value), ['no', 'none'])) {
            return true;
        }

        // Parse crop parameters
        $parts = explode('|', $crop_value);
        
        // Part 1: Crop mode validation
        $crop_mode = strtolower($parts[0] ?? '');
        if (!in_array($crop_mode, ['yes', 'no', 'face_detect'])) {
            return "Invalid crop mode '{$parts[0]}'. Must be 'yes', 'no', or 'face_detect'";
        }

        // If crop_mode is 'no', stop validation here
        if ($crop_mode === 'no') {
            return true;
        }

        // Part 2: Position validation (if provided)
        if (isset($parts[1]) && !empty(trim($parts[1]))) {
            $position_error = $this->validateCropPosition($parts[1]);
            if ($position_error !== true) {
                return $position_error;
            }
        }

        // Part 3: Offset validation (if provided)
        if (isset($parts[2]) && !empty(trim($parts[2]))) {
            $offset_error = $this->validateCropOffset($parts[2]);
            if ($offset_error !== true) {
                return $offset_error;
            }
        }

        // Part 4: Smart scaling validation (if provided)
        if (isset($parts[3]) && !empty(trim($parts[3]))) {
            $smart_scale = strtolower(trim($parts[3]));
            if (!in_array($smart_scale, ['yes', 'no', 'y', 'n'])) {
                return "Invalid smart scaling value '{$parts[3]}'. Must be 'yes' or 'no'";
            }
        }

        return true;
    }

    /**
     * Validate crop position parameter
     * 
     * @param string $position Position value to validate
     * @return bool|string True if valid, error message if invalid
     */
    private function validateCropPosition(string $position): string|bool
    {
        $position = trim($position);
        
        // Check for comma-separated horizontal,vertical format
        if (strpos($position, ',') !== false) {
            $pos_parts = explode(',', $position);
            if (count($pos_parts) !== 2) {
                return "Invalid crop position format '{$position}'. Must be 'horizontal,vertical' (e.g., 'center,center')";
            }

            $horizontal = strtolower(trim($pos_parts[0]));
            $vertical = strtolower(trim($pos_parts[1]));

            // Validate horizontal position
            if (!in_array($horizontal, ['left', 'center', 'right', 'face_detect'])) {
                return "Invalid horizontal crop position '{$pos_parts[0]}'. Must be 'left', 'center', 'right', or 'face_detect'";
            }

            // Validate vertical position
            if (!in_array($vertical, ['top', 'center', 'bottom', 'face_detect'])) {
                return "Invalid vertical crop position '{$pos_parts[1]}'. Must be 'top', 'center', 'bottom', or 'face_detect'";
            }
        } else {
            // Single value format (legacy support)
            $pos_lower = strtolower($position);
            if (!in_array($pos_lower, ['left', 'center', 'right', 'top', 'bottom', 'face_detect'])) {
                return "Invalid crop position '{$position}'. Must be 'left', 'center', 'right', 'top', 'bottom', or 'face_detect'";
            }
        }

        return true;
    }

    /**
     * Validate crop offset parameter
     * 
     * @param string $offset Offset value to validate
     * @return bool|string True if valid, error message if invalid
     */
    private function validateCropOffset(string $offset): string|bool
    {
        $offset = trim($offset);
        
        // Check for comma-separated horizontal,vertical format
        if (strpos($offset, ',') !== false) {
            $offset_parts = explode(',', $offset);
            if (count($offset_parts) !== 2) {
                return "Invalid crop offset format '{$offset}'. Must be 'horizontal,vertical' (e.g., '0,0' or '10px,-5px')";
            }

            foreach ($offset_parts as $index => $part) {
                $part = trim($part);
                $axis = $index === 0 ? 'horizontal' : 'vertical';
                
                if (!$this->isValidOffsetValue($part)) {
                    return "Invalid {$axis} crop offset '{$part}'. Must be a number, optionally followed by 'px' or '%' (e.g., '10', '-5px', '2%')";
                }
            }
        } else {
            // Single value format (legacy support)
            if (!$this->isValidOffsetValue($offset)) {
                return "Invalid crop offset '{$offset}'. Must be a number, optionally followed by 'px' or '%' (e.g., '10', '-5px', '2%')";
            }
        }

        return true;
    }

    /**
     * Check if a value is a valid offset (number with optional px or % suffix)
     * 
     * @param string $value Value to check
     * @return bool True if valid offset value
     */
    private function isValidOffsetValue(string $value): bool
    {
        $value = trim($value);
        
        // Allow empty/zero values
        if ($value === '' || $value === '0') {
            return true;
        }

        // Check for numeric value with optional px or % suffix
        if (preg_match('/^-?\d+(\.\d+)?(px|%)?$/i', $value)) {
            return true;
        }

        return false;
    }
}
