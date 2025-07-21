<?php

if (session_start() && !empty($_SESSION['ROCKET_CSRF']) && $_SERVER['REQUEST_METHOD'] != 'POST' && !isset($_GET['rocket_bypass'])) {
    $path = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . 'rocket_cache';
    if (file_exists($path . DIRECTORY_SEPARATOR . 'enabled')) {
        if (!isset($_COOKIE['rocket_loggedin']) || !file_exists($path . DIRECTORY_SEPARATOR . 'bypass')) {
            $url = $_SERVER['REQUEST_URI'];
            $query_string = parse_url($url)['query'] ?? '';
            $url = parse_url($url)['path'];

            $query_exception_file = $path . DIRECTORY_SEPARATOR . 'query_exceptions';
            if (file_exists($query_exception_file)) {
                $query_exceptions = explode(',', file_get_contents($query_exception_file));
                $query_params = $_GET;
                foreach ($query_exceptions as $_exception) {
                    unset($query_params[trim($_exception)]);
                }
                $query_string = http_build_query($query_params);
            }

            $url .= !empty($query_string) ? '?' . $query_string : '';

            $filepath = $path . DIRECTORY_SEPARATOR . 'cache';
            $filepath .= DIRECTORY_SEPARATOR . $_SERVER['HTTP_HOST'];
            $filepath .= $url == '/' ? DIRECTORY_SEPARATOR.'#' : str_replace('/', DIRECTORY_SEPARATOR, $url);
            $filepath = str_replace('?','#', $filepath);
            $filepath .= '.cache';

            if (file_exists($filepath)) {
                die(str_replace(
                    '{{ROCKET_CSRF}}',
                    '<input type="hidden" name="csrf_token" value="'.$_SESSION['ROCKET_CSRF'].'" />',
                    file_get_contents($filepath)
                ));
            }
        }
    }
}
session_destroy();
