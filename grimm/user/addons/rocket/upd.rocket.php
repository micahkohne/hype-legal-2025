<?php
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use Meatpaste\Rocket\Library\Shared;

class Rocket_upd
{
    use Shared;

    public function install()
    {
        $prefix = ee()->db->dbprefix;

        ee()->load->dbforge();

        // hooks
        ee()->db->insert_batch('extensions', [
            [
                'class' => 'Rocket_ext','priority' => 1,'version' => $this->version,'enabled' => 'y','settings' => '',
                'hook' => 'core_boot', 'method' => 'render_url',
            ],
            [
                'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                'hook' => 'channel_entries_query_result', 'method' => 'log_channel_entry_ids',
            ],
            [
                'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                'hook' => 'cp_custom_menu', 'method' => 'cp_custom_menu',
            ],
            [
                'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                'hook' => 'cp_member_login', 'method' => 'member_login',
            ],
            [
                'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                'hook' => 'cp_member_logout', 'method' => 'member_logout',
            ],
            [
                'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                'hook' => 'low_reorder_post_sort', 'method' => 'log_low_reorder_entry_ids',
            ],
            [
                'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                'hook' => 'after_channel_entry_update', 'method' => 'updated_channel_entry',
            ],
            [
                'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                'hook' => 'after_channel_entry_insert', 'method' => 'inserted_channel_entry',
            ],
            [
                'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                'hook' => 'after_channel_entry_delete', 'method' => 'deleted_channel_entry',
            ],
        ]);

        // module
        ee()->db->insert('modules', [
           'module_name' => $this->name,
           'module_version' => $this->version,
           'has_cp_backend' => 'y',
           'has_publish_fields' => 'n'
        ]);

        // paths table
        $fields = [
            'entry_id' => ['type' => 'int', 'unsigned' => true],
            'channel_id' => ['type' => 'int', 'unsigned' => true],
            'path' => ['type' => 'varchar', 'constraint' => '10000'],
        ];
        ee()->dbforge->add_field($fields);
        ee()->dbforge->create_table('rocket_paths', true);

        // speedy index for the paths table
        ee()->db->query("CREATE UNIQUE INDEX idx_rocket_paths_unique ON {$prefix}rocket_paths (entry_id, channel_id, path(700))");

        // settings table
        $fields = [
            'label' => ['type' => 'varchar', 'constraint' => '50'],
            'value' => ['type' => 'text'],
        ];
        ee()->dbforge->add_field($fields);
        ee()->dbforge->add_key('label', true);
        ee()->dbforge->create_table('rocket_settings', true);
        ee()->db->query("INSERT INTO exp_rocket_settings (`label`,`value`) VALUES ('exceptions','');");
        ee()->db->query("INSERT INTO exp_rocket_settings (`label`,`value`) VALUES ('exceptions_mode','exclude');");



        // in case we need to know someone is logged in before EE has booted
        setcookie('rocket_loggedin', 1, 0, '/');

        return true;
    }

    public function uninstall()
    {
        ee()->db->where('module_name', $this->name);
        ee()->db->delete('modules');

        ee()->db->where('class', 'Rocket_ext');
        ee()->db->delete('extensions');

        ee()->load->dbforge();
        ee()->dbforge->drop_table('rocket_settings');
        ee()->dbforge->drop_table('rocket_paths');

        $this->disable_rocket();
        $this->purge_cache_files();

        if (is_dir($this->cache_path)) {
            rmdir($this->cache_path);
        }

        return true;
    }

    public function update($current = '')
    {
        $prefix = ee()->db->dbprefix;

        if (version_compare($current, '2.2', '<')) {
            // new column
            ee()->load->dbforge();
            ee()->dbforge->add_column('rocket_paths', [
                'channel_id' => ['type' => 'int', 'unsigned' => true],
            ]);

            // new hook
            ee()->db->insert_batch('extensions', [
                [
                    'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                    'hook' => 'after_channel_entry_insert', 'method' => 'inserted_channel_entry',
                ],
                [
                    'class' => 'Rocket_ext', 'priority' => 1, 'version' => $this->version, 'enabled' => 'y', 'settings' => '',
                    'hook' => 'after_channel_entry_delete', 'method' => 'deleted_channel_entry',
                ],
            ]);

        }

        if (version_compare($current, '2.3.4', '<')) {
            ee()->db->query("TRUNCATE TABLE {$prefix}rocket_paths;");
            ee()->db->query("CREATE UNIQUE INDEX idx_rocket_paths_unique ON {$prefix}rocket_paths (entry_id, channel_id, path(700))");
        }

        return true;
    }
}
