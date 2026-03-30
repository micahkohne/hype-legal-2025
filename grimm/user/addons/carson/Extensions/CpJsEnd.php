<?php

namespace BoldMinded\Carson\Extensions;

use BoldMinded\Carson\Dependency\Litzinger\Basee\App;
use BoldMinded\Carson\Dependency\Litzinger\Basee\License;
use BoldMinded\Carson\Dependency\Litzinger\Basee\Setting;
use BoldMinded\Carson\Dependency\Litzinger\Basee\Version;
use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;

class CpJsEnd extends AbstractRoute
{
    public function process()
    {
        $scripts = [];

        // If another extension shares the same hook
        if (ee()->extensions->last_call !== false) {
            $scripts[] = ee()->extensions->last_call;
        }

        // Don't load unnecessary files when it's a frontedit modal.
        if (App::isFrontEditRequest()) {
            return implode('', $scripts);
        }

        $modules[] = $this->versionCheck();

        return implode('', $scripts) . implode('', $modules);
    }

    private function versionCheck(bool $checkForUpdates = true): string
    {
        if ($checkForUpdates) {
            $version = new Version();
            $latest = $version->setAddon('carson')->fetchLatest();

            if (isset($latest->version) && version_compare($latest->version, CARSON_VERSION, '>')) {
                /** @var Setting $setting */
                $setting = ee('carson:Setting');

                $url = sprintf('https://boldminded.com/account/licenses?l=%s', $setting->get('license'));
                $script = License::getUpdateAvailableNotice('carson', $url);
                return preg_replace("/\s+/", " ", $script);
            }
        }

        return '';
    }
}
