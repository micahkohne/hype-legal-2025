<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use Meatpaste\Rocket\Library\Shared;

class Rocket_ext
{
    use Shared;

    public function cache_url($url)
    {
        if ($this->exceptions_mode == 'exclude') {
            foreach ($this->exceptions as $_exception) {
                if (!empty($_exception)) {
                    if (substr($_exception, -1) == '*' && strpos($url, substr($_exception, 0, -1)) === 0) {
                        // starts with
                        return false;
                    } elseif ($url == $_exception) {
                        // exact match
                        return false;
                    }
                }
            }
        }

        if ($this->exceptions_mode == 'include') {
            if (empty($this->exceptions)) {
                return false;
            }
            $match = false;
            foreach($this->exceptions as $_exception) {
                if (substr($_exception, -1) == '*' && strpos($url, substr($_exception, 0, -1)) === 0) {
                    // starts with
                    $match = true;
                } elseif ($url == $_exception) {
                    // exact match
                    $match = true;
                }
            }
            if (!$match) {
                return false;
            }
        }
        $scheme = !empty($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'http';

        if (strpos($url,$_SERVER['HTTP_HOST']) === 0) {
            $html = $this->get($scheme . '://' . ltrim($url,'/'));
        } else {
            $html = $this->get(rtrim($scheme . '://' . $_SERVER['HTTP_HOST'],'/') . '/' . ltrim($url,'/'));
        }

        if (!empty($html)) {
            $filepath = $this->cacheFilePath($url);

            $html .= "<!-- Rocket Cache $url -->";

            @mkdir(substr($filepath, 0, strrpos($filepath, DIRECTORY_SEPARATOR)), 0775, true);
            file_put_contents($filepath, $html);
        }
    }

    public function cp_custom_menu($menu)
    {
        $sub = $menu->addSubmenu('Rocket');
        $sub->addItem('Settings', ee('CP/URL')->make('addons/settings/rocket'));
        $sub->addItem('Purge Cache', ee('CP/URL')->make('addons/settings/rocket/purge_cache'));
    }

    public function deleted_channel_entry($entry, $values) {
        // delete cache file and path record when an entry is removed
        $paths = ee()->db->query(
            "SELECT DISTINCT(`path`) AS `path`
            FROM `exp_rocket_paths`
            WHERE `entry_id` = ?",
            [$entry->entry_id]
        );

        foreach ($paths->result_array() as $_path) {
            ee()->db->query("DELETE FROM exp_rocket_paths WHERE `path` = ?", [$_path['path']]);

            $file = $this->cacheFilePath($_path['path']);

            if (file_exists($file)) {
                unlink($file);
            }
        }

        return true;
    }

    public function get($url)
    {
        $url .= strpos($url, '?') ? '&rocket_bypass' : '?rocket_bypass';

        // this bit makes file_get_contents ignore SSL errors
        $arrContextOptions=array(
            "ssl"=>array(
                "verify_peer"=>false,
                "verify_peer_name"=>false,
            ),
        );

        $out = @file_get_contents($url, false, stream_context_create($arrContextOptions));
        if (!empty($out)) {
            $pattern = "(<input type=\"hidden\" name=\"csrf_token\" value=\"\w*\" ?\/>)";
            $out = preg_replace($pattern, '{{ROCKET_CSRF}}', $out);

            $out = $this->minify_html($out);
            return $out;
        }
        return;
    }

    public function inserted_channel_entry($entry, $values) {
        // delete cached files that have more than one entry from this channel
        $paths = ee()->db->query(
            "SELECT `path`
            FROM `exp_rocket_paths`
            WHERE `channel_id` = ?
            GROUP BY `path`
            HAVING COUNT(`path`) > 1",
            [$entry->channel_id]
        );
        foreach ($paths->result_array() as $_path) {
            $file = $this->cacheFilePath($_path['path']);

            if (file_exists($file)) {
                unlink($file);
            }
        }
    }

    private function loadSettings()
    {
        $settings = include PATH_THIRD . 'rocket/addon.setup.php';

        foreach ($settings as $_key => $_setting) {
            $this->{$_key} = $_setting;
        }
    }

    public function log_channel_entry_ids($channel, $query_result)
    {
        if (REQ == 'CP') {
            return $query_result;
        }
        if (ee()->TMPL->template_type == '404') {
            return $query_result;
        }
        if (stripos(json_encode(ee()->TMPL->tag_data), 'search_results')) {
            return $query_result;
        }
        $url = $_SERVER['REQUEST_URI'];

        foreach ($query_result as $_result) {
            $entry_id = $_result['entry_id'];
            ee()->session->cache['rocket']['entry_urls'][] = [$_result['entry_id'], $_result['channel_id']];
        }

        return $query_result;
    }

    public function member_login()
    {
        setcookie('loggedin',1,0,'/');
    }

    public function member_logout()
    {
        setcookie('loggedin',1,time()-3600, '/');
    }

    private function minify_html($html)
    {
        if ($this->dont_minify == 'yes') {
            return $html;
        }

        $search = array(
            '/\>[^\S ]+/s',     // strip whitespaces after tags, except space
            '/[^\S ]+\</s',     // strip whitespaces before tags, except space
            '/(\s)+/s',         // shorten multiple whitespace sequences
            '/<!--(.|\s)*?-->/' // Remove HTML comments
        );

        $replace = array(
            '>',
            '<',
            '\\1',
            ''
        );

        $out = preg_replace($search, $replace, $html);

        return $out;
    }

    public function render_url()
    {
        if ($_SERVER['REQUEST_METHOD'] == 'POST' || REQ == 'CP' || REQ == 'ACTION' || isset($_GET['rocket_bypass'])) {
            $_SERVER['REQUEST_URI'] = str_replace('?rocket_bypass', '', str_replace('&rocket_bypass', '', $_SERVER['REQUEST_URI']));
            return;
        }
        $url = $_SERVER['REQUEST_URI'];

        if ($this->bypass && isset($_COOKIE['loggedin'])) {
            return;
        }

        $_SESSION['ROCKET_CSRF'] = CSRF_TOKEN;

        if ($this->enabled) {
            $query_string = parse_url($url)['query'] ?? '';
            $url = parse_url($url)['path'];

            if (file_exists($this->queryexception_path)) {
                $query_exceptions = explode(',', file_get_contents($this->queryexception_path));
                $query_params = $_GET;
                foreach ($query_exceptions as $_exception) {
                    unset($query_params[trim($_exception)]);
                }
                $query_string = http_build_query($query_params);
            }

            $url .= !empty($query_string) ? '?' . $query_string : '';

            $filepath = $this->cacheFilePath($url);

            if (file_exists($filepath)) {
                $out = file_get_contents($filepath);
                $out = str_replace(
                    '{{ROCKET_CSRF}}',
                    '<input type="hidden" name="csrf_token" value="'.CSRF_TOKEN.'" />',
                    $out
                );
                die($out);
            } else {
                $this->cache_url($url);
            }
        }
    }

    public function updated_channel_entry($entry, $values, $modified) {
        $paths = ee()->db->query(
            "SELECT DISTINCT(`path`) AS `path`
            FROM `exp_rocket_paths`
            WHERE `entry_id` = ?",
            [$entry->entry_id]
        );

        foreach ($paths->result_array() as $_path) {
            ee()->db->query("DELETE FROM exp_rocket_paths WHERE `path` = ?", [$_path['path']]);

            $file = $this->cacheFilePath($_path['path']);

            if (file_exists($file)) {
                unlink($file);
            }

            if ($this->update_on_save == 'yes') {
                $this->cache_url($_path['path']);
            }
        }

        return true;
    }
}
