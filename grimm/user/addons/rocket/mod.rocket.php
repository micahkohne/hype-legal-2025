<?php if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Rocket
{
    public function learn() {
        if (ee()->TMPL->template_type == '404') {
            return '';
        }

        if (stripos(json_encode(ee()->TMPL->tag_data), 'search_results')) {
            return '';
        }

        $urls = @ee()->session->cache['rocket']['entry_urls'];
        if (empty($urls)) {
            return '';
        }

        foreach ($urls as $_url) {
            ee()->db->query(
                "INSERT IGNORE INTO exp_rocket_paths (`entry_id`, `channel_id`, `path`)
                VALUES (?, ?, ?);",
                [
                    $_url[0],
                    $_url[1],
                    $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'],
                ]
            );
        }

        return '';
    }
}
