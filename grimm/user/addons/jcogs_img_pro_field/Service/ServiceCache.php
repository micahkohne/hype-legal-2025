<?php

/**
 * JCOGS Image Pro Field - ServiceCache
 *====================================
 * Per-request service caching.
 *
 * ExpressionEngine templates may render multiple instances of the same field
 * during a single request. This cache avoids repeated service container lookups
 * and repeated instantiation of stateless helper services.
 *
 * Implementation detail:
 * - Uses ee()->session->cache (per-request) when available.
 * - Falls back to an internal static array in unusual contexts.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.0
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.8
 */

namespace JCOGSDesign\JcogsImgProField\Service;

use JCOGSDesign\JcogsImgProField\Repository\ActionRepository;
use JCOGSDesign\JcogsImgProField\Repository\FileRepository;
use JCOGSDesign\JcogsImgProField\Repository\ImgProPresetRepository;
use JCOGSDesign\JcogsImgProField\Repository\UploadDestinationRepository;
use JCOGSDesign\JcogsImgProField\Repository\UsageRepository;
use JCOGSDesign\JcogsImgProField\Repository\UsageVersionRepository;
use JCOGSDesign\JcogsImgProField\Service\PublishUiSectionsService;
use JCOGSDesign\JcogsImgProField\Service\UsageVersioningService;

/**
 * Simple per-request cache for commonly-used services.
 */
class ServiceCache
{
    /**
     * Fallback cache for contexts where ee()->session->cache is unavailable.
     *
     * @var array<string, object>
     */
    private static array $fallback = [];

    /**
     * Clear cached services.
     */
    public static function clear(): void
    {
        $bucket = &self::bucket();
        $bucket = [];
    }

    /**
     * Get ActionRepository.
     */
    public static function action_repo(): ActionRepository
    {
        /** @var ActionRepository */
        return self::get('ActionRepository', static function () {
            return ee('jcogs_img_pro_field:ActionRepository');
        });
    }

    /**
     * Get FileRepository.
     */
    public static function file_repo(): FileRepository
    {
        /** @var FileRepository */
        return self::get('FileRepository', static function () {
            return ee('jcogs_img_pro_field:FileRepository');
        });
    }

    /**
     * Get ImgProPresetRepository.
     */
    public static function img_pro_preset_repo(): ImgProPresetRepository
    {
        /** @var ImgProPresetRepository */
        return self::get('ImgProPresetRepository', static function () {
            return ee('jcogs_img_pro_field:ImgProPresetRepository');
        });
    }

    /**
     * Get UploadDestinationRepository.
     */
    public static function upload_destination_repo(): UploadDestinationRepository
    {
        /** @var UploadDestinationRepository */
        return self::get('UploadDestinationRepository', static function () {
            return ee('jcogs_img_pro_field:UploadDestinationRepository');
        });
    }

    /**
     * Get UsageRepository.
     */
    public static function usage_repo(): UsageRepository
    {
        /** @var UsageRepository */
        return self::get('UsageRepository', static function () {
            return ee('jcogs_img_pro_field:UsageRepository');
        });
    }

    /**
     * Get UsageVersionRepository.
     */
    public static function usage_version_repo(): UsageVersionRepository
    {
        /** @var UsageVersionRepository */
        return self::get('UsageVersionRepository', static function () {
            return ee('jcogs_img_pro_field:UsageVersionRepository');
        });
    }

    /**
     * Get FieldSettingsService.
     */
    public static function field_settings(): FieldSettingsService
    {
        /** @var FieldSettingsService */
        return self::get('FieldSettingsService', static function () {
            return ee('jcogs_img_pro_field:FieldSettingsService');
        });
    }

    /**
     * Get PolicyEnforcer.
     */
    public static function policy(): PolicyEnforcer
    {
        /** @var PolicyEnforcer */
        return self::get('PolicyEnforcer', static function () {
            return ee('jcogs_img_pro_field:PolicyEnforcer');
        });
    }

    /**
     * Get ImageProRenderer.
     */
    public static function renderer(): ImageProRenderer
    {
        /** @var ImageProRenderer */
        return self::get('ImageProRenderer', static function () {
            return ee('jcogs_img_pro_field:ImageProRenderer');
        });
    }

    /**
     * Get UsagePersistenceService.
     */
    public static function usage_persistence(): UsagePersistenceService
    {
        /** @var UsagePersistenceService */
        return self::get('UsagePersistenceService', static function () {
            return ee('jcogs_img_pro_field:UsagePersistenceService');
        });
    }

    /**
     * Get UsagePayloadMaintenanceService.
     */
    public static function usage_payload_maintenance(): UsagePayloadMaintenanceService
    {
        /** @var UsagePayloadMaintenanceService */
        return self::get('UsagePayloadMaintenanceService', static function () {
            return ee('jcogs_img_pro_field:UsagePayloadMaintenanceService');
        });
    }

