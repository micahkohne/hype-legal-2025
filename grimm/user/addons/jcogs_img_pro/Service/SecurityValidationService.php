<?php

namespace JCOGSDesign\JCOGSImagePro\Service;

/**
 * Security Validation Service
 * 
 * Provides comprehensive security validation for both tag parameters 
 * and variable modifier parameters, protecting against XSS, path traversal,
 * command injection, and other attack vectors.
 */
class SecurityValidationService
{
    /**
     * Check for potentially malicious content in parameter values
     * 
     * @param string $value Parameter value to check
     * @return bool True if content appears malicious
     */
    public function containsMaliciousContent($value): bool
    {
        // First check original value for encoded attacks
        if ($this->checkRawPatterns($value)) {
            return true;
        }
        
        // Decode and normalize the value for comprehensive checking
        $decodedValue = $this->decodeAndNormalize($value);
        
        // Basic checks for script injection, path traversal, etc.
        $dangerous_patterns = [
            // Script injection patterns
            '/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi',
            '/javascript:/i',
            '/vbscript:/i',
            '/on\w+\s*=/i',
            '/data:\s*text\/html/i',
            
            // Path traversal patterns (enhanced)
            '/\.\.\//i',
            '/\.\.\\\/i',
            '/\.\.\\\\/i',
            '/\.\.%2f/i',
            '/\.\.%5c/i',
            '/file:\/\//i',
            '/\x2e\x2e\x2f/i',  // UTF-8 encoded dots and slash
            '/\x{002e}\x{002e}\x{002f}/ui', // Unicode escaped (PCRE2 syntax)
            
            // Command injection patterns (enhanced)
            '/\0/',
            '/eval\s*\(/i',
            '/exec\s*\(/i',
            '/system\s*\(/i',
            '/shell_exec\s*\(/i',
            '/passthru\s*\(/i',
            '/`[^`]*`/',
            '/\$\([^)]*\)/',
            '/\${[^}]*}/',
            '/[;&]/',  // Semicolon and ampersand only, removed pipe (|) as it's used as delimiter in many parameters
            '/[<>]/',
            
            // Quoted command injection patterns
            '/"[^"]*[;&|][^"]*"/',
            "/'[^']*[;&|][^']*'/",
            '/\w+["\'][^"\']*["\']/',  // Split quoted commands like c"a"t
            '/[a-z]+["\']\w+["\']\s+/', // Command with quoted letters
            
            // Advanced patterns
            '/powershell/i',
            '/cmd\.exe/i',
            '/expression\s*\(/i',
            '/@import/i',
            '/url\s*\(/i',
        ];
        
        foreach ($dangerous_patterns as $pattern) {
            if (preg_match($pattern, $decodedValue)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Check raw patterns before decoding
     * 
     * @param string $value Original parameter value
     * @return bool True if raw patterns match malicious content
     */
    private function checkRawPatterns($value): bool
    {
        $raw_patterns = [
            '/\x00/',           // Direct null byte
            '/%00/',            // URL encoded null
            '/\\\\0/',          // Escaped null  
            '/\\\u0000/',       // Unicode null
            '/\xc0\x80/',       // Overlong UTF-8 null
            '/%c0%af/',         // UTF-8 encoded slash (overlong encoding)
            '/\w+["\'][^"\']*["\'][^"\']*/',  // Quoted command patterns like c"a"t
        ];
        
        foreach ($raw_patterns as $pattern) {
            if (preg_match($pattern, $value)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Decode and normalize input for comprehensive security checking
     * 
     * @param string $value Input value to decode
     * @return string Decoded and normalized value
     */
    public function decodeAndNormalize($value): string
    {
        // Start with original value
        $decoded = $value;
        
        // URL decode (multiple rounds to catch double encoding)
        for ($i = 0; $i < 3; $i++) {
            $newDecoded = urldecode($decoded);
            if ($newDecoded === $decoded) {
                break; // No more changes
            }
            $decoded = $newDecoded;
        }
        
        // HTML entity decode
        $decoded = html_entity_decode($decoded, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Unicode normalization for UTF-8 attacks
        if (function_exists('normalizer_normalize')) {
            $decoded = normalizer_normalize($decoded, \Normalizer::FORM_KC);
        }
        
        // Handle various null byte encodings before normalization
        $patterns = [
            '/\x00/',           // Actual null byte
            '/%00/',            // URL encoded null
            '/\\\\0/',          // Escaped null
            '/\\\u0000/',       // Unicode escaped null
            '/\0/',             // PHP null representation
        ];
        
        foreach ($patterns as $pattern) {
            $decoded = preg_replace($pattern, '', $decoded);
        }
        
        // Convert to lowercase for case-insensitive matching
        $normalized = strtolower($decoded);
        
        // Remove common obfuscation characters but preserve structure for quoted strings
        $normalized = str_replace(["\r", "\n", "\t"], '', $normalized);
        
        return $normalized;
    }
    
    /**
     * Validate and sanitize an array of parameters
     * 
     * @param array $parameters Parameters to validate and sanitize
     * @return array Sanitized parameters with malicious content removed/escaped
     */
    public function validateAndSanitizeParameters(array $parameters): array
    {
        $sanitized = [];
        
        foreach ($parameters as $key => $value) {
            // Validate parameter key itself
            if ($this->containsMaliciousContent($key)) {
                // Skip malicious parameter keys entirely
                continue;
            }
            
            // Validate and sanitize parameter value
            if (is_string($value)) {
                if ($this->containsMaliciousContent($value)) {
                    // For malicious content, either remove or escape
                    // For safety, we'll remove the parameter entirely
                    continue;
                } else {
                    // Safe content - apply basic normalization
                    $sanitized[$key] = $this->basicSanitize($value);
                }
            } else {
                // Non-string values (arrays, objects) - pass through if key is safe
                $sanitized[$key] = $value;
            }
        }
        
        return $sanitized;
    }
    
    /**
     * Apply basic sanitization to safe content
     * 
     * @param string $value Safe value to sanitize
     * @return string Sanitized value
     */
    private function basicSanitize($value): string
    {
        // Remove any null bytes
        $sanitized = str_replace(["\0", "\x00"], '', $value);
        
        // Trim whitespace
        $sanitized = trim($sanitized);
        
        return $sanitized;
    }
}
