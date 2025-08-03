<?php

namespace Meatpaste\Rocket\Library;

trait Shared
{
    public $cache_path;
    public $enabled;
    public $enabled_path;
    public $bypass;
    public $bypass_path;
    public $queryexception_path;
    public $cached_settings;
    public $exceptions;
    public $exceptions_mode;
    public $update_on_save;
    public $dont_minify;
    public $author;
    public $author_url;
    public $version = '';
    public $settings = [];
    public $name = '';
    public $namespace;
    public $description = '';
    public $settings_exist = false;
    public $docs_url = '';

    public function __construct()
    {
        // load settings from the addon file
        $this->loadSetup();

        // make sure we have a cache folder
        if(!array_key_exists('HOME', $_SERVER)) {
            $this->cache_path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'rocket_cache';
        } else {
            $this->cache_path = $_SERVER['HOME'] . 'public_html/web' . DIRECTORY_SEPARATOR . 'rocket_cache';
        }

        if (!file_exists($this->cache_path)) {
            mkdir($this->cache_path, 0755, true);
        }

        // see if rocket is enabled
        $this->enabled = false;
        $this->enabled_path = $this->cache_path . DIRECTORY_SEPARATOR . 'enabled';
        if (file_exists($this->enabled_path)) {
            $this->enabled = true;
        }

        // bypass cache if logged in
        $this->bypass = false;
        $this->bypass_path = $this->cache_path . DIRECTORY_SEPARATOR . 'bypass';
        if (file_exists($this->bypass_path)) {
            $this->bypass = true;
        }

        // query exception path
        $this->queryexception_path = $this->cache_path . DIRECTORY_SEPARATOR . 'query_exceptions';

        // settings from the database
        $this->cached_settings = ee()->cache->get('/rocket/settings');

        if (!$this->cached_settings) {
            $this->cached_settings = [];
            if (ee()->db->table_exists('exp_rocket_settings')) {
                $settings = ee()->db->query("SELECT `label`,`value` FROM `exp_rocket_settings`;");

                foreach($settings->result_array() as $_setting) {
                    switch ($_setting['label']) {
                        case 'exceptions':
                            $this->cached_settings['exceptions'] = explode(PHP_EOL, $_setting['value']);
                            break;
                        case 'exceptions_mode':
                            $this->cached_settings['exceptions_mode'] = $_setting['value'];
                            break;
                        case 'update_on_save':
                            if ($_setting['value'] != 'yes') {
                                $this->cached_settings['update_on_save'] = 'no';
                            }
                            break;
                        case 'dont_minify':
                            $this->cached_settings['dont_minify'] = $_setting['value'];
                            break;
                    }
                }
            }
            ee()->cache->save('/rocket/settings', $this->cached_settings);
        }

        $this->exceptions = empty($this->cached_settings['exceptions']) ? [] : $this->cached_settings['exceptions'];
        $this->exceptions_mode = empty($this->cached_settings['exceptions_mode']) ? 'exclude' : $this->cached_settings['exceptions_mode'];
        $this->update_on_save = empty($this->cached_settings['update_on_save']) ? 'yes' : $this->cached_settings['update_on_save'];
        $this->dont_minify = empty($this->cached_settings['dont_minify']) ? 'no' : $this->cached_settings['dont_minify'];
    }

    protected function cacheFilePath($url)
    {
        $out = $this->cache_path . DIRECTORY_SEPARATOR . 'cache';
        $out .= DIRECTORY_SEPARATOR;
        if (strpos($url, $_SERVER['HTTP_HOST']) === false) {
            $out .= $_SERVER['HTTP_HOST'];
        }
        $out .= $url == '/' ? DIRECTORY_SEPARATOR.'#' : str_replace('/', DIRECTORY_SEPARATOR, $url);
        $out = str_replace('?', '#', $out);
        $out .= '.cache';

        return $out;
    }

    private function delTree($dir) {
        if (strpos($dir, 'rocket_cache') === false) {
            return true;
        }

        if (!is_dir($dir)) {
            // Directory doesn't exist, nothing to delete
            return false;
        }

        $files = array_diff(scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            $path = "$dir/$file";
            if (is_dir($path)) {
                $this->delTree($path);
            } else {
                unlink($path);
            }
        }

        return rmdir($dir);
    }

    public function disable_rocket()
    {
        if (file_exists($this->enabled_path)) {
            unlink($this->enabled_path);
        }
        $this->enabled = false;
    }

    public function enable_rocket()
    {
        touch($this->enabled_path);
        $this->enabled = true;
    }

    private function loadSetup() {
        $settings = include PATH_THIRD . 'rocket/addon.setup.php';

        foreach ($settings as $_key => $_setting) {
            $this->{$_key} = $_setting;
        }
    }

    public function purge_cache_files()
    {
        $files = glob($this->cache_path . DIRECTORY_SEPARATOR . '*.html');
        foreach ($files as $file) {
            unlink($file);
        }

        $this->delTree($this->cache_path . DIRECTORY_SEPARATOR . 'cache');
    }
}
