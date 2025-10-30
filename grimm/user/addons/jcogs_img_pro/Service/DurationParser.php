<?php

/**
 * JCOGS Image Pro - Duration Parser Service
 * =========================================
 * Natural language duration parsing with human-readable formatting
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

use Carbon\Carbon;
use Carbon\CarbonInterval;

class DurationParser
{
    /**
     * Special duration keywords
     */
    const SPECIAL_VALUES = [
        'forever' => -1,
        'never expire' => -1,
        'permanent' => -1,
        'perpetual' => -1,
        'never' => 0,
        'disabled' => 0,
        'no cache' => 0,
        'no caching' => 0,
        'off' => 0,
        'daily' => 86400,
        'weekly' => 604800,
        'monthly' => 2592000
    ];

    /**
     * Parse natural language duration input to seconds
     * 
     * @param string $input Natural language input
     * @return array{value: int, error: string|null, parsed_from: string}
     */
    public function parseToSeconds(string $input): array
    {
        $input = trim(strtolower($input));
        
        if (empty($input)) {
            return ['value' => 0, 'error' => 'Duration cannot be empty', 'parsed_from' => $input];
        }

        // Check for special keywords first
        foreach (self::SPECIAL_VALUES as $keyword => $value) {
            if ($input === $keyword) {
                return ['value' => $value, 'error' => null, 'parsed_from' => $input];
            }
        }

        // If it's already a number, validate and return
        if (is_numeric($input)) {
            $seconds = (int)$input;
            if ($seconds < -1) {
                return ['value' => 0, 'error' => 'Duration must be -1 (permanent), 0 (disabled), or positive seconds', 'parsed_from' => $input];
            }
            return ['value' => $seconds, 'error' => null, 'parsed_from' => $input];
        }

        try {
            // Try to parse with Carbon
            $seconds = $this->parseNaturalLanguage($input);
            return ['value' => $seconds, 'error' => null, 'parsed_from' => $input];
        } catch (\Exception $e) {
            return ['value' => 0, 'error' => $this->generateParsingError($input, $e->getMessage()), 'parsed_from' => $input];
        }
    }

    /**
     * Parse natural language using Carbon's CarbonInterval
     */
    private function parseNaturalLanguage(string $input): int
    {
        // Pre-process number words to digits using wordsToNumber
        require_once __DIR__ . '/wordsToNumber.php';
        $original = $input;
        $converted = wordsToNumber($input);
        // Replace number words with their numeric equivalents
        if ($original !== $converted) {
            // Find all number words in the original string
            $numwords = [
                'zero','one','two','three','four','five','six','seven','eight','nine','ten',
                'eleven','twelve','thirteen','fourteen','fifteen','sixteen','seventeen','eighteen','nineteen','twenty',
                'thirty','forty','fifty','sixty','seventy','eighty','ninety','hundred','thousand','million','billion'
            ];
            foreach ($numwords as $word) {
                $pattern = '/\b' . $word . '\b/';
                if (preg_match($pattern, $original)) {
                    // Find the corresponding number in the converted string
                    if (preg_match('/\b(\d+)\b/', $converted, $matches)) {
                        $number = $matches[1];
                        $original = preg_replace($pattern, $number, $original, 1);
                        // Remove the matched number from converted to avoid duplicate replacements
                        $converted = preg_replace('/\b' . $number . '\b/', '', $converted, 1);
                    }
                }
            }
            $input = $original;
        }

        // First try Carbon's built-in parsing which handles many natural language formats
        try {
            // Carbon can parse formats like:
            // "2 weeks", "1 hour 30 minutes", "P1Y2M3DT4H5M6S" (ISO 8601), etc.
            $interval = CarbonInterval::fromString($input);
            return $interval->totalSeconds;
        } catch (\Exception $e) {
            // If Carbon fails, try our custom normalization and parsing
            return $this->parseWithCustomNormalization($input);
        }
    }

    /**
     * Custom parsing with normalization for formats Carbon doesn't handle
     */
    private function parseWithCustomNormalization(string $input): int
    {
        // Normalize common phrases that Carbon might not understand
        $input = str_replace([
            'a ', 'an ', 'one ',
            'half a ', 'half an ',
            'every ', 'each ',
            'and a half'
        ], [
            '1 ', '1 ', '1 ',
            '0.5 ', '0.5 ',
            '1 ', '1 ',
            '.5'
        ], $input);

        // Handle fractional expressions
        $input = preg_replace('/(\d+)\.5\s+(minute|hour|day|week|month|year)s?/', '$1.5 $2s', $input);

        // Try Carbon again after normalization
        try {
            $interval = CarbonInterval::fromString($input);
            return $interval->totalSeconds;
        } catch (\Exception $e) {
            // Fall back to our regex-based parsing for edge cases
            return $this->parseWithRegex($input);
        }
    }

    /**
     * Fallback regex-based parsing for formats Carbon can't handle
     */
    private function parseWithRegex(string $input): int
    {
        // Handle simple single unit durations
        if (preg_match('/^(\d+(?:\.\d+)?)\s*(second|minute|hour|day|week|month|year)s?$/', $input, $matches)) {
            $amount = floatval($matches[1]);
            $unit = $matches[2];
            
            return $this->convertToSeconds($amount, $unit);
        }

        // Handle compound durations like "1 week 2 days"
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(second|minute|hour|day|week|month|year)s?/', $input, $matches, PREG_SET_ORDER)) {
            $totalSeconds = 0;
            foreach ($matches as $match) {
                $amount = floatval($match[1]);
                $unit = $match[2];
                $totalSeconds += $this->convertToSeconds($amount, $unit);
            }
            return $totalSeconds;
        }

        throw new \InvalidArgumentException("Could not parse duration: {$input}");
    }

    /**
     * Convert amount and unit to seconds
     */
    private function convertToSeconds(float $amount, string $unit): int
    {
        $multipliers = [
            'second' => 1,
            'minute' => 60,
            'hour' => 3600,
            'day' => 86400,
            'week' => 604800,
            'month' => 2592000, // 30 days
            'year' => 31536000  // 365 days
        ];

        if (!isset($multipliers[$unit])) {
            throw new \InvalidArgumentException("Unknown time unit: {$unit}");
        }

        return (int)round($amount * $multipliers[$unit]);
    }

    /**
     * Format seconds to human-readable duration using Carbon
     * 
     * @param int $seconds Duration in seconds
     * @param bool $abbreviated Use abbreviated format (e.g., "2h" vs "2 hours")
     * @return string Human-readable duration
     */
    public function formatDuration(int $seconds, bool $abbreviated = false): string
    {
        // Handle special values
        if ($seconds === -1) {
            return $abbreviated ? 'forever' : 'forever (never expires)';
        }
        
        if ($seconds === 0) {
            return $abbreviated ? 'disabled' : 'disabled (no caching)';
        }

        if ($seconds < 0) {
            return $abbreviated ? 'invalid' : 'invalid duration';
        }

        try {
            // Use Carbon's CarbonInterval for accurate formatting
            $interval = CarbonInterval::seconds($seconds);
            
            // For very small values (< 2 minutes), show in seconds
            if ($seconds < 120) {
                $unit = $abbreviated ? 's' : ($seconds == 1 ? ' second' : ' seconds');
                return $seconds . $unit;
            }

            // Use Carbon's human-readable formatting for larger values
            if ($abbreviated) {
                return $this->formatAbbreviated($interval);
            } else {
                return $this->formatVerbose($interval, $seconds);
            }
            
        } catch (\Exception $e) {
            // Fallback to basic formatting if Carbon fails
            return $this->formatBasic($seconds, $abbreviated);
        }
    }

    /**
     * Format interval in abbreviated form (e.g., "2h", "1d")
     */
    private function formatAbbreviated(CarbonInterval $interval): string
    {
        if ($interval->totalYears >= 1) {
            return round($interval->totalYears, 1) . 'y';
        }
        if ($interval->totalMonths >= 1) {
            return round($interval->totalMonths, 1) . 'mo';
        }
        if ($interval->totalDays >= 1) {
            return round($interval->totalDays) . 'd';
        }
        if ($interval->totalHours >= 1) {
            return round($interval->totalHours, 1) . 'h';
        }
        
        return round($interval->totalMinutes) . 'm';
    }

    /**
     * Format interval in verbose form with Carbon's built-in formatting
     */
    private function formatVerbose(CarbonInterval $interval, int $seconds): string
    {
        // For values over a year, add "about" prefix since it's an approximation
        if ($interval->totalYears >= 1) {
            return "about " . $interval->forHumans();
        }
        
        // For medium to large values, use Carbon's human formatting
        if ($seconds >= 7200) { // 2 hours or more
            $formatted = $interval->forHumans();
            
            // Clean up Carbon's output to match our style
            $formatted = str_replace(['after', 'before'], '', $formatted);
            $formatted = trim($formatted);
            
            return $formatted;
        }
        
        // For smaller values, use our precise formatting
        if ($seconds < 7200) {
            $minutes = round($seconds / 60);
            return $minutes . ($minutes == 1 ? ' minute' : ' minutes');
        }
        
        return $interval->forHumans();
    }

    /**
     * Basic fallback formatting when Carbon is not available
     */
    private function formatBasic(int $seconds, bool $abbreviated): string
    {
        // For small values (< 2 hours), show in minutes
        if ($seconds < 7200) {
            $minutes = round($seconds / 60);
            $unit = $abbreviated ? 'm' : ($minutes == 1 ? ' minute' : ' minutes');
            return $minutes . $unit;
        }

        // For medium values (< 2 days), show in hours
        if ($seconds < 172800) {
            $hours = round($seconds / 3600, 1);
            if ($hours == floor($hours)) {
                $hours = (int)$hours;
            }
            $unit = $abbreviated ? 'h' : ($hours == 1 ? ' hour' : ' hours');
            return $hours . $unit;
        }

        // For large values (< 2 months), show in days
        if ($seconds < 5184000) {
            $days = round($seconds / 86400);
            $unit = $abbreviated ? 'd' : ($days == 1 ? ' day' : ' days');
            return $days . $unit;
        }

        // For very large values, show in months/years
        if ($seconds < 31536000) {
            $months = round($seconds / 2592000, 1);
            if ($months == floor($months)) {
                $months = (int)$months;
            }
            $unit = $abbreviated ? 'mo' : ($months == 1 ? ' month' : ' months');
            return "about " . $months . $unit;
        }

        // Show in years
        $years = round($seconds / 31536000, 1);
        if ($years == floor($years)) {
            $years = (int)$years;
        }
        $unit = $abbreviated ? 'y' : ($years == 1 ? ' year' : ' years');
        return "about " . $years . $unit;
    }

    /**
     * Generate helpful parsing error message with Carbon-enhanced examples
     */
    private function generateParsingError(string $input, string $carbonError): string
    {
        return "Could not parse '{$input}'. Try formats like: '2 weeks', '1 hour 30 minutes', 'a week', 'half an hour', 'PT1H30M' (ISO 8601), '604800' (seconds), 'forever', or 'disabled'.";
    }

    /**
     * Get duration examples for UI help text
     * 
     * @param string $context Context for appropriate examples (cache, timeout, audit)
     * @return array Array of example strings
     */
    public function getExamples(string $context = 'general'): array
    {
        $common = [
            '30 seconds',
            'a minute',
            '2 hours',
            'a day',
            'a week',
            '1 hour 30 minutes',
            'forever',
            'disabled'
        ];

        $contextExamples = [
            'cache' => [
                'a day',
                'a week',
                '2 weeks',
                'a month',
                '1 week 3 days',
                'forever',
                'disabled'
            ],
            'audit' => [
                'daily',
                'a couple days',
                'a week',
                '2 weeks'
            ],
            'timeout' => [
                '30 seconds',
                'a minute',
                'a couple minutes',
                '5 minutes'
            ]
        ];

        return $contextExamples[$context] ?? $common;
    }

    /**
     * Validate duration for specific context
     * 
     * @param int $seconds Duration in seconds
     * @param string $context Context for validation (cache, timeout, audit)
     * @return array{valid: bool, error: string|null}
     */
    public function validateForContext(int $seconds, string $context): array
    {
        switch ($context) {
            case 'cache':
                if ($seconds < -1) {
                    return ['valid' => false, 'error' => 'Cache duration must be -1 (permanent), 0 (disabled), or positive seconds'];
                }
                return ['valid' => true, 'error' => null];
                
            case 'timeout':
                if ($seconds <= 0) {
                    return ['valid' => false, 'error' => 'Timeout must be greater than 0 seconds'];
                }
                if ($seconds > 3600) {
                    return ['valid' => false, 'error' => 'Timeout should not exceed 1 hour (3600 seconds)'];
                }
                return ['valid' => true, 'error' => null];
                
            case 'audit':
                if ($seconds <= 0) {
                    return ['valid' => false, 'error' => 'Audit interval must be greater than 0 seconds'];
                }
                if ($seconds < 3600) {
                    return ['valid' => false, 'error' => 'Audit interval should be at least 1 hour for performance reasons'];
                }
                return ['valid' => true, 'error' => null];
                
            default:
                if ($seconds < -1) {
                    return ['valid' => false, 'error' => 'Duration must be -1, 0, or positive seconds'];
                }
                return ['valid' => true, 'error' => null];
        }
    }
}
