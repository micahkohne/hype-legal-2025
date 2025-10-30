<?php

/**
 * JCOGS Image Pro - Parameter Package Discovery Service
 * =====================================================
 * Discovers and manages parameter packages for the presets system
 * 
 * This service provides discovery and instantiation of parameter packages,
 * similar to how Filter discovery works in the existing addon architecture.
 * Integrates with ParameterRegistry for consistent categorization.
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Presets Feature Implementation
 */

namespace JCOGSDesign\JCOGSImagePro\Service;

use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\ParameterPackageInterface;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\ControlParameterPackage;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\CropParameterPackage;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\DimensionalParameterPackage;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\TransformationalParameterPackage;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\TextParameterPackage;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\WatermarkParameterPackage;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\BorderParameterPackage;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\ReflectionParameterPackage;
use JCOGSDesign\JCOGSImagePro\Service\ParameterPackages\RoundedCornersParameterPackage;

class ParameterPackageDiscovery
{
    /**
     * Cached package instances
     * @var array
     */
    private static $packages = [];

    /**
     * ParameterRegistry instance
     * @var ParameterRegistry
     */
    private $parameterRegistry;

    /**
     * ValidationService instance
     * @var mixed
     */
    private $validationService;

    /**
     * Package configuration
     * @var array
     */
    private $packageConfig = [];

    /**
     * Constructor
     * 
     * @param ParameterRegistry $parameterRegistry Parameter registry instance
     * @param mixed $validationService ValidationService instance
     */
    public function __construct(
        ?ParameterRegistry $parameterRegistry = null,
        $validationService = null
    ) {
        $this->parameterRegistry = $parameterRegistry ?: new ParameterRegistry();
        $this->validationService = $validationService ?: ServiceCache::validation();
        
        $this->initializePackageConfig();
    }

    /**
     * Initialize package configuration
     * Maps package names to their class implementations
     * 
     * @return void
     */
    private function initializePackageConfig(): void
    {
        $this->packageConfig = [
            'control' => [
                'class' => ControlParameterPackage::class,
                'category' => 'control',
                'enabled' => true,
                'priority' => 10
            ],
            'dimensional' => [
                'class' => DimensionalParameterPackage::class,
                'category' => 'dimensional',
                'enabled' => true,
                'priority' => 20
            ],
            'transformational' => [
                'class' => TransformationalParameterPackage::class,
                'category' => 'transformational',
                'enabled' => true,
                'priority' => 30
            ],
            'text' => [
                'class' => TextParameterPackage::class,
                'category' => 'transformational', // Text is a transformational parameter
                'enabled' => true,
                'priority' => 25  // Higher priority than general transformational package
            ],
            'watermark' => [
                'class' => WatermarkParameterPackage::class,
                'category' => 'transformational', // Watermark is a transformational parameter
                'enabled' => true,
                'priority' => 22  // Higher priority than general transformational package
            ],
            'crop' => [
                'class' => CropParameterPackage::class,
                'category' => 'transformational', // Crop is a transformational parameter
                'enabled' => true,
                'priority' => 19  // Higher priority than all other transformational packages
            ],
            'border' => [
                'class' => BorderParameterPackage::class,
                'category' => 'transformational', // Border is a transformational parameter
                'enabled' => true,
                'priority' => 24  // Higher priority than general transformational package
            ],
            'rounded_corners' => [
                'class' => RoundedCornersParameterPackage::class,
                'category' => 'transformational', // Rounded corners is a transformational parameter
                'enabled' => true,
                'priority' => 23  // Higher priority than general transformational package
            ],
            'reflection' => [
                'class' => ReflectionParameterPackage::class,
                'category' => 'transformational', // Reflection is a transformational parameter
                'enabled' => true,
                'priority' => 21  // Higher priority than general transformational package
            ]
        ];
    }

    /**
     * Get all available parameter packages
     * 
     * @param bool $enabled_only Only return enabled packages
     * @return array Array of ParameterPackageInterface instances
     */
    public function getPackages(bool $enabled_only = true): array
    {
        $packages = [];

        foreach ($this->packageConfig as $name => $config) {
            if ($enabled_only && !$config['enabled']) {
                continue;
            }

            $package = $this->getPackage($name);
            if ($package && (!$enabled_only || $package->isEnabled())) {
                $packages[] = $package;
            }
        }

        // Sort by priority
        usort($packages, function($a, $b) {
            return $a->getPriority() <=> $b->getPriority();
        });

        return $packages;
    }

