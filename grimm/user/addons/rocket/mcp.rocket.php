<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use Meatpaste\Rocket\Library\Shared;

class Rocket_mcp
{
    use Shared;

    public function index() {
        $url = explode('/', $_SERVER['QUERY_STRING']);
        if (end($url) == 'purge_cache') {
            $this->purge_cache();
            ee('CP/Alert')->makeBanner('Rocket')
              ->asSuccess()
              ->withTitle('Cache Purged')
              ->addToBody('Rocket cache has been purged')
              ->defer();

            ee()->functions->redirect(ee('CP/URL')->make('addons/settings/rocket'));
            die();
        }

        $exceptions = '';
        $exceptions_result = ee()->db->query("SELECT value FROM `exp_rocket_settings` WHERE `label` = 'exceptions';");
        if ($exceptions_result->num_rows() > 0) {
            $exceptions = $exceptions_result->result_array()[0]['value'];
        }

        $queryExceptions = '';
        if (file_exists($this->queryexception_path)) {
            $txt = fopen($this->queryexception_path, 'r');
            $queryExceptions = fread($txt, filesize($this->queryexception_path));
        }

        $vars = [
            'base_url' => ee('CP/URL')->make('addons/settings/rocket/save'),
            'cp_page_title' => 'Rocket Settings',
            'action_button' => [
                'href' => ee('CP/URL')->make('addons/settings/rocket/purge_cache'),
                'text' => 'settings_purge_cache',
            ],
            'save_btn_text' => 'btn_save_settings',
            'save_btn_text_working' => 'btn_saving',
            'alerts_name' => 'rocket-save',
            'sections' => [[]],
        ];

        $vars['sections'] = [
            [[
                'title' => 'settings_rocket_enabled',
                'fields' => [
                    'enabled' => [
                        'type' => 'yes_no',
                        'value' => $this->enabled,
                        'required' => false,
                    ],
                ],
            ]],
            [[
                'title' => 'settings_bypass_when_logged_in',
                'fields' => [
                    'bypass' => [
                        'type' => 'yes_no',
                        'value' => $this->bypass,
                        'required' => false,
                    ],
                ],
            ]],
            [[
                'title' => 'settings_dont_minify',
                'fields' => [
                    'dont_minify' => [
                        'type' => 'inline_radio',
                        'choices' => [
                            'yes' => 'Minification disabled/off',
                            'no' => 'Minification enabled/on',
                        ],
                        'value' => $this->dont_minify,
                        'required' => true,
                    ],
                ],
            ]],
            [[
                'title' => 'settings_update_on_save',
                'fields' => [
                    'update_on_save' => [
                        'type' => 'inline_radio',
                        'choices' => [
                            'yes' => 'Yes - Create new cache files when an entry is saved',
                            'no' => 'No - Delete cache files on save but do not create new ones',
                        ],
                        'value' => $this->update_on_save,
                        'required' => true,
                    ],
                ],
            ]],
            [[
                'title' => 'settings_exceptions_mode',
                'fields' => [
                    'exceptions_mode' => [
                        'type' => 'inline_radio',
                        'choices' => [
                            'include' => 'Include - Only cache URLs listed below',
                            'exclude' => 'Exclude - Do not cache URLs listed below',
                        ],
                        'value' => $this->exceptions_mode,
                        'required' => true,
                    ],
                ],
            ]],
            [[
                'title' => 'settings_exceptions',
                'fields' => [
                    'exceptions' => [
                        'type' => 'textarea',
                        'value' => $exceptions,
                        'required' => false,
                    ],
                ],
            ]],
            [[
                'title' => 'settings_query_exceptions',
                'fields' => [
                    'query_exceptions' => [
                        'type' => 'textarea',
                        'value' => $queryExceptions,
                        'required' => false,
                    ],
                ],
            ]],
        ];

        return ee('View')->make('ee:_shared/form')->render($vars);
    }

    public function purge_cache()
    {
        $this->purge_cache_files();

        ee('CP/Alert')->makeBanner('Rocket')
            ->asSuccess()
            ->withTitle('Cache Purged')
            ->addToBody('Rocket cache has been purged')
            ->defer();

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/rocket'));

        die();
    }

    public function save() {
        if (empty($_POST)) {
            show_error(lang('unauthorized_access'));
        }

        ee()->cache->delete('/rocket/');

        // in case we need to know someone is logged in before EE has booted
        setcookie('rocket_loggedin', 1, 0, '/');

        if ($_POST['enabled'] == 'y') {
            $this->enable_rocket();
        } else {
            $this->disable_rocket();
        }

        if ($_POST['bypass'] == 'y') {
            touch($this->bypass_path);
        } else {
            if (file_exists($this->bypass_path)) {
                unlink($this->bypass_path);
            }
        }

        if (!empty($_POST['query_exceptions'])) {
            $txt = fopen($this->queryexception_path, 'w');
            fwrite($txt, $_POST['query_exceptions']);
        }
        ee()->db->query(
            "REPLACE INTO exp_rocket_settings (value,label) VALUES (?,?);",
            [$_POST['exceptions'], 'exceptions']
        );

        ee()->db->query(
            "REPLACE INTO exp_rocket_settings (value,label) VALUES (?,?);",
            [$_POST['exceptions_mode'], 'exceptions_mode']
        );

        ee()->db->query(
            "REPLACE INTO exp_rocket_settings (value, label) VALUES (?,?);",
            [$_POST['dont_minify'], 'dont_minify']
        );

        ee()->db->query(
            "REPLACE INTO exp_rocket_settings (value, label) VALUES (?,?);",
            [$_POST['update_on_save'], 'update_on_save']
        );

        $this->purge_cache();

        ee()->functions->redirect(ee('CP/URL')->make('addons/settings/rocket'));
    }
}