    /**
     * Get UsageVersioningService.
     */
    public static function usage_versioning(): UsageVersioningService
    {
        /** @var UsageVersioningService */
        return self::get('UsageVersioningService', static function () {
            return ee('jcogs_img_pro_field:UsageVersioningService');
        });
    }

    /**
     * Get ArtDirectionService.
     */
    public static function art_direction(): ArtDirectionService
    {
        /** @var ArtDirectionService */
        return self::get('ArtDirectionService', static function () {
            return ee('jcogs_img_pro_field:ArtDirectionService');
        });
    }

    /**
     * Get AspectRatioService.
     */
    public static function aspect_ratio(): AspectRatioService
    {
        /** @var AspectRatioService */
        return self::get('AspectRatioService', static function () {
            return ee('jcogs_img_pro_field:AspectRatioService');
        });
    }

    /**
     * Get ResponsiveDefaultsService.
     */
    public static function responsive_defaults(): ResponsiveDefaultsService
    {
        /** @var ResponsiveDefaultsService */
        return self::get('ResponsiveDefaultsService', static function () {
            return ee('jcogs_img_pro_field:ResponsiveDefaultsService');
        });
    }

    /**
     * Get PresetOptionsService.
     */
    public static function preset_options(): PresetOptionsService
    {
        /** @var PresetOptionsService */
        return self::get('PresetOptionsService', static function () {
            return ee('jcogs_img_pro_field:PresetOptionsService');
        });
    }

    /**
     * Get SettingsUiService.
     */
    public static function settings_ui(): SettingsUiService
    {
        /** @var SettingsUiService */
        return self::get('SettingsUiService', static function () {
            return ee('jcogs_img_pro_field:SettingsUiService');
        });
    }

    /**
     * Get AssetLoaderService.
     */
    public static function assets(): AssetLoaderService
    {
        /** @var AssetLoaderService */
        return self::get('AssetLoaderService', static function () {
            return ee('jcogs_img_pro_field:AssetLoaderService');
        });
    }

    /**
     * Get TagRenderService.
     */
    public static function tag_render(): TagRenderService
    {
        /** @var TagRenderService */
        return self::get('TagRenderService', static function () {
            return ee('jcogs_img_pro_field:TagRenderService');
        });
    }

    /**
     * Get PublishGuidanceService.
     */
    public static function publish_guidance(): PublishGuidanceService
    {
        /** @var PublishGuidanceService */
        return self::get('PublishGuidanceService', static function () {
            return ee('jcogs_img_pro_field:PublishGuidanceService');
        });
    }

    /**
     * Get PublishUiShellService.
     */
    public static function publish_ui_shell(): PublishUiShellService
    {
        /** @var PublishUiShellService */
        return self::get('PublishUiShellService', static function () {
            return ee('jcogs_img_pro_field:PublishUiShellService');
        });
    }

    /**
     * Get PublishUiChipsService.
     */
    public static function publish_ui_chips(): PublishUiChipsService
    {
        /** @var PublishUiChipsService */
        return self::get('PublishUiChipsService', static function () {
            return ee('jcogs_img_pro_field:PublishUiChipsService');
        });
    }

    /**
     * Get PublishUiSectionsService.
     */
    public static function publish_ui_sections(): PublishUiSectionsService
    {
        /** @var PublishUiSectionsService */
        return self::get('PublishUiSectionsService', static function () {
            return ee('jcogs_img_pro_field:PublishUiSectionsService');
        });
    }

    /**
     * Retrieve cached instance by key.
     *
     * @param string   $key
     * @param callable $factory
     * @return object
     */
    private static function get(string $key, callable $factory): object
    {
        $bucket = &self::bucket();

        if (! isset($bucket[$key])) {
            $bucket[$key] = $factory();
        }

        return $bucket[$key];
    }

    /**
     * Get the per-request cache bucket (preferred) or fallback bucket.
     *
     * @return array<string, object>
     */
    private static function &bucket(): array
    {
        try {
            if (function_exists('ee') && isset(ee()->session) && isset(ee()->session->cache) && is_array(ee()->session->cache)) {
                if (! isset(ee()->session->cache['jcogs_img_pro_field'])) {
                    ee()->session->cache['jcogs_img_pro_field'] = [];
                }
                if (! isset(ee()->session->cache['jcogs_img_pro_field']['services']) || ! is_array(ee()->session->cache['jcogs_img_pro_field']['services'])) {
                    ee()->session->cache['jcogs_img_pro_field']['services'] = [];
                }

                return ee()->session->cache['jcogs_img_pro_field']['services'];
            }
        } catch (\Throwable $e) {
            // Fall back.
        }

        return self::$fallback;
    }
}
