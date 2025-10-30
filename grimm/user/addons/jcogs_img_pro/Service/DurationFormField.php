<?php

/**
 * JCOGS Image Pro - Duration Form Field Component
 * ===============================================
 * Enhanced form field for duration inputs with natural language support
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Duration Enhancement Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

class DurationFormField
{
    private DurationParser $parser;
    
    public function __construct(DurationParser $parser)
    {
        $this->parser = $parser;
    }

    /**
     * Create an enhanced duration form field
     * 
     * @param object $fieldset EE fieldset object
     * @param string $fieldName Field name
     * @param mixed $currentValue Current value in seconds
     * @param string $context Context for validation and examples (cache, timeout, audit)
     * @param array $options Additional options
     * @return object The created field object
     */
    public function createField($fieldset, string $fieldName, $currentValue, string $context = 'general', array $options = [])
    {
        $currentSeconds = (int)$currentValue;
        $humanReadable = $this->parser->formatDuration($currentSeconds);
        $examples = $this->parser->getExamples($context);
        
        // Create the text field
        $field = $fieldset->getField($fieldName, 'text');
        
        // Set placeholder with examples
        $placeholder = $options['placeholder'] ?? $this->generatePlaceholder($context);
        $field->setPlaceholder($placeholder);
        
        // Set value - show human readable if it's clean, otherwise show seconds
        $displayValue = $this->getDisplayValue($currentSeconds, $humanReadable);
        $field->setValue($displayValue);
        
        // Add CSS classes and data attributes using EE7's set() method
        $field->set('class', 'form-control duration-input');
        $field->set('data-duration-context', $context);
        $field->set('data-current-seconds', $currentSeconds);
        $field->set('data-current-human', $humanReadable);
        $field->set('data-examples', implode('|', $examples));
        
        return $field;
    }

    /**
     * Generate placeholder text based on context with natural language examples
     */
    private function generatePlaceholder(string $context): string
    {
        $contextPlaceholders = [
            'cache' => 'e.g., "a week", "2 weeks", "forever", "disabled"',
            'audit' => 'e.g., "daily", "a week", "2 days"', 
            'timeout' => 'e.g., "30 seconds", "a minute", "2 minutes"'
        ];
        
        return $contextPlaceholders[$context] ?? 'e.g., "a week", "2 days", "1 hour 30 minutes", "forever"';
    }

    /**
     * Determine the best display value for the field
     */
    private function getDisplayValue(int $seconds, string $humanReadable): string
    {
        // For special values, always show human readable
        if ($seconds === -1 || $seconds === 0) {
            return $humanReadable;
        }
        
        // For common values that format nicely, show human readable
        if ($this->isCleanHumanValue($humanReadable)) {
            return $humanReadable;
        }
        
        // For odd values, show seconds
        return (string)$seconds;
    }

    /**
     * Check if human readable value is "clean" (no "about" prefix, whole numbers)
     */
    private function isCleanHumanValue(string $humanReadable): bool
    {
        // If it starts with "about", it's an approximation, show seconds instead
        if (strpos($humanReadable, 'about') === 0) {
            return false;
        }
        
        // If it contains decimals, might be less user-friendly
        if (strpos($humanReadable, '.') !== false) {
            return false;
        }
        
        return true;
    }

    /**
     * Generate help text for duration field with enhanced natural language info
     */
    public function getHelpText(string $context): string
    {
        $examples = $this->parser->getExamples($context);
        $exampleText = implode('", "', array_slice($examples, 0, 4));
        
        return "You can enter natural language like \"{$exampleText}\", compound durations like \"1 hour 30 minutes\", or seconds directly (e.g., 604800).";
    }

    /**
     * Validate duration input from form submission
     * 
     * @param string $input User input
     * @param string $context Validation context
     * @return array{value: int, error: string|null, human_readable: string}
     */
    public function validateInput(string $input, string $context = 'general'): array
    {
        $parseResult = $this->parser->parseToSeconds($input);
        
        if ($parseResult['error']) {
            return [
                'value' => 0,
                'error' => $parseResult['error'],
                'human_readable' => 'invalid'
            ];
        }
        
        $contextValidation = $this->parser->validateForContext($parseResult['value'], $context);
        
        if (!$contextValidation['valid']) {
            return [
                'value' => $parseResult['value'],
                'error' => $contextValidation['error'],
                'human_readable' => $this->parser->formatDuration($parseResult['value'])
            ];
        }
        
        return [
            'value' => $parseResult['value'],
            'error' => null,
            'human_readable' => $this->parser->formatDuration($parseResult['value'])
        ];
    }

    /**
     * Load required CSS and JavaScript assets for duration form fields
     * Call this method from routes that use duration form fields
     * 
     * @return void
     */
    public function loadAssets(): void
    {
        // Load CSS
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'user/jcogs_img_pro/css/duration-form-field.css" />');
        
        // Load JavaScript
        ee()->cp->add_to_foot('<script defer src="' . URL_THIRD_THEMES . 'user/jcogs_img_pro/javascript/duration-form-field.js"></script>');
    }

    /**
     * Get asset loading instructions for manual implementation
     * For routes that prefer to handle asset loading themselves
     * 
     * @return array Asset URLs and loading methods
     */
    public function getAssetInfo(): array
    {
        return [
            'css' => [
                'url' => URL_THIRD_THEMES . 'user/jcogs_img_pro/css/duration-form-field.css',
                'method' => 'add_to_head'
            ],
            'javascript' => [
                'url' => URL_THIRD_THEMES . 'user/jcogs_img_pro/javascript/duration-form-field.js',
                'method' => 'add_to_foot',
                'defer' => true
            ]
        ];
    }
}
