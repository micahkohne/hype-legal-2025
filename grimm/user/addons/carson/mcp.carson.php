<?php

if (! defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use ExpressionEngine\Library\CP\URL;
use ExpressionEngine\Service\Addon\Mcp;
use ExpressionEngine\Service\Sidebar\Sidebar;

class Carson_mcp extends Mcp
{
    protected $addon_name = 'carson';

    public function __construct()
    {
        $this->makeSidebar();
    }

    protected function makeSidebar(): Sidebar
    {
        /** @var Sidebar $sidebar */
        $sidebar = ee('CP/Sidebar')->make();
        $sidebar->addHeader(lang('carson_mcp_home'), $this->makeUrl());
        $sidebar->addHeader(lang('carson_mcp_openai'), $this->makeUrl('openai'));
        $sidebar->addHeader(lang('carson_mcp_anthropic'), $this->makeUrl('anthropic'));
        $sidebar->addHeader(lang('carson_mcp_documentation'), 'https://docs.boldminded.com/carson/docs');
        $sidebar->addHeader(lang('carson_mcp_release_notes'), $this->makeUrl('releases'));

        return $sidebar;
    }

    protected function makeUrl(string $action = '', array $params = []): URL
    {
        $path = 'addons/settings/carson/' . $action;
        $path = rtrim($path, '/');

        return ee('CP/URL')->make($path, $params);
    }
}
