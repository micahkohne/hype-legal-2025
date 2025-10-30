<?php

/**
 * JCOGS Image Pro - Preset Model
 * ===============================
 * Model for managing preset configurations with parameter storage
 * 
 * This model handles CRUD operations for presets, including JSON parameter
 * storage, validation, and site-specific preset management.
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

namespace JCOGSDesign\JCOGSImagePro\Models;

use ExpressionEngine\Service\Model\Model;

class Preset extends Model
{
    /**
     * Table name
     * @var string
     */
    protected static $_table_name = 'jcogs_img_pro_presets';

    /**
     * Primary key
     * @var string
     */
    protected static $_primary_key = 'id';

    /**
     * Model fields and their types
     * @var array
     */
    protected static $_typed_columns = [
        'id' => 'int',
        'site_id' => 'int', 
        'name' => 'string',
        'description' => 'string',
        'parameters' => 'json',
        'sample_file_id' => 'int',
        'created_date' => 'timestamp',
        'modified_date' => 'timestamp'
    ];

    /**
     * Validation rules
     * @var array
     */
    protected static $_validation_rules = [
        'site_id' => 'required|integer',
        'name' => 'required|max_length[100]',
        'description' => 'max_length[1000]',
        'parameters' => 'required',
        'sample_file_id' => 'integer'
    ];

    /**
     * Fields that can be mass assigned
     * @var array
     */
    protected $_fillable = [
        'site_id',
        'name', 
        'description',
        'parameters',
        'sample_file_id'
    ];

    /**
     * Relationships
     * @var array
     */
    protected static $_relationships = [
        'Site' => [
            'type' => 'belongsTo',
            'model' => 'Site',
            'from_key' => 'site_id',
            'to_key' => 'site_id'
        ],
        'SampleFile' => [
            'type' => 'belongsTo', 
            'model' => 'File',
            'from_key' => 'sample_file_id',
            'to_key' => 'file_id'
        ]
    ];

    /**
     * Auto-set timestamps on save
     * @var array
     */
    protected $_auto_timestamps = [
        'created_date' => 'onInsert',
        'modified_date' => 'onUpdate'
    ];

    /**
     * Create a new preset
     * 
     * @param array $data Preset data
     * @return static Created preset instance
     * @throws \Exception If validation fails
     */
    public static function create(array $data): self
    {
        // Ensure we have the current site_id if not provided
        if (!isset($data['site_id'])) {
            $data['site_id'] = ee()->config->item('site_id');
        }

        // Ensure parameters are properly JSON encoded
        if (isset($data['parameters']) && is_array($data['parameters'])) {
            $data['parameters'] = json_encode($data['parameters']);
        }

        // Set timestamps
        $now = ee()->localize->now();
        $data['created_date'] = $now;
        $data['modified_date'] = $now;

        // Create the preset
        $preset = ee('Model')->make('jcogs_img_pro:Preset', $data);
        
        // Validate and save
        $result = $preset->validate();
        if ($result->isNotValid()) {
            throw new \Exception('Preset validation failed: ' . implode(', ', $result->getAllErrors()));
        }

        $preset->save();
        return $preset;
    }

    /**
     * Delete preset by name
     * 
     * @param string $name Preset name
     * @param int|null $site_id Site ID (defaults to current)
     * @return bool Success status
     */
    public static function deleteByName(string $name, ?int $site_id = null): bool
    {
        $preset = self::findByName($name, $site_id);
        if ($preset) {
            $preset->delete();
            return true;
        }
        return false;
    }

    /**
     * Find preset by name for the current site
     * 
     * @param string $name Preset name
     * @param int|null $site_id Site ID (defaults to current site)
     * @return static|null Found preset or null
     */
    public static function findByName(string $name, ?int $site_id = null): ?self
    {
        if ($site_id === null) {
            $site_id = ee()->config->item('site_id');
        }

        return ee('Model')->get('jcogs_img_pro:Preset')
            ->filter('site_id', $site_id)
            ->filter('name', $name)
            ->first();
    }

    /**
     * Get preset count for site
     * 
     * @param int|null $site_id Site ID (defaults to current)
     * @return int Preset count
     */
    public static function getCountForSite(?int $site_id = null): int
    {
        if ($site_id === null) {
            $site_id = ee()->config->item('site_id');
        }

        return ee('Model')->get('jcogs_img_pro:Preset')
            ->filter('site_id', $site_id)
            ->count();
    }

    /**
     * Get all presets for a site
     * 
     * @param int|null $site_id Site ID (defaults to current site)
     * @param array $order_by Order by fields
     * @return \ExpressionEngine\Service\Model\Collection
     */
    public static function getForSite(?int $site_id = null, array $order_by = ['name' => 'asc'])
    {
        if ($site_id === null) {
            $site_id = ee()->config->item('site_id');
        }

        $query = ee('Model')->get('jcogs_img_pro:Preset')
            ->filter('site_id', $site_id);

        foreach ($order_by as $field => $direction) {
            $query->order($field, $direction);
        }

        return $query->all();
    }

    /**
     * Get parameters as array
     * 
     * @return array Decoded parameters
     */
    public function getParametersArray(): array
    {
        if (is_string($this->parameters)) {
            $decoded = json_decode((string)$this->parameters, true);
            return $decoded ?? [];
        }

        return is_array($this->parameters) ? $this->parameters : [];
    }

    /**
     * Check if preset name is unique for the site
     * 
     * @param string $name Preset name to check
     * @param int|null $site_id Site ID (defaults to current)
     * @param int|null $exclude_id Preset ID to exclude from check
     * @return bool True if unique
     */
    public static function isNameUnique(string $name, ?int $site_id = null, ?int $exclude_id = null): bool
    {
        if ($site_id === null) {
            $site_id = ee()->config->item('site_id');
        }

        $query = ee('Model')->get('jcogs_img_pro:Preset')
            ->filter('site_id', $site_id)
            ->filter('name', $name);

        if ($exclude_id !== null) {
            $query->filter('id', '!=', $exclude_id);
        }

        return $query->count() === 0;
    }

    /**
     * After load hook - decode JSON parameters
     * 
     * @return void
     */
    protected function onAfterLoad()
    {
        // Decode JSON parameters for easier access
        if (is_string($this->parameters)) {
            $decoded = json_decode((string)$this->parameters, true);
            if ($decoded !== null) {
                $this->parameters = $decoded;
            }
        }

        parent::onAfterLoad();
    }

    /**
     * Before save hook - ensure JSON encoding
     * 
     * @return void
     */
    protected function onBeforeSave()
    {
        // Ensure parameters are JSON encoded
        if (is_array($this->parameters)) {
            $this->parameters = json_encode($this->parameters);
        }

        // Update modified timestamp
        $this->modified_date = ee()->localize->now();
        
        parent::onBeforeSave();
    }

    /**
     * Set parameters from array
     * 
     * @param array $parameters Parameters to set
     * @return void
     */
    public function setParametersArray(array $parameters): void
    {
        $this->parameters = json_encode($parameters);
    }

    /**
     * Export preset to array format
     * 
     * @return array Exportable preset data
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'site_id' => $this->site_id,
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->getParametersArray(),
            'sample_file_id' => $this->sample_file_id,
            'created_date' => $this->created_date,
            'modified_date' => $this->modified_date
        ];
    }

    /**
     * Get preset for export (without internal IDs)
     * 
     * @return array Export-friendly preset data
     */
    public function toExportArray(): array
    {
        return [
            'name' => $this->name,
            'description' => $this->description,
            'parameters' => $this->getParametersArray()
        ];
    }

    /**
     * Update preset parameters
     * 
     * @param array $parameters New parameters
     * @return bool Success status
     */
    public function updateParameters(array $parameters): bool
    {
        $this->parameters = json_encode($parameters);
        $this->modified_date = ee()->localize->now();
        
        $result = $this->validate();
        if ($result->isNotValid()) {
            return false;
        }

        $this->save();
        return true;
    }
}
