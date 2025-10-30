<?php

/**
 * JCOGS Image Pro - Cache Management Route
 * =========================================
 * Advanced cache configuration and management with named filesystem connections
 * 
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  Copyright (c) 2021 - 2025 JCOGS Design
 * @license    https://jcogs.net/add-ons/license/jcogs_img_pro
 * @version    2.0.0-beta7
 * @link       https://JCOGS.net/
 * @since      Phase 3 Legacy Independence - Named Connections System
 */

namespace JCOGSDesign\JCOGSImagePro\ControlPanel\Routes;

use Exception;
use ExpressionEngine\Library\CP\Table;

class Caching extends ImageAbstractRoute
{
    /**
     * @var string Route path for URL generation
     */
    protected $route_path = 'caching';

    /**
     * @var string Control panel page title
     */
    protected $cp_page_title;
    
    /**
     * @var array|null Cached named filesystem adapters configuration
     */
    private $cached_named_adapters_config = null;
    
    /**
     * @var array|null Cached named connections (connections only)
     */
    private $cached_named_connections = null;
    
    /**
     * Main cache management page processor
     * 
     * Handles all cache management requests including actions via URL segments.
     * EE7 MCP routing calls this method for all requests to this route.
     * 
     * @param mixed $id Route parameter (connection name for actions)
     * @return $this Fluent interface for EE7 routing
     */
    public function process($id = false)
    {
        // Check if this is a POST request with form data - redirect to proper route
        if (count($_POST) > 0) {
            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/update_settings'));
            return $this;
        }
        
        // Load language files
        $this->load_language();
        $this->load_language('jcogs_img_pro_parameters');
        
        // Load CSS and JavaScript assets
        $this->_load_cache_management_assets();
                
        // Set page title and navigation
        $this->cp_page_title = lang('jcogs_img_pro_cp_cache_management');
        $this->build_sidebar($this->_get_current_settings());
        $this->addBreadcrumb('caching', $this->cp_page_title);

        // Build the main page content
        $variables = $this->_build_page_content();

        // Set the page body using EE7 view system
        $this->setBody('cache_management', $variables);

        return $this;
    }

    /**
     * Build all page content sections
     * 
     * @return array Template variables for the view
     */
    private function _build_page_content(): array
    {
        // Get current settings and cache information
        $current_settings = $this->_get_current_settings();
        $named_connections = $this->_get_named_connections();
        
        // Create a single form for the entire page (like legacy approach)
        $form = ee('CP/Form');
        
        // Set the base URL where the form should be processed (EE7 method)
        $form_action_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/update_settings');
        $form->setBaseUrl($form_action_url);
        
        // Configure form to not show automatic buttons
        $form->set('save_btn_text', 'Save Cache Settings');
        $form->set('save_btn_text_working', 'Saving');
        $form->set('hide_top_buttons', true);  // Hide the top buttons
        
        // Build sections as groups within the main form
        $this->_build_cache_status_group($form);
        $this->_build_cache_locations_group($form);  // Add Cache Locations group
        $this->_build_existing_connections_group($form, $named_connections); // Add Existing Connections group
        $this->_build_cache_controls_group($form, $named_connections);
        $this->_build_cache_settings_group($form, $current_settings);
        
        // Get cache locations table for the cache status section
        $cache_locations_table = null;
        try {
            $cache_stats = $this->_calculate_cache_statistics();
            if (!empty($cache_stats['connections'])) {
                $cache_locations_table = $this->_build_cache_locations_table($cache_stats['connections']);
            }
        } catch (Exception $e) {
            // Table will remain null if there's an error
        }

        $variables = [
            'cp_page_title' => $this->cp_page_title,
            'base_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching'),
            
            // Single main form containing all sections as groups
            'main_form' => $form,
            'form_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/update_settings'),
            
            // Cache locations table for inclusion in the form
            'cache_locations_table' => $cache_locations_table,
            
