<?php

/**
 * JCOGS Image Pro - Preset Service
 * =================================
 * CRUD operations for preset management with EE Files integration
 * 
 * This service handles all preset database operations including creation,
 * reading, updating, and deletion. Integrates with the parameter package
 * system for validation and EE Files for sample image management.
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

use JCOGSDesign\JCOGSImagePro\Service\ParameterPackageDiscovery;
use JCOGSDesign\JCOGSImagePro\Service\Utilities;
use JCOGSDesign\JCOGSImagePro\Service\ServiceCache;

class PresetService
{
    /**
     * @var string Database table name
     */
    private $table_name = 'exp_jcogs_img_pro_presets';
    
    /**
     * Parameter package discovery service
     * @var ParameterPackageDiscovery
     */
    private $packageDiscovery;

    /**
     * Utilities service for logging
     * @var Utilities
     */
    private $utilities;

    /**
     * Current site ID
     * @var int
     */
    private $site_id;

    /**
     * Constructor
     * 
     * @param ParameterPackageDiscovery $packageDiscovery Parameter package discovery service
     * @param Utilities $utilities Utilities service for logging
     */
    public function __construct(ParameterPackageDiscovery $packageDiscovery = null, Utilities $utilities = null)
    {
        $this->packageDiscovery = $packageDiscovery ?: new ParameterPackageDiscovery();
        $this->utilities = $utilities ?: ServiceCache::utilities();
        $this->site_id = ee()->config->item('site_id');
        
        // Ensure preset table exists
        $this->ensureTableExists();
    }

    /**
     * Create a new preset
     * 
     * @param string $name Preset name
     * @param array $parameters Parameter array (tag-compatible format)
     * @param string $description Optional description
     * @param int $sample_file_id Optional EE Files reference for sample image
     * @return array Result array with success/error information
     */
    public function createPreset(
        string $name, 
        array $parameters, 
        string $description = '', 
        int $sample_file_id = null
    ): array {
        // Validate preset name
        $name_validation = $this->validatePresetName($name);
        if (!$name_validation['valid']) {
            return [
                'success' => false,
                'errors' => ['name' => $name_validation['error']]
            ];
        }

        // Check if preset name already exists for this site
        $existing = ee()->db->get_where($this->table_name, [
            'name' => $name,
            'site_id' => $this->site_id
        ]);
        
        if ($existing->num_rows() > 0) {
            return [
                'success' => false,
                'errors' => ['name' => "Preset '{$name}' already exists"]
            ];
        }

        // Note: Parameter validation is skipped during initial preset creation
        // Parameters will be validated later when they are added/modified

        // Validate sample file if provided
        if ($sample_file_id && !$this->validateSampleFile($sample_file_id)) {
            return [
                'success' => false,
                'errors' => ['sample_file_id' => 'Invalid sample file reference']
            ];
        }

        // Create preset using direct database insertion
        try {
            $preset_data = [
                'name' => $name,
                'parameters' => json_encode($parameters),
                'site_id' => $this->site_id,
                'description' => $description,
                'sample_file_id' => $sample_file_id ?: null,
                'created_date' => ee()->localize->now,
                'modified_date' => ee()->localize->now
            ];
            
            ee()->db->insert($this->table_name, $preset_data);
            $preset_id = ee()->db->insert_id();

            // Log creation
            $this->utilities->debug_log('preset_created_success', $name . ' (ID: ' . $preset_id . ')');

            return [
                'success' => true,
                'preset_id' => $preset_id,
                'message' => "Preset '{$name}' created successfully"
            ];
        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_create_failed', $name, $e->getMessage());
            
            return [
                'success' => false,
                'errors' => ['database' => 'Failed to save preset to database']
            ];
        }
    }

    /**
     * Read a preset by name
     * 
     * @param string $name Preset name
     * @return array|null Preset data or null if not found
     */
    public function getPreset(string $name): ?array
    {
        // Use direct database query instead of model
        $query = ee()->db
            ->where('site_id', $this->site_id)
            ->where('name', $name)
            ->get('jcogs_img_pro_presets');
            
        if ($query->num_rows() === 0) {
            return null;
        }
        
        $preset = $query->row_array();

        // Convert to array format expected by existing code
        $result = [
            'id' => $preset['id'],
            'site_id' => $preset['site_id'],
            'name' => $preset['name'],
            'description' => $preset['description'],
            'parameters' => json_decode($preset['parameters'] ?? '[]', true),
            'sample_file_id' => $preset['sample_file_id'],
            'created_date' => $preset['created_date'],
            'modified_date' => $preset['modified_date']
        ];
        
        // Validate sample file exists if referenced
        if ($preset['sample_file_id']) {
            $result['sample_file_exists'] = $this->validateSampleFile((int)$preset['sample_file_id']);
        } else {
            $result['sample_file_exists'] = false;
        }

        return $result;
    }

    /**
     * Read a preset by ID
     * 
     * @param int $id Preset ID
     * @return array|null Preset data or null if not found
     */
    public function getPresetById(int $id): ?array
    {
        $result = ee()->db->get_where($this->table_name, [
            'id' => $id,
            'site_id' => $this->site_id
        ]);

        if ($result->num_rows() === 0) {
            return null;
        }

        $preset = $result->row_array();
        
        // Decode JSON parameters
        $preset['parameters'] = json_decode($preset['parameters'], true);
        
        // Validate sample file exists if referenced
        if ($preset['sample_file_id']) {
            $preset['sample_file_exists'] = $this->validateSampleFile($preset['sample_file_id']);
        } else {
            $preset['sample_file_exists'] = false;
        }

        return $preset;
    }

    /**
     * Get all presets for current site
     * 
     * @param int $limit Optional limit
     * @param int $offset Optional offset for pagination
     * @return array Array of preset data
     */
    public function getAllPresets(int $limit = 0, int $offset = 0): array
    {
        $query = ee()->db
            ->select('*')
            ->from($this->table_name)
            ->where('site_id', $this->site_id)
            ->order_by('name', 'ASC');

        if ($limit > 0) {
            $query->limit($limit, $offset);
        }

        $result = $query->get();
        
        if ($result->num_rows() === 0) {
            return [];
        }

        $presets = [];
        foreach ($result->result_array() as $row) {
            // Decode JSON parameters
            $row['parameters'] = json_decode($row['parameters'], true);
            
            // Validate sample file exists if referenced
            if ($row['sample_file_id']) {
                $row['sample_file_exists'] = $this->validateSampleFile($row['sample_file_id']);
            } else {
                $row['sample_file_exists'] = false;
            }
            
            $presets[] = $row;
        }

        return $presets;
    }

    /**
     * Update an existing preset
     * 
     * @param string $name Current preset name
     * @param array $data Update data array
     * @return array Result array with success/error information
     */
    public function updatePreset(string $name, array $data): array
    {
        // Check if preset exists
        if (!$this->presetExists($name)) {
            return [
                'success' => false,
                'errors' => ['name' => "Preset '{$name}' not found"]
            ];
        }

        // Validate new name if provided
        if (isset($data['name']) && $data['name'] !== $name) {
            $name_validation = $this->validatePresetName($data['name']);
            if (!$name_validation['valid']) {
                return [
                    'success' => false,
                    'errors' => ['name' => $name_validation['error']]
                ];
            }

            // Check if new name already exists
            if ($this->presetExists($data['name'])) {
                return [
                    'success' => false,
                    'errors' => ['name' => "Preset '{$data['name']}' already exists"]
                ];
            }
        }

        // Validate parameters if provided
        if (isset($data['parameters'])) {
            $validation_errors = $this->validateParameters($data['parameters']);
            if (!empty($validation_errors)) {
                return [
                    'success' => false,
                    'errors' => $validation_errors
                ];
            }
            
            // Encode parameters as JSON
            $data['parameters'] = json_encode($data['parameters']);
        }

        // Validate sample file if provided
        if (isset($data['sample_file_id']) && $data['sample_file_id'] && !$this->validateSampleFile($data['sample_file_id'])) {
            return [
                'success' => false,
                'errors' => ['sample_file_id' => 'Invalid sample file reference']
            ];
        }

        // Add modified date
        $data['modified_date'] = ee()->localize->now;

        // Update database
        try {
            ee()->db
                ->where('site_id', $this->site_id)
                ->where('name', $name)
                ->update($this->table_name, $data);

            $affected_rows = ee()->db->affected_rows();
            
            if ($affected_rows === 0) {
                return [
                    'success' => false,
                    'errors' => ['database' => 'No changes made to preset']
                ];
            }

            $final_name = $data['name'] ?? $name;
            $this->utilities->debug_log('preset_updated_success', $final_name);

            return [
                'success' => true,
                'message' => "Preset '{$final_name}' updated successfully"
            ];
        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_update_failed', $name, $e->getMessage());
            
            return [
                'success' => false,
                'errors' => ['database' => 'Failed to update preset in database']
            ];
        }
    }

    /**
     * Delete a preset
     * 
     * @param string $name Preset name
     * @return array Result array with success/error information
     */
    public function deletePreset(string $name): array
    {
        // Check if preset exists
        if (!$this->presetExists($name)) {
            return [
                'success' => false,
                'errors' => ['name' => "Preset '{$name}' not found"]
            ];
        }

        // Delete from database
        try {
            ee()->db
                ->where('site_id', $this->site_id)
                ->where('name', $name)
                ->delete($this->table_name);

            $affected_rows = ee()->db->affected_rows();
            
            if ($affected_rows === 0) {
                return [
                    'success' => false,
                    'errors' => ['database' => 'Failed to delete preset']
                ];
            }

            $this->utilities->debug_log('preset_deleted_success', $name);

            return [
                'success' => true,
                'message' => "Preset '{$name}' deleted successfully"
            ];
        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_delete_failed', $name, $e->getMessage());
            
            return [
                'success' => false,
                'errors' => ['database' => 'Failed to delete preset from database']
            ];
        }
    }

    /**
     * Check if a preset exists
     * 
     * @param string $name Preset name
     * @return bool True if preset exists
     */
    public function presetExists(string $name): bool
    {
        $result = ee()->db->get_where($this->table_name, [
            'name' => $name,
            'site_id' => $this->site_id
        ]);
        
        return $result->num_rows() > 0;
    }

    /**
     * Get preset count for current site
     * 
     * @return int Number of presets
     */
    public function getPresetCount(): int
    {
        $query = ee()->db
            ->where('site_id', $this->site_id)
            ->get('jcogs_img_pro_presets');
            
        return $query->num_rows();
    }

    /**
     * Validate preset name
     * 
     * @param string $name Preset name to validate
     * @return array Validation result with 'valid' and 'error' keys
     */
    private function validatePresetName(string $name): array
    {
        // Check length
        if (empty($name)) {
            return ['valid' => false, 'error' => 'Preset name is required'];
        }

        if (strlen($name) > 100) {
            return ['valid' => false, 'error' => 'Preset name cannot exceed 100 characters'];
        }

        // Check for valid characters (alphanumeric, underscore, hyphen - no spaces)
        if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $name)) {
            return ['valid' => false, 'error' => 'Preset name can only contain letters, numbers, underscores, and hyphens (no spaces)'];
        }

        // Additional check to prevent names starting with numbers or special characters
        if (!preg_match('/^[a-zA-Z]/', $name)) {
            return ['valid' => false, 'error' => 'Preset name must start with a letter'];
        }

        return ['valid' => true, 'error' => ''];
    }

    /**
     * Validate parameters using parameter packages
     * 
     * @param array $parameters Parameter array to validate
     * @return array Validation errors (empty if valid)
     */
    private function validateParameters(array $parameters): array
    {
        if (empty($parameters)) {
            return ['parameters' => 'At least one parameter is required'];
        }

        // Use parameter package discovery for validation
        return $this->packageDiscovery->validateAllParameters($parameters);
    }

    /**
     * Validate sample file reference
     * 
     * @param int $file_id EE Files file ID
     * @return bool True if file exists and is valid
     */
    private function validateSampleFile(int $file_id): bool
    {
        // Check if file exists in EE Files
        $query = ee()->db
            ->select('file_id, file_name, mime_type')
            ->from('files')
            ->where('file_id', $file_id)
            ->limit(1)
            ->get();

        if ($query->num_rows() === 0) {
            return false;
        }

        $file = $query->row_array();
        
        // Check if it's an image file
        $valid_mime_types = [
            'image/jpeg',
            'image/jpg', 
            'image/png',
            'image/gif',
            'image/webp'
        ];

        return in_array($file['mime_type'], $valid_mime_types);
    }

    /**
     * Get form fields for preset creation/editing
     * Uses parameter package discovery to generate all available fields
     * 
     * @param array $current_values Current parameter values for editing
     * @param array $package_filter Optional array of package names to include
     * @return array EE-compatible form field definitions
     */
    public function getPresetFormFields(array $current_values = [], array $package_filter = []): array
    {
        return $this->packageDiscovery->getAllFormFields($current_values, $package_filter);
    }

    /**
     * Transform form data to parameters format
     * Converts Control Panel form submission to tag-compatible parameters
     * 
     * @param array $form_data Raw form data from Control Panel
     * @return array Tag-compatible parameter array
     */
    public function transformFormToParameters(array $form_data): array
    {
        $parameters = [];
        $packages = $this->packageDiscovery->getPackages(true);

        foreach ($packages as $package) {
            $package_parameters = $package->transformFormToParameters($form_data);
            $parameters = array_merge($parameters, $package_parameters);
        }

        return $parameters;
    }

    /**
     * Transform parameters to form data format
     * Converts tag parameters to Control Panel form values
     * 
     * @param array $parameters Tag parameter values
     * @return array Form-compatible values
     */
    public function transformParametersToForm(array $parameters): array
    {
        $form_data = [];
        $packages = $this->packageDiscovery->getPackages(true);

        foreach ($packages as $package) {
            $package_form_data = $package->transformParametersToForm($parameters);
            $form_data = array_merge($form_data, $package_form_data);
        }

        return $form_data;
    }

    /**
     * List all presets for current site
     * 
     * @param array $options Optional filtering and sorting options
     * @return array Array of preset data
     * @throws \Exception If database query fails
     */
    public function listPresets(array $options = []): array
    {
        try {
            $this->ensureTableExists();
            
            // Start building the query
            $db = ee()->db;
            $db->where('site_id', $this->site_id);
            
            // Apply filtering if specified
            if (!empty($options['name_filter'])) {
                $db->like('name', $options['name_filter']);
            }
            
            // Apply sorting
            $sort_field = $options['sort'] ?? 'name';
            $sort_direction = $options['direction'] ?? 'asc';
            $db->order_by($sort_field, $sort_direction);
            
            // Apply pagination if specified
            if (!empty($options['limit'])) {
                $offset = $options['offset'] ?? 0;
                $db->limit($options['limit'], $offset);
            }
            
            $query = $db->get('jcogs_img_pro_presets');
            $results = $query->result_array();
            
            // Convert to expected format
            $preset_list = [];
            foreach ($results as $preset) {
                $preset_list[] = [
                    'id' => $preset['id'],
                    'name' => $preset['name'],
                    'description' => $preset['description'],
                    'parameters' => json_decode($preset['parameters'] ?? '[]', true),
                    'created_date' => $preset['created_date'],
                    'modified_date' => $preset['modified_date']
                ];
            }
            
            return $preset_list;
            
        } catch (\Exception $e) {
            throw new \Exception('Failed to list presets: ' . $e->getMessage());
        }
    }
    
    /**
     * Ensure the preset table exists
     * Creates the table if it doesn't exist using the same structure as the migration
     * 
     * @return void
     */
    private function ensureTableExists(): void
    {
        // Check if table exists
        if (!ee()->db->table_exists($this->table_name)) {
            $this->createPresetTable();
        }
    }
    
    /**
     * Create the preset table
     * Uses the same structure as the migration file
     * 
     * @return void
     */
    private function createPresetTable(): void
    {
        // Define table fields following EE7 migration patterns
        $fields = [
            'id' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false,
                'auto_increment' => true
            ],
            'site_id' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false
            ],
            'name' => [
                'type' => 'varchar',
                'constraint' => 100,
                'null' => false
            ],
            'description' => [
                'type' => 'text',
                'null' => true
            ],
            'parameters' => [
                'type' => 'text', // Use text instead of json for wider compatibility
                'null' => false
            ],
            'sample_file_id' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => true
            ],
            'created_date' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false
            ],
            'modified_date' => [
                'type' => 'int',
                'constraint' => 10,
                'unsigned' => true,
                'null' => false
            ]
        ];

        // Create the table
        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('id', true);
        ee()->dbforge->create_table($this->table_name);

        // Add indexes for better performance
        ee()->db->query("CREATE INDEX idx_site_id ON {$this->table_name} (site_id)");
        ee()->db->query("CREATE INDEX idx_name ON {$this->table_name} (name)");
        ee()->db->query("CREATE UNIQUE INDEX idx_unique_preset ON {$this->table_name} (site_id, name)");

        $this->utilities->debug_log('preset_table_created', $this->table_name);
    }

    /**
     * Export preset as JSON for backup/sharing
     * 
     * @param int $preset_id Preset ID to export
     * @return array Result array with success/error information and JSON data
     */
    public function exportPreset(int $preset_id): array
    {
        try {
            $preset = $this->getPresetById($preset_id);
            
            if (!$preset) {
                return [
                    'success' => false,
                    'errors' => ['Preset not found']
                ];
            }
            
            // Create export data structure
            $export_data = [
                'jcogs_img_pro_preset' => [
                    'version' => '2.0.0-alpha4',
                    'export_date' => date('Y-m-d H:i:s'),
                    'preset' => [
                        'name' => $preset['name'],
                        'description' => $preset['description'],
                        'parameters' => $preset['parameters'],
                        'created_date' => $preset['created_date'],
                        'modified_date' => $preset['modified_date']
                    ]
                ]
            ];
            
            $json_output = json_encode($export_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            
            if ($json_output === false) {
                return [
                    'success' => false,
                    'errors' => ['Failed to encode preset data as JSON']
                ];
            }
            
            return [
                'success' => true,
                'json_data' => $json_output,
                'filename' => 'preset_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $preset['name']) . '.json'
            ];
            
        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_export_error', $e->getMessage());
            return [
                'success' => false,
                'errors' => ['Export failed: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Import preset from JSON data
     * 
     * @param string $json_data JSON string containing preset data
     * @param array $options Import options (overwrite, auto_rename)
     * @return array Result array with success/error information
     */
    public function importPreset(string $json_data, array $options = []): array
    {
        try {
            // Parse JSON data
            $import_data = json_decode($json_data, true);
            
            if ($import_data === null) {
                return [
                    'success' => false,
                    'errors' => ['Invalid JSON data']
                ];
            }
            
            // Validate import structure
            if (!isset($import_data['jcogs_img_pro_preset']['preset'])) {
                return [
                    'success' => false,
                    'errors' => ['Invalid preset format - missing preset data']
                ];
            }
            
            $preset_data = $import_data['jcogs_img_pro_preset']['preset'];
            $preset_name = $preset_data['name'];
            $preset_description = $preset_data['description'] ?? '';
            $preset_parameters = $preset_data['parameters'] ?? [];
            
            // Check if preset with same name exists
            $existing = ee()->db->get_where($this->table_name, [
                'name' => $preset_name,
                'site_id' => $this->site_id
            ]);
            
            if ($existing->num_rows() > 0) {
                if ($options['overwrite'] ?? false) {
                    // Update existing preset
                    $result = $this->updatePreset($existing->row()->id, $preset_name, $preset_parameters, $preset_description);
                    
                    if ($result['success']) {
                        return [
                            'success' => true,
                            'preset_name' => $preset_name,
                            'action' => 'updated'
                        ];
                    } else {
                        return $result;
                    }
                } elseif ($options['auto_rename'] ?? true) {
                    // Generate unique name
                    $base_name = $preset_name;
                    $counter = 1;
                    
                    do {
                        $preset_name = $base_name . " ({$counter})";
                        $check = ee()->db->get_where($this->table_name, [
                            'name' => $preset_name,
                            'site_id' => $this->site_id
                        ]);
                        $counter++;
                    } while ($check->num_rows() > 0);
                } else {
                    return [
                        'success' => false,
                        'errors' => ['Preset with name "' . $preset_name . '" already exists']
                    ];
                }
            }
            
            // Create new preset
            $result = $this->createPreset($preset_name, $preset_parameters, $preset_description);
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'preset_name' => $preset_name,
                    'action' => 'created'
                ];
            } else {
                return $result;
            }
            
        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_import_error', $e->getMessage());
            return [
                'success' => false,
                'errors' => ['Import failed: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Duplicate an existing preset with new name
     * 
     * @param int $preset_id Original preset ID
     * @param string $new_name New preset name
     * @param string $new_description New preset description (optional)
     * @return array Result array with success/error information
     */
    public function duplicatePreset(int $preset_id, string $new_name, string $new_description = ''): array
    {
        try {
            $original_preset = $this->getPresetById($preset_id);
            
            if (!$original_preset) {
                return [
                    'success' => false,
                    'errors' => ['Original preset not found']
                ];
            }
            
            // Use original description if new one is empty
            if (empty($new_description)) {
                $new_description = $original_preset['description'];
            }
            
            // Create new preset with original parameters
            $result = $this->createPreset(
                $new_name,
                $original_preset['parameters'],
                $new_description,
                $original_preset['sample_file_id'] ?? null
            );
            
            if ($result['success']) {
                return [
                    'success' => true,
                    'preset_id' => $result['preset_id'],
                    'preset_name' => $new_name
                ];
            } else {
                return $result;
            }
            
        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_duplicate_error', $e->getMessage());
            return [
                'success' => false,
                'errors' => ['Duplication failed: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Load preset by ID (alias for getPresetById for route compatibility)
     * 
     * @param int $preset_id Preset ID
     * @return array|null Preset data or null if not found
     */
    public function loadPreset(int $preset_id): ?array
    {
        return $this->getPresetById($preset_id);
    }

    // =========================================================================
    // ANALYTICS METHODS
    // =========================================================================

    /**
     * Track preset usage (increment usage count and update last used date)
     * 
     * @param int $preset_id Preset ID
     * @param float $execution_time Optional execution time in seconds
     * @return bool Success status
     */
    public function trackPresetUsage(int $preset_id, float $execution_time = null): bool
    {
        try {
            // Update usage statistics atomically
            ee()->db->set('usage_count', 'usage_count + 1', false)
                   ->set('last_used_date', time())
                   ->where('id', $preset_id)
                   ->where('site_id', $this->site_id)
                   ->update($this->table_name);
                   
            // Update performance data if execution time provided
            if ($execution_time !== null) {
                $this->updatePerformanceData($preset_id, $execution_time);
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_usage_tracking_error', $e->getMessage());
            return false;
        }
    }

    /**
     * Track preset error (increment error count and update last error date)
     * 
     * @param int $preset_id Preset ID
     * @param string $error_message Error description
     * @return bool Success status
     */
    public function trackPresetError(int $preset_id, string $error_message = ''): bool
    {
        try {
            ee()->db->set('error_count', 'error_count + 1', false)
                   ->set('last_error_date', time())
                   ->where('id', $preset_id)
                   ->where('site_id', $this->site_id)
                   ->update($this->table_name);
                   
            // Log the error for debugging
            if (!empty($error_message)) {
                $this->utilities->debug_log('preset_error_tracked', "Preset ID {$preset_id}: {$error_message}");
            }
            
            return true;
            
        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_error_tracking_error', $e->getMessage());
            return false;
        }
    }

    /**
     * Get analytics data for a specific preset
     * 
     * @param int $preset_id Preset ID
     * @return array Analytics data
     */
    public function getPresetAnalytics(int $preset_id): array
    {
        try {
            $preset = ee()->db->select('id, name, usage_count, last_used_date, error_count, last_error_date, performance_data, created_date')
                            ->where('id', $preset_id)
                            ->where('site_id', $this->site_id)
                            ->get($this->table_name)
                            ->row_array();

            if (!$preset) {
                return [
                    'success' => false,
                    'error' => 'Preset not found'
                ];
            }

            // Parse performance data if available
            $performance_data = [];
            if (!empty($preset['performance_data'])) {
                $performance_data = json_decode($preset['performance_data'], true) ?: [];
            }

            return [
                'success' => true,
                'preset_id' => $preset['id'],
                'preset_name' => $preset['name'],
                'usage_count' => (int)$preset['usage_count'],
                'last_used_date' => $preset['last_used_date'] ? (int)$preset['last_used_date'] : null,
                'error_count' => (int)$preset['error_count'],
                'last_error_date' => $preset['last_error_date'] ? (int)$preset['last_error_date'] : null,
                'created_date' => (int)$preset['created_date'],
                'performance' => $performance_data,
                'days_since_creation' => floor((time() - $preset['created_date']) / 86400),
                'avg_daily_usage' => $this->calculateAverageDaily($preset['usage_count'], $preset['created_date'])
            ];

        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_analytics_error', $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to retrieve analytics data'
            ];
        }
    }

    /**
     * Get usage summary for all presets (for main analytics view)
     * 
     * @param string $timeframe Timeframe filter: 'all', '30days', '7days'
     * @return array Analytics summary
     */
    public function getPresetUsageSummary(string $timeframe = 'all'): array
    {
        try {
            $query = ee()->db->select('id, name, usage_count, last_used_date, error_count, created_date')
                           ->where('site_id', $this->site_id);

            // Apply timeframe filter
            if ($timeframe === '30days') {
                $query->where('last_used_date >=', time() - (30 * 86400));
            } elseif ($timeframe === '7days') {
                $query->where('last_used_date >=', time() - (7 * 86400));
            }

            $presets = $query->order_by('usage_count', 'DESC')
                           ->get($this->table_name)
                           ->result_array();

            $summary = [
                'total_presets' => count($presets),
                'total_usage' => 0,
                'total_errors' => 0,
                'most_popular' => [],
                'least_used' => [],
                'error_prone' => [],
                'timeframe' => $timeframe
            ];

            foreach ($presets as $preset) {
                $summary['total_usage'] += $preset['usage_count'];
                $summary['total_errors'] += $preset['error_count'];

                // Track most popular (top 5)
                if (count($summary['most_popular']) < 5) {
                    $summary['most_popular'][] = [
                        'id' => $preset['id'],
                        'name' => $preset['name'],
                        'usage_count' => (int)$preset['usage_count']
                    ];
                }

                // Track error-prone presets (error rate > 5%)
                if ($preset['usage_count'] > 0) {
                    $error_rate = ($preset['error_count'] / $preset['usage_count']) * 100;
                    if ($error_rate > 5) {
                        $summary['error_prone'][] = [
                            'id' => $preset['id'],
                            'name' => $preset['name'],
                            'error_rate' => round($error_rate, 1),
                            'error_count' => (int)$preset['error_count'],
                            'usage_count' => (int)$preset['usage_count']
                        ];
                    }
                }
            }

            // Find least used (usage_count = 0 or very low)
            $summary['least_used'] = array_filter($presets, function($preset) {
                return $preset['usage_count'] <= 1;
            });
            $summary['least_used'] = array_slice($summary['least_used'], 0, 5);

            return $summary;

        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_usage_summary_error', $e->getMessage());
            return [
                'success' => false,
                'error' => 'Failed to generate usage summary'
            ];
        }
    }

    /**
     * Update performance data for a preset
     * 
     * @param int $preset_id Preset ID
     * @param float $execution_time Execution time in seconds
     */
    private function updatePerformanceData(int $preset_id, float $execution_time): void
    {
        try {
            // Get current performance data
            $current = ee()->db->select('performance_data')
                             ->where('id', $preset_id)
                             ->get($this->table_name)
                             ->row_array();

            $performance_data = [];
            if (!empty($current['performance_data'])) {
                $performance_data = json_decode($current['performance_data'], true) ?: [];
            }

            // Initialize if empty
            if (empty($performance_data)) {
                $performance_data = [
                    'total_execution_time' => 0,
                    'execution_count' => 0,
                    'avg_execution_time' => 0
                ];
            }

            // Update performance metrics
            $performance_data['total_execution_time'] += $execution_time;
            $performance_data['execution_count']++;
            $performance_data['avg_execution_time'] = $performance_data['total_execution_time'] / $performance_data['execution_count'];

            // Update in database
            ee()->db->set('performance_data', json_encode($performance_data))
                   ->where('id', $preset_id)
                   ->update($this->table_name);

        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_performance_update_error', $e->getMessage());
        }
    }

    /**
     * Calculate average daily usage
     * 
     * @param int $usage_count Total usage count
     * @param int $created_date Creation timestamp
     * @return float Average daily usage
     */
    private function calculateAverageDaily(int $usage_count, int $created_date): float
    {
        $days_active = max(1, floor((time() - $created_date) / 86400));
        return round($usage_count / $days_active, 2);
    }

    /**
     * Reset all statistics for a preset
     * 
     * @param int $preset_id Preset ID
     * @return bool Success status
     */
    public function resetPresetStatistics(int $preset_id): bool
    {
        try {
            $result = ee()->db->set([
                'usage_count' => 0,
                'last_used_date' => null,
                'error_count' => 0,
                'last_error_date' => null,
                'performance_data' => null
            ])
            ->where('id', $preset_id)
            ->where('site_id', $this->site_id)
            ->update($this->table_name);

            if ($result) {
                $this->utilities->debug_log('preset_statistics_reset', "Statistics reset for preset ID: {$preset_id}");
                return true;
            } else {
                $this->utilities->debug_log('preset_statistics_reset_failed', "Failed to reset statistics for preset ID: {$preset_id}");
                return false;
            }

        } catch (\Exception $e) {
            $this->utilities->debug_log('preset_statistics_reset_error', $e->getMessage());
            return false;
        }
    }
}