    /**
     * Get a specific parameter package by name
     * 
     * @param string $name Package name
     * @return ParameterPackageInterface|null Package instance or null if not found
     */
    public function getPackage(string $name): ?ParameterPackageInterface
    {
        // Check cache first
        if (isset(self::$packages[$name])) {
            return self::$packages[$name];
        }

        // Check if package is configured
        if (!isset($this->packageConfig[$name])) {
            return null;
        }

        $config = $this->packageConfig[$name];
        $className = $config['class'];

        // Ensure class exists
        if (!class_exists($className)) {
            return null;
        }

        // Instantiate package
        try {
            $package = new $className(
                $this->validationService,
                $this->parameterRegistry
            );

            // Verify it implements the interface
            if (!($package instanceof ParameterPackageInterface)) {
                error_log("ParameterPackageDiscovery DEBUG: Package {$name} does not implement ParameterPackageInterface");
                return null;
            }

            error_log("ParameterPackageDiscovery DEBUG: Successfully instantiated package {$name} ({$className})");

            // Cache for future use
            self::$packages[$name] = $package;

            return $package;
        } catch (\Exception $e) {
            // Log error and return null
            error_log("ParameterPackageDiscovery DEBUG: Failed to instantiate parameter package '{$name}': " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get packages by category
     * 
     * @param string $category Parameter category (control, dimensional, transformational)
     * @param bool $enabled_only Only return enabled packages
     * @return array Array of packages in the specified category
     */
    public function getPackagesByCategory(string $category, bool $enabled_only = true): array
    {
        $packages = $this->getPackages($enabled_only);
        
        return array_filter($packages, function($package) use ($category) {
            return $package->getCategory() === $category;
        });
    }

    /**
     * Get all form fields from all enabled packages
     * 
     * @param array $current_values Current parameter values
     * @param array $package_filter Optional array of package names to include
     * @return array Combined form fields from all packages
     */
    public function getAllFormFields(array $current_values = [], array $package_filter = []): array
    {
        $all_fields = [];
        $packages = $this->getPackages(true);

        foreach ($packages as $package) {
            // Skip if package filter is specified and this package isn't included
            if (!empty($package_filter) && !in_array($package->getName(), $package_filter)) {
                continue;
            }

            $package_fields = $package->generateFormFields($current_values);
            
            // Add package context to each field
            foreach ($package_fields as $field_name => $field_config) {
                $field_config['package'] = $package->getName();
                $field_config['category'] = $package->getCategory();
                $all_fields[$field_name] = $field_config;
            }
        }

        return $all_fields;
    }

    /**
     * Validate parameters across all relevant packages
     * 
     * @param array $parameters Parameter values to validate
     * @param array $package_filter Optional array of package names to validate
     * @return array Combined validation errors from all packages
     */
    public function validateAllParameters(array $parameters, array $package_filter = []): array
    {
        $all_errors = [];
        $packages = $this->getPackages(true);

        foreach ($packages as $package) {
            // Skip if package filter is specified and this package isn't included
            if (!empty($package_filter) && !in_array($package->getName(), $package_filter)) {
                continue;
            }

            $package_errors = $package->validateParameters($parameters);
            $all_errors = array_merge($all_errors, $package_errors);
        }

        return $all_errors;
    }

    /**
     * Get JavaScript files from all enabled packages
     * 
     * @return array Combined JavaScript file paths
     */
    public function getAllJavaScriptFiles(): array
    {
        $js_files = [];
        $packages = $this->getPackages(true);

        foreach ($packages as $package) {
            $package_js = $package->getJavaScriptFiles();
            $js_files = array_merge($js_files, $package_js);
        }

        return array_unique($js_files);
    }

    /**
     * Get CSS files from all enabled packages
     * 
     * @return array Combined CSS file paths
     */
    public function getAllCssFiles(): array
    {
        $css_files = [];
        $packages = $this->getPackages(true);

        foreach ($packages as $package) {
            $package_css = $package->getCssFiles();
            $css_files = array_merge($css_files, $package_css);
        }

        return array_unique($css_files);
    }

    /**
     * Clear cached packages (useful for testing)
     * 
     * @return void
     */
    public static function clearCache(): void
    {
        self::$packages = [];
    }

    /**
     * Get package configuration for admin interface
     * 
     * @return array Package configuration array
     */
    public function getPackageConfiguration(): array
    {
        return $this->packageConfig;
    }

    /**
     * Check if a package is available
     * 
     * @param string $name Package name
     * @return bool True if package is available
     */
    public function hasPackage(string $name): bool
    {
        return isset($this->packageConfig[$name]);
    }
}