            // We still need the existing connections section as a separate table (not part of the form)
            // 'existing_connections_section' => $this->_build_existing_connections_section($named_connections),
        ];

        return $variables;
    }

    /**
     * Build the Existing Connections section
     * 
     * @param array $named_connections Current named connections
     * @return array Section data for template
     */
    private function _build_existing_connections_section(array $named_connections): array
    {
        // Create the table with enhanced styling to match cache locations table
        $table = ee('CP/Table', [
            'autosort' => true,
            'autosearch' => false,
            'sortable' => true,
            'class' => 'tbl-ctrls tbl-fixed',
            'table_attrs' => [
                'class' => 'table table-striped table-hover'
            ]
        ]);
        
        // Set the columns with enhanced configuration
        $table->setColumns([
            'name' => [
                'label' => 'Connection Name',
                'sort' => true,
                'encode' => false,
                'class' => 'highlight'
            ],
            'type' => [
                'label' => 'Type',
                'sort' => true,
                'encode' => false,
                'class' => 'center'
            ],
            'cache_path' => [
                'label' => 'Cache Path',
                'sort' => true
            ],
            'status' => [
                'label' => 'Status',
                'sort' => true,
                'encode' => false,
                'class' => 'center'
            ],
            'actions' => [
                'label' => 'Actions',
                'type' => Table::COL_TOOLBAR,
                'sort' => false,
                'class' => 'center'
            ]
        ]);
        
        // Set no results message
        $table->setNoResultsText(
            'No named connections found.',
            'Add New Connection',
            ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/add_connection')
        );
        
        // Build table data from named connections
        $table_data = [];
        $current_default = $this->_get_default_connection();
        
        foreach ($named_connections as $name => $connection) {
            $is_default = ($name === $current_default);
            $is_valid = $connection['is_valid'] ?? false;
            
            // Build connection name with default indicator
            $name_content = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
            if ($is_default) {
                $name_content .= ' <span class="st-open" style="margin-left: 0.5rem;">DEFAULT</span>';
            }
            
            // Get cache path from connection config
            $cache_path = $this->_get_connection_cache_path($connection);
            
            // Build connection type with consistent styling
            $type_content = '<span class="st-' . strtolower($connection['type'] ?? 'unknown') . '">' . strtoupper($connection['type'] ?? 'unknown') . '</span>';
            
            // Build status with proper visual styling matching cache locations table
            $status_content = '';
            if ($is_valid) {
                $status_content = '<span style="background-color: #f0fdf4; color: #16a34a; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Valid</span>';
            } else {
                $status_content = '<span style="background-color: #fef2f2; color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Invalid</span>';
            }
            
            // Build toolbar actions with proper icons (no duplicate classes)
            $toolbar_items = [
                'edit' => [
                    'href' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/edit_connection', ['connection' => $name]),
                    'title' => 'Edit Connection',
                    'content' => ''
                ],
                'delete' => [
                    'href' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/delete_connection/' . urlencode($name)),
                    'title' => 'Delete Connection',
                    'data-connection' => $name,
                    'data-confirm' => 'Delete connection &quot;' . $name . '&quot;?',
                    'content' => '<i class="fas fa-trash-alt"></i> Clear'
                ]
            ];
            
            $table_data[] = [
                $name_content,
                $type_content,
                $cache_path,
                $status_content,
                ['toolbar_items' => $toolbar_items]
            ];
        }
        
        $table->setData($table_data);
        
        // Return the table data for the view to render
        return [
            'title' => 'Existing Connections',
            'table' => $table,
            'add_connection_url' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/add_connection')
        ];
    }

    /**
     * Build Cache Locations Table using CP/Table
     * 
     * Creates a proper EE7 CP/Table for cache location details with statistics
     * and action buttons. Migrated from manual HTML table in cache_status.php view.
     * 
     * @param array $connections Cache connection data with statistics
     * @return Table EE7 CP/Table instance
     */
    private function _build_cache_locations_table(array $connections): Table
    {
        // Create the table with enhanced styling
        $table = ee('CP/Table', [
            'autosort' => true,
            'autosearch' => false,
            'sortable' => true,
            'class' => 'tbl-ctrls tbl-fixed cache-locations-table',
            'table_attrs' => [
                'class' => 'table table-striped table-hover'
            ]
        ]);
        
        // Set the columns for cache location details
        $table->setColumns([
            'location' => [
                'label' => 'Cache Location',
                'sort' => true,
                'encode' => false,
                'class' => 'highlight'
            ],
            'type' => [
                'label' => 'Type',
                'sort' => true,
                'encode' => false,
                'class' => 'center'
            ],
            'files' => [
                'label' => 'Files',
                'sort' => true,
                'class' => 'center'
            ],
            'size' => [
                'label' => 'Size',
                'sort' => true,
                'class' => 'center'
            ],
            'status' => [
                'label' => 'Status',
                'sort' => true,
                'encode' => false,
                'class' => 'center'
            ],
            'last_updated' => [
                'label' => 'Last Updated',
                'sort' => true,
                'class' => 'center'
            ],
            'actions' => [
                'label' => 'Actions',
                'type' => Table::COL_TOOLBAR,
                'sort' => false,
                'class' => 'center'
            ]
        ]);
        
        // Set no results message
        $table->setNoResultsText(
            'No cache locations found.',
            'Check Connections',
            ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching')
        );
        
        // Build table data from cache connections
        $table_data = [];
        
        foreach ($connections as $location_key => $location) {
            // Build location display with connection name using EE7 patterns
            $location_content = '<div class="cache-location-info">';
            $location_content .= '<div class="highlight" style="font-weight: 600;">' . htmlspecialchars($location['cache_dir'], ENT_QUOTES, 'UTF-8') . '</div>';
            $location_content .= '<div class="meta-info" style="font-size: 0.875rem; color: #6b7280; margin-top: 0.125rem;">' . htmlspecialchars($location['connection_name'], ENT_QUOTES, 'UTF-8') . '</div>';
            $location_content .= '</div>';
            
            // Build adapter type badge with simpler styling
            $type_content = '<span class="st-' . strtolower($location['adapter_type']) . '">' . strtoupper($location['adapter_type']) . '</span>';
            
            // Format file count
            $file_count = number_format($location['file_count']);
            
            // Format size
            $size_formatted = $location['size_formatted'];
            
            // Build status with proper visual styling instead of EE7's generic status
            $status_content = '';
            if ($location['status'] === 'valid') {
                $status_content = '<span style="background-color: #f0fdf4; color: #16a34a; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Valid</span>';
            } else {
                $status_content = '<span style="background-color: #fef2f2; color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Invalid</span>';
            }
            
            // Format last updated time
            $last_updated = $location['last_updated'];
            
            // Build toolbar actions (now enabled with proper EE7 routing)
            $toolbar_items = [];
            
            if ($location['file_count'] > 0 && $location['status'] === 'valid') {
                $toolbar_items = [
                    'audit' => [
                        'href' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/audit_cache/' . urlencode($location['connection_name'])),
                        'title' => 'Run audit to clean expired cache files',
                        'class' => 'cache-audit-btn',
                        'data-connection' => $location['connection_name'],
                        'data-action' => 'audit',
                        'content' => '<i class="fas fa-broom"></i>'
                    ],
                    'clear' => [
                        'href' => ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/clear_cache/' . urlencode($location['connection_name'])),
                        'title' => 'Clear all cache files from this location',
                        'class' => 'cache-clear-btn',
                        'data-connection' => $location['connection_name'],
                        'data-action' => 'clear',
                        'data-confirm' => 'Clear all cache files from "' . $location['connection_name'] . '"?',
                        'content' => '<i class="fas fa-trash-alt"></i>'
                    ]
                ];
            }
            
            $table_data[] = [
                $location_content,
                $type_content,
                $file_count,
                $size_formatted,
                $status_content,
                $last_updated,
                ['toolbar_items' => $toolbar_items]
            ];
        }
        
        $table->setData($table_data);
        
        return $table;
    }

    /**
     * Build the Image Cache Settings group within the main form
     * 
     * @param \ExpressionEngine\Library\CP\Form $form The main form
     * @param array $current_settings Current addon settings
     * @return void
     */
    private function _build_cache_settings_group($form, array $current_settings): void
    {
        // Create group for Image Cache Settings
        $settings_group = $form->getGroup(lang('jcogs_img_cp_cache_settings'));
        
        // ENHANCED: Default cache duration with natural language support
        $duration_fieldset = $settings_group->getFieldSet(lang('jcogs_img_pro_cp_default_cache_duration'));
        $duration_fieldset->setDesc(lang('jcogs_img_pro_cp_default_cache_duration_desc'));
        
        // Initialize duration services
        try {
            $duration_parser = new \JCOGSDesign\JCOGSImagePro\Service\DurationParser();
            $duration_form_field = new \JCOGSDesign\JCOGSImagePro\Service\DurationFormField($duration_parser);
            
            // Use enhanced duration field with natural language support
            $current_duration = $current_settings['img_cp_default_cache_duration'] ?? '2678400';
            $duration_field = $duration_form_field->createField(
                $duration_fieldset, 
                'img_cp_default_cache_duration', 
                $current_duration, 
                'cache'
            );
            
            // Add help text for better UX
            $help_text = $duration_form_field->getHelpText('cache');
            $duration_fieldset->setDesc(lang('jcogs_img_pro_cp_default_cache_duration_desc') . '<br><small style="color: #64748b;">' . $help_text . '</small>');
            
        } catch (Exception $e) {
            // Fallback to basic field if duration services fail
            $duration_fieldset->getField('img_cp_default_cache_duration', 'text')
                ->setValue($current_settings['img_cp_default_cache_duration'] ?? '2678400');
        }
        
        // Cache Auto-Management
        $auto_manage_fieldset = $settings_group->getFieldSet(lang('jcogs_img_cp_enable_cache_auto_manage'));
        $auto_manage_fieldset->setDesc(lang('jcogs_img_cp_enable_cache_auto_manage_desc'));
        
        $auto_manage_fieldset->getField('img_cp_cache_auto_manage', 'yes_no')
            ->setValue($current_settings['img_cp_cache_auto_manage'] ?? 'y');
    }

    /**
     * Build the Default Cache Location group within the main form
     * 
     * @param \ExpressionEngine\Library\CP\Form $form The main form
     * @param array $named_connections Available named connections
     * @return void
     */
    private function _build_cache_controls_group($form, array $named_connections): void
    {
        // Create group for Default Cache Location
        $controls_group = $form->getGroup('Default Cache Location');
        
        // Default cache connection dropdown
        $connection_fieldset = $controls_group->getFieldSet('Default Cache Connection');
        $connection_fieldset->setDesc('Choose which named connection to use as the default cache location for new images.');
        
        // Build options for the dropdown
        $options = ['' => 'Select a connection...'];
        foreach ($named_connections as $connection_name => $config) {
            // Skip invalid connections
            if (!($config['is_valid'] ?? false)) {
                continue;
            }
            
            $adapter_type = $config['type'] ?? 'local';
            $type_label = strtoupper($adapter_type);
            $options[$connection_name] = $connection_name . ' (' . $type_label . ')';
        }
        
        // Get current default connection
        $current_default = $this->_get_default_connection();
        
        $connection_field = $connection_fieldset->getField('default_cache_connection', 'select');
        $connection_field->set('choices', $options);
        $connection_field->setValue($current_default);
        // Set attributes using the proper EE7 method - try the attrs property as a string
        $connection_field->set('attrs', 'autocomplete="off"');
    }

    /**
     * Build the Image Cache Status group within the main form
     * 
     * @param \ExpressionEngine\Library\CP\Form $form The main form
     * @return void
     */
    private function _build_cache_status_group($form): void
    {
        // Create group for Image Cache Status
        $status_group = $form->getGroup('Image Cache Status');
        
        // Build cache statistics HTML boxes
        try {
            $cache_stats = $this->_calculate_cache_statistics();
            
            // Create three horizontal boxes for the statistics using CSS classes
            $status_html = '<div class="cache-statistics-container">';
            
            // Box 1: Processed images stored in cache
            $status_html .= '<div class="cache-stat-box primary">';
            $status_html .= '<div class="cache-stat-value primary">' . number_format($cache_stats['total_files']) . '</div>';
            $status_html .= '<div class="cache-stat-label primary">Processed images stored in cache</div>';
            $status_html .= '</div>';
            
            // Box 2: Total size of stored processed images
            $status_html .= '<div class="cache-stat-box success">';
            $status_html .= '<div class="cache-stat-value success">' . $cache_stats['total_size_formatted'] . '</div>';
            $status_html .= '<div class="cache-stat-label success">Total size of stored processed images</div>';
            $status_html .= '</div>';
            
            // Box 3: Number of active cache locations (only those with content)
            $status_html .= '<div class="cache-stat-box info">';
            $status_html .= '<div class="cache-stat-value info">' . number_format(count($cache_stats['connections'])) . '</div>';
            $status_html .= '<div class="cache-stat-label info">Number of active cache locations</div>';
            $status_html .= '</div>';
            
            $status_html .= '</div>';
            
        } catch (Exception $e) {
            // Error state with single box using CSS classes
            $status_html = '<div class="cache-error-message">';
            $status_html .= '<strong>Error calculating cache statistics:</strong><br>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
            $status_html .= '</div>';
        }
        
        $status_fieldset = $status_group->getFieldSet('Cache Statistics');
        $status_fieldset->setDesc('Current cache status across all configured connections.');
        
        // Use an HTML field to display the formatted statistics boxes
        // Note: getField() returns Html instance at runtime, despite Field return type annotation
        $status_fieldset->getField('cache_status_display', 'html')
            ->setContent($status_html);
    }

    /**
     * Build the Cache Locations group within the main form
     * 
     * @param \ExpressionEngine\Library\CP\Form $form The main form
     * @return void
     */
    private function _build_cache_locations_group($form): void
    {
        // Create group for Cache Locations
        $locations_group = $form->getGroup('Cache Locations');
        
        try {
            $cache_stats = $this->_calculate_cache_statistics();
            if (!empty($cache_stats['connections'])) {
                $cache_locations_table = $this->_build_cache_locations_table($cache_stats['connections']);
                
                // Add the table using EE7's Table field type
                $table_fieldset = $locations_group->getFieldSet('Cache Location Details');
                $table_fieldset->setDesc('Detailed breakdown of cache files by connection.');
                
                // Test array format approach alongside current working implementation
                $table_field = $table_fieldset->getField('cache_locations_table', 'table');
                
                // Set up the actual cache locations table with real columns
                $table_field->setColumns([
                    'location' => ['label' => 'Cache Location', 'sort' => true, 'encode' => false],
                    'type' => ['label' => 'Type', 'sort' => true, 'class' => 'center', 'encode' => false],
                    'files' => ['label' => 'Files', 'sort' => true, 'class' => 'center'],
                    'size' => ['label' => 'Size', 'sort' => true, 'class' => 'center'],
                    'status' => ['label' => 'Status', 'sort' => true, 'class' => 'center', 'encode' => false],
                    'last_updated' => ['label' => 'Last Updated', 'sort' => true, 'class' => 'center'],
                    'actions' => ['label' => 'Actions', 'sort' => false, 'class' => 'center', 'encode' => false]
                ]);
                
                // Try array format for better structure and cleaner HTML
                $table_data = [];
                foreach ($cache_stats['connections'] as $location_key => $location) {
                    // Build location info with proper HTML structure
                    $location_html = '<div class="cache-location-info">';
                    $location_html .= '<div style="font-weight: 600;">' . htmlspecialchars($location['cache_dir'], ENT_QUOTES, 'UTF-8') . '</div>';
                    $location_html .= '<div style="font-size: 0.875rem; color: #6b7280; margin-top: 0.125rem;">' . htmlspecialchars($location['connection_name'], ENT_QUOTES, 'UTF-8') . '</div>';
                    $location_html .= '</div>';
                    
                    // Build type badge
                    $type_html = '<span class="st-' . strtolower($location['adapter_type']) . '">' . strtoupper($location['adapter_type']) . '</span>';
                    
                    // Build status badge
                    $status_html = '';
                    if ($location['status'] === 'valid') {
                        $status_html = '<span style="background-color: #f0fdf4; color: #16a34a; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Valid</span>';
                    } else {
                        $status_html = '<span style="background-color: #fef2f2; color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Invalid</span>';
                    }
                    
                    // Build actions
                    $actions_html = '';
                    if ($location['file_count'] > 0 && $location['status'] === 'valid') {
                        $audit_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/audit_cache/' . urlencode($location['connection_name']));
                        $clear_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/clear_cache/' . urlencode($location['connection_name']));
                        
                        $actions_html = '<a href="' . $audit_url . '" title="Run audit to clean expired cache files" class="button button--small"><i class="fas fa-broom"></i> Audit</a> ';
                        $actions_html .= '<a href="' . $clear_url . '" title="Clear all cache files from this location" class="button button--small button--danger" onclick="return confirm(\'Clear all cache files from &quot;' . htmlspecialchars($location['connection_name'], ENT_QUOTES, 'UTF-8') . '&quot;?\')"><i class="fas fa-trash-alt"></i> Clear</a>';
                    } else {
                        $actions_html = '<span style="color: #9ca3af;">No actions available</span>';
                    }
                    
                    // Test both formats - try array format first, fall back to simple format if needed
                    try {
                        // Array format approach
                        $table_data[] = [
                            'columns' => [
                                [
                                    'html' => $location_html,
                                    'attrs' => ['class' => 'cache-location-cell']
                                ],
                                [
                                    'html' => $type_html,
                                    'attrs' => ['class' => 'center']
                                ],
                                number_format($location['file_count']),
                                $location['size_formatted'],
                                [
                                    'html' => $status_html,
                                    'attrs' => ['class' => 'center status-cell']
                                ],
                                $location['last_updated'],
                                [
                                    'html' => $actions_html,
                                    'attrs' => ['class' => 'center actions-cell']
                                ]
                            ],
                            'attrs' => [
                                'class' => 'cache-location-row'
                            ]
                        ];
                    } catch (Exception $e) {
                        // Fallback to simple format
                        $table_data[] = [
                            $location_html,
                            $type_html,
                            number_format($location['file_count']),
                            $location['size_formatted'],
                            $status_html,
                            $location['last_updated'],
                            $actions_html
                        ];
                    }
                }
                
                $table_field->setData($table_data);
                $table_field->setNoResultsText('No cache locations found.');
                
            } else {
                // No cache locations available
                $no_data_fieldset = $locations_group->getFieldSet('No Cache Data');
                $no_data_fieldset->setDesc('No cache locations found.');
                
                $no_data_fieldset->getField('cache_locations_empty', 'text')
                    ->setValue('Nothing currently stored in the cache system')
                    ->setDisabled(true);
            }
        } catch (Exception $e) {
            // Error getting cache locations
            $error_fieldset = $locations_group->getFieldSet('Cache Locations Error');
            $error_fieldset->setDesc('Error retrieving cache location data.');
            
            $error_fieldset->getField('cache_locations_error', 'text')
                ->setValue('Error: ' . $e->getMessage())
                ->setDisabled(true);
        }
    }

    /**
     * Build the Existing Connections group within the main form
     * 
     * @param \ExpressionEngine\Library\CP\Form $form The main form
     * @param array $named_connections Available named connections
     * @return void
     */
    private function _build_existing_connections_group($form, array $named_connections): void
    {
        // Create group for Existing Connections
        $connections_group = $form->getGroup('Existing Connections');
        
        if (!empty($named_connections)) {
            // Build the existing connections using Table field
            $table_fieldset = $connections_group->getFieldSet('Connection Management');
            $table_fieldset->setDesc('Manage your named filesystem connections. <a href="' . ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/add_connection') . '" class="button button--small button--primary" style="float:right; margin-right:25px">Add New Connection</a>');
            $table_field = $table_fieldset->getField('existing_connections_table', 'table');
            
            // Set up the actual connections table columns
            $table_field->setColumns([
                'name' => ['label' => 'Connection Name', 'sort' => true, 'encode' => false],
                'type' => ['label' => 'Type', 'sort' => true, 'class' => 'center', 'encode' => false],
                'cache_path' => ['label' => 'Cache Path', 'sort' => true],
                'status' => ['label' => 'Status', 'sort' => true, 'class' => 'center', 'encode' => false],
                'actions' => ['label' => 'Actions', 'sort' => false, 'class' => 'center', 'encode' => false]
            ]);
            
            // Convert connections data to table format
            $table_data = [];
            $current_default = $this->_get_default_connection();
            
            foreach ($named_connections as $name => $connection) {
                $is_default = ($name === $current_default);
                $is_valid = $connection['is_valid'] ?? false;
                
                // Build connection name with default indicator
                $name_content = '<div>';
                $name_content .= '<span style="font-weight: 600;">' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</span>';
                if ($is_default) {
                    $name_content .= ' <span style="background-color: #dbeafe; color: #1d4ed8; padding: 0.125rem 0.5rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600; margin-left: 0.5rem;">DEFAULT</span>';
                }
                $name_content .= '</div>';
                
                // Get cache path from connection config
                $cache_path = $this->_get_connection_cache_path($connection);
                
                // Build connection type badge
                $type_content = '<span class="st-' . strtolower($connection['type'] ?? 'unknown') . '">' . strtoupper($connection['type'] ?? 'unknown') . '</span>';
                
                // Build status badge
                $status_content = '';
                if ($is_valid) {
                    $status_content = '<span style="background-color: #f0fdf4; color: #16a34a; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Valid</span>';
                } else {
                    $status_content = '<span style="background-color: #fef2f2; color: #dc2626; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 600;">Invalid</span>';
                }
                
                // Build actions for connection management
                $edit_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/edit_connection', ['connection' => $name]);
                $clone_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/add_connection', ['clone' => $name]);
                $delete_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/delete_connection/' . urlencode($name));
                
                $actions_content = '<a href="' . $edit_url . '" title="Edit Connection" class="button button--small"><i class="fas fa-edit"></i></a> ';
                $actions_content .= '<a href="' . $clone_url . '" title="Clone Connection" class="button button--small"><i class="fas fa-copy"></i></a> ';
                
                if (!$is_default) {
                    $default_url = ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/set_default/' . urlencode($name));
                    $actions_content .= '<a href="' . $delete_url . '" title="Delete Connection" class="button button--small button--danger" onclick="return confirm(\'Delete connection &quot;' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '&quot;?\')"><i class="fas fa-trash-alt"></i> Delete</a>';
                }
                
                $table_data[] = [
                    $name_content,
                    $type_content,
                    htmlspecialchars($cache_path, ENT_QUOTES, 'UTF-8'),
                    $status_content,
                    $actions_content
                ];
            }
            
            $table_field->setData($table_data);
            $table_field->setNoResultsText('No named connections found.');
            
        } else {
            // No connections available
            $no_connections_fieldset = $connections_group->getFieldSet('No Connections');
            $no_connections_fieldset->setDesc('No connections configured. <a href="' . ee('CP/URL')->make('addons/settings/jcogs_img_pro/caching/add_connection') . '" class="button button--small button--primary">Add New Connection</a>');
            
            $no_connections_fieldset->getField('no_connections_message', 'text')
                ->setValue('No connections have been configured yet')
                ->setDisabled(true);
        }
    }

    /**
     * Calculate cache statistics for display - updated to show real cache locations
     * 
     * @return array Cache statistics data
     */
    private function _calculate_cache_statistics(): array
    {
        try {
            if ($this->cache_service === null) {
                $this->cache_service = ee('jcogs_img_pro:CacheManagementService');
            }
            $cache_service = $this->cache_service;
            $named_connections = $this->_get_named_connections();
            $total_files = 0;
            $total_size = 0;
            $location_stats = [];
            $errors = [];

            // If no connections are configured, return minimal stats with informative error
            if (empty($named_connections)) {
                return [
                    'total_files' => 0,
                    'total_size' => 0,
                    'total_size_formatted' => '0 B',
                    'connection_count' => 0,
                    'connections' => [],
                    'errors' => ['No named connections configured. Please configure at least one filesystem adapter connection.']
                ];
            }

            foreach ($named_connections as $connection_name => $config) {
                try {
                    // Get the adapter type for this connection
                    $adapter_type = $config['type'] ?? 'local';
                    
                    // Get cache directory path for this connection
                    $cache_dir = $this->_get_cache_directory_for_connection($connection_name, $config);
                    
                    // Get real cache location information using CacheManagementService
                    // FIX: Pass connection_name (not adapter_type) - adapter_name parameter expects connection name
                    $adapter_info = $cache_service->get_adapter_cache_info($connection_name, $cache_dir);
                    
                    // Create location key that combines cache_dir + connection + adapter
                    $location_key = $cache_dir . ' (' . $connection_name . ')';
                    
                    // Determine cache validity (valid if we can access cache location and no critical errors)
                    $status = $this->_determine_cache_validity($adapter_info, $connection_name, $adapter_type);
                    
                    // Format last updated date properly
                    $last_updated = $this->_format_last_updated($adapter_info);
                    
                    // Skip cache locations with no files (per user request)
                    if (($adapter_info['file_count'] ?? 0) == 0) {
                        continue;
                    }
                    
                    $location_stats[$location_key] = [
                        'cache_dir' => $cache_dir,
                        'connection_name' => $connection_name,
                        'adapter_type' => $adapter_type,
                        'file_count' => $adapter_info['file_count'] ?? 0,
                        'total_size' => $adapter_info['total_size'] ?? 0,
                        'size_formatted' => $this->utilities_service->format_file_size($adapter_info['total_size'] ?? 0),
                        'status' => $status,
                        'last_updated' => $last_updated,
                        'database_entries' => $adapter_info['database_entries'] ?? 0,
                        'orphaned_files' => $adapter_info['orphaned_files'] ?? 0,
                    ];

                    $total_files += $adapter_info['file_count'] ?? 0;
                    $total_size += $adapter_info['total_size'] ?? 0;

                } catch (Exception $e) {
                    $errors[] = "Connection '{$connection_name}': " . $e->getMessage();
                    
                    // Try to get cache directory even on error
                    $cache_dir = $this->_get_cache_directory_for_connection($connection_name, $config);
                    $location_key = $cache_dir . ' (' . $connection_name . ')';
                                       
                    $location_stats[$location_key] = [
                        'cache_dir' => $cache_dir,
                        'connection_name' => $connection_name,
                        'adapter_type' => $adapter_type,
                        'file_count' => 0,
                        'total_size' => 0,
                        'size_formatted' => '0 B',
                        'status' => 'invalid',
                        'last_updated' => 'Error',
                        'error' => $e->getMessage(),
                        'database_entries' => 0,
                        'orphaned_files' => 0,
                    ];
                }
            }

            // Count valid connections only (connections with is_valid = true)
            $valid_connection_count = 0;
            foreach ($named_connections as $connection_name => $config) {
                if (($config['is_valid'] ?? false) === true) {
                    $valid_connection_count++;
                }
            }

            return [
                'total_files' => $total_files,
                'total_size' => $total_size,
                'total_size_formatted' => $this->utilities_service->format_file_size($total_size),
                'connection_count' => $valid_connection_count,
                'connections' => $location_stats,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            // Return minimal error data structure
            return [
                'total_files' => 0,
                'total_size' => 0,
                'total_size_formatted' => '0 B',
                'connection_count' => 0,
                'connections' => [],
                'errors' => ['System error: ' . $e->getMessage()]
            ];
        }
    }

    /**
     * Determine cache validity based on stored connection validity and adapter info
     * 
     * @param array $adapter_info Adapter information from CacheManagementService
     * @param string $connection_name Connection name
     * @param string $adapter_type Adapter type
     * @return string Status ('valid' or 'invalid')
     */
    private function _determine_cache_validity(array $adapter_info, string $connection_name, string $adapter_type): string
    {
        // If there's a critical error in the adapter info, it's invalid
        if (isset($adapter_info['error']) || isset($adapter_info['critical_error'])) {
            return 'invalid';
        }
        
        // Get the named connections configuration to check stored validity
        $named_connections = $this->_get_named_connections();
        
        // Check if connection exists and use its stored validity
        if (isset($named_connections[$connection_name])) {
            $connection_config = $named_connections[$connection_name];
            $is_valid = $connection_config['is_valid'] ?? false;
            
            return $is_valid ? 'valid' : 'invalid';
        }
        
        // Fallback: if connection not found in named connections, treat as invalid
        return 'invalid';
    }

    /**
     * Format last updated date from adapter info
     * 
     * @param array $adapter_info Adapter information
     * @return string Formatted date or 'Never'
     */
    private function _format_last_updated(array $adapter_info): string
    {
        if (!isset($adapter_info['last_modified']) || $adapter_info['last_modified'] === null) {
            return 'Never';
        }
        
        $timestamp = $adapter_info['last_modified'];
        
        // Handle different timestamp formats
        if (is_numeric($timestamp)) {
            // Unix timestamp
            $formatted = date('Y-m-d H:i:s', (int)$timestamp);
            return $formatted;
        } elseif (is_string($timestamp)) {
            // Try to parse as date string
            $parsed = strtotime($timestamp);
            if ($parsed !== false) {
                $formatted = date('Y-m-d H:i:s', $parsed);
                return $formatted;
            }
        }
        
        return 'Never';
    }

    /**
     * Get cache directory for a named connection
     * 
     * Updated to use the named connection configuration instead of legacy adapter settings.
     * Uses the same logic as get_connection_cache_path for consistency.
     * 
     * @param string $connection_name Connection name
     * @param array $config Connection configuration  
     * @return string Cache directory path
     */
    private function _get_cache_directory_for_connection(string $connection_name, array $config): string
    {
        try {
            // Use the same logic as get_connection_cache_path for consistency
            return $this->_get_connection_cache_path($config);
        } catch (Exception $e) {
            // Fallback to default directory
            return '/images/jcogs_img_pro/cache/';
        }
    }
    
    /**
     * Get the cache path for a connection
     * 
     * @param array $connection Connection configuration
     * @return string Cache path
     */
    private function _get_connection_cache_path(array $connection): string
    {
        $config = $connection['config'] ?? [];
        $type = $connection['type'] ?? 'unknown';
        
        switch ($type) {
            case 'local':
                return $config['cache_directory'] ?? '/default/cache';
            case 's3':
                $bucket = $config['bucket'] ?? 'bucket';
                $path = $config['server_path'] ?? '';
                return $bucket . (!empty($path) ? '/' . trim($path, '/') : '');
            case 'r2':
                $bucket = $config['bucket'] ?? 'bucket';
                $path = $config['server_path'] ?? '';
                return $bucket . (!empty($path) ? '/' . trim($path, '/') : '');
            case 'dospaces':
                $space = $config['space'] ?? 'space';
                $path = $config['server_path'] ?? '';
                return $space . (!empty($path) ? '/' . trim($path, '/') : '');
            default:
                return 'Unknown';
        }
    }

    /**
     * Get cached named filesystem adapters configuration (with caching for efficiency)
     * 
     * @return array Full named adapters configuration with connections and default_connection
     */
    private function _get_named_adapters_config(): array
    {
        if ($this->cached_named_adapters_config === null) {
            $this->cached_named_adapters_config = $this->settings_service->getNamedFilesystemAdapters();
        }
        
        return $this->cached_named_adapters_config;
    }
    
    /**
     * Get the current default connection name (with caching for efficiency)
     * 
     * @return string Default connection name
     */
    private function _get_default_connection(): string
    {
        $config = $this->_get_named_adapters_config();
        return $config['default_connection'] ?? '';
    }

    /**
     * Get named filesystem connections from settings (with caching for efficiency)
     * 
     * @return array Named connections configuration
     */
    private function _get_named_connections(): array
    {
        if ($this->cached_named_connections === null) {
            $config = $this->_get_named_adapters_config();
            $this->cached_named_connections = $config['connections'] ?? [];
        }
        
        // Return connections as-is from settings, preserving stored validity status
        // The is_valid property should already be set in the stored configuration
        return $this->cached_named_connections;
    }

    /**
     * Load CSS and JavaScript assets for cache management interface
     * 
     * @return void
     */
    private function _load_cache_management_assets()
    {
        // Load CSS
        ee()->cp->add_to_head('<link rel="stylesheet" type="text/css" href="' . URL_THIRD_THEMES . 'jcogs_img_pro/css/cache-management.css" />');
        
        // JavaScript can be added here if needed in the future
        // ee()->cp->add_to_foot('<script defer src="' . URL_THIRD_THEMES . 'jcogs_img_pro/javascript/cache-management.js"></script>');
    }
}

