<?php

use BoldMinded\Carson\Dependency\Litzinger\Basee\Setting;
use BoldMinded\Carson\Dependency\Litzinger\Basee\Trial;
use BoldMinded\Carson\Helpers\FieldHelper;
use ExpressionEngine\Core\Provider;

require_once PATH_THIRD . 'carson/vendor-build/autoload.php';

if (!defined('CARSON_VERSION')) {
    define('CARSON_VERSION', '1.4.0');
    define('CARSON_NAME_SHORT', 'Carson');
    define('CARSON_DESC', 'Your AI Assistant');
    define('CARSON_TRIAL', file_exists(PATH_THIRD . 'carson/Config/trial'));
    define('CARSON_NAME', CARSON_NAME_SHORT . (CARSON_TRIAL ? ' (Free Trial)' : ''));
    define('CARSON_EXT', 'Carson_ext');
    define('CARSON_PATH', PATH_THIRD.'carson/');
    define('CARSON_BUILD_VERSION', 'fca6222b');
}

return [
    'name'              => CARSON_NAME_SHORT,
    'description'       => CARSON_DESC,
    'version'           => '1.4.0',
    'author'            => 'BoldMinded, LLC',
    'author_url'        => 'https://boldminded.com',
    'namespace'         => 'BoldMinded\Carson',
    'settings_exist'    => true,
    'fieldtypes'        => [
        'carson_assistant' => [
            'name' => 'Carson Assistant',
            'compatibility' => 'text',
        ],
        'carson_seo' => [
            'name' => 'Carson SEO',
            'compatibility' => 'text',
        ],
        'carson_omni' => [
            'name' => 'Carson Omni',
            'compatibility' => 'text',
        ],
    ],

    'requires' => [
        'php'   => '8.2',
        'ee'    => '7.4'
    ],

    'services.singletons' => [
        'Config' => function () {
            ee()->lang->loadfile('carson');
            return include_once CARSON_PATH . 'Config/settings.php';
        },
        'Setting' => function ($provider) {
            $setting = new Setting('carson_settings');
            $setting->setGlobalSettings(
                array_merge($setting->getGlobalSettings(), $provider->make('Config'))
            );

            return $setting;
        },
        'FieldHelper' => function () {
            return new FieldHelper;
        }
    ],
    'services' => [
        'Trial' => function ($provider) {
            /** @var Provider $provider */
            $setting = $provider->make('Setting');
            /** @var Trial $trialService */
            $trialService = new Trial();
            $trialService
                ->setTrialEnabled(CARSON_TRIAL)
                ->setInstalledDate($setting->get('installed_date'))
                ->setMessageTitle('Your free trial of Carson has expired and is disabled.')
                ->setMessageBody('Please go to the <a href="https://boldminded.com">https://boldminded.com</a> to purchase the full version.');

            return $trialService;
        },
    ],
];
