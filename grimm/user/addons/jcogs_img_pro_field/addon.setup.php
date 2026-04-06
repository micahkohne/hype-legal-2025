<?php

/**
 * JCOGS Image Pro Field - Add-on Setup
 *====================================
 * ExpressionEngine add-on setup/config file.
 *
 * Registers services and defines version constants from addon.json.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.2.RC1
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.6
 */

// Load Composer autoloader if present
$autoload = __DIR__ . '/vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

$addonJson = json_decode(file_get_contents(__DIR__ . '/addon.json'));

defined('JCOGS_IMG_PRO_FIELD_VERSION') || define('JCOGS_IMG_PRO_FIELD_VERSION', $addonJson->version);
defined('JCOGS_IMG_PRO_FIELD_CLASS') || define('JCOGS_IMG_PRO_FIELD_CLASS', $addonJson->class);
defined('JCOGS_IMG_PRO_FIELD_NAME') || define('JCOGS_IMG_PRO_FIELD_NAME', $addonJson->name);
defined('JCOGS_IMG_PRO_FIELD_MIN_IMG_PRO_VERSION') || define('JCOGS_IMG_PRO_FIELD_MIN_IMG_PRO_VERSION', '2.0.3');

return [
    'author' => $addonJson->author,
    'author_url' => $addonJson->author_url,
    'name' => $addonJson->name,
    'description' => $addonJson->description,
    'version' => $addonJson->version,
    'namespace' => $addonJson->namespace,
    'settings_exist' => $addonJson->settings_exist,
    'docs_url' => $addonJson->docs_url,

    'fieldtypes' => [
        // Key must match ft.<shortname>.php (jcogs_img_pro_field)
        // so EE can correctly map compatibility for existing field edits.
        'jcogs_img_pro_field' => [
            'name' => 'JCOGS Image Pro Field',
            // This field stores and resolves EE file references (file_id / {filedir_*}),
            // so it should be offered as a migration target for File-compatible fields.
            'compatibility' => 'file',
        ],
    ],

    'services' => [
        'Utilities' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\Utilities();
        },
        'HttpClientService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\HttpClientService();
        },
        'UpdateMarkerScriptService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\UpdateMarkerScriptService();
        },
        'UpdateCheckService' => function ($ee) {
            $httpClient = $ee->make('jcogs_img_pro_field:HttpClientService');
            $utilities = $ee->make('jcogs_img_pro_field:Utilities');
            $updateMarkerScriptService = $ee->make('jcogs_img_pro_field:UpdateMarkerScriptService');
            return new \JCOGSDesign\JcogsImgProField\Service\UpdateCheckService($httpClient, $utilities, $updateMarkerScriptService);
        },
        'ActionRepository' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Repository\ActionRepository();
        },
        'AuthService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\AuthService();
        },
        'FileRepository' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Repository\FileRepository();
        },
        'FieldSettingsService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\FieldSettingsService();
        },
        'ImgProPresetRepository' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Repository\ImgProPresetRepository();
        },
        'PolicyEnforcer' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\PolicyEnforcer();
        },
        'ImageProRenderer' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\ImageProRenderer();
        },
        'ActionResponder' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\ActionResponder();
        },
        'UploadDestinationRepository' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Repository\UploadDestinationRepository();
        },
        'UsageRepository' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Repository\UsageRepository();
        },
        'UsageVersionRepository' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Repository\UsageVersionRepository();
        },
        'UsagePersistenceService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\UsagePersistenceService();
        },
        'UsagePayloadMaintenanceService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\UsagePayloadMaintenanceService();
        },
        'UsageVersioningService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\UsageVersioningService();
        },
        'ArtDirectionService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\ArtDirectionService();
        },
        'AspectRatioService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\AspectRatioService();
        },
        'ResponsiveDefaultsService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\ResponsiveDefaultsService();
        },
        'PresetOptionsService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\PresetOptionsService();
        },
        'SettingsUiService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\SettingsUiService();
        },
        'AssetLoaderService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\AssetLoaderService();
        },
        'TagRenderService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\TagRenderService();
        },
        'PublishGuidanceService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\PublishGuidanceService();
        },
        'PublishUiShellService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\PublishUiShellService();
        },
        'PublishUiChipsService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\PublishUiChipsService();
        },
        'PublishUiSectionsService' => function ($ee) {
            return new \JCOGSDesign\JcogsImgProField\Service\PublishUiSectionsService();
        },
    ],

    'requires' => [
        'php' => $addonJson->require->php,
        'ee' => $addonJson->require->expressionengine,
    ],
];
