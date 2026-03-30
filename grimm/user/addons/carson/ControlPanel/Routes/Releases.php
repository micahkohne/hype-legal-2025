<?php

namespace BoldMinded\Carson\ControlPanel\Routes;

use BoldMinded\Carson\Dependency\Litzinger\Basee\Version;
use ExpressionEngine\Service\Addon\Controllers\Mcp\AbstractRoute;

class Releases extends AbstractRoute
{
    /**
     * @var string
     */
    protected $route_path = 'releases';

    /**
     * @var string
     */
    protected $cp_page_title = 'Releases';

    /**
     * @param false $id
     * @return AbstractRoute
     */
    public function process($id = false)
    {
        $this->addBreadcrumb('releases', 'Releases');

        $version = new Version();
        $allVersions = $version->setAddon('carson')->fetchAll();

        $releases = [];

        foreach ($allVersions as $version) {
            $releases[] = [
                'date' => $version->dateFormatted,
                'version' => $version->version,
                'notes' => html_entity_decode($version->notes),
                'isNew' => version_compare($version->version, CARSON_VERSION, '>'),
                'currentVersion' => CARSON_VERSION,
            ];
        }

        $vars['releases'] = $releases;

        $vars['message'] = ee('CP/Alert')->makeInline('carson-releases')
            ->asAttention()
            ->cannotClose()
            ->withTitle('Stay up-to-date!')
            ->addToBody('The latest version of Carson can be downloaded from your <a href="https://boldminded.com/account/licenses">BoldMinded account</a>')
            ->render();

        $this->setBody('releases', $vars);

        return $this;
    }
}
