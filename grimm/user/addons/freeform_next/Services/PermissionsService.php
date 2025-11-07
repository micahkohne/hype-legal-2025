<?php
/**
 * Freeform for ExpressionEngine
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2025, Solspace, Inc.
 * @link          https://docs.solspace.com/expressionengine/freeform/v3/
 * @license       https://docs.solspace.com/license-agreement/
 */

namespace Solspace\Addons\FreeformNext\Services;

use Solspace\Addons\FreeformNext\Repositories\PermissionsRepository;

class PermissionsService
{
    public const PERMISSION__MANAGE_FORMS         = 'forms';
    public const PERMISSION__ACCESS_SUBMISSIONS   = 'submissions';
    public const PERMISSION__MANAGE_SUBMISSIONS   = 'manageSubmissions';
    public const PERMISSION__ACCESS_FIELDS        = 'fields';
    public const PERMISSION__ACCESS_EXPORT        = 'export';
    public const PERMISSION__ACCESS_NOTIFICATIONS = 'notifications';
    public const PERMISSION__ACCESS_SETTINGS      = 'settings';
    public const PERMISSION__ACCESS_INTEGRATIONS  = 'integrations';
    public const PERMISSION__ACCESS_RESOURCES     = 'resources';
    public const PERMISSION__ACCESS_LOGS          = 'logs';

    public const PERMISSION__ACCESS_SETTINGS__LICENSE             = 'settings/license';
    public const PERMISSION__ACCESS_SETTINGS__GENERAL             = 'settings/general';
    public const PERMISSION__ACCESS_SETTINGS__PERMISSIONS         = 'settings/permissions';
    public const PERMISSION__ACCESS_SETTINGS__FORMATING_TEMPLATES = 'settings/formatting_templates';
    public const PERMISSION__ACCESS_SETTINGS__EMAIL_TEMPLATES     = 'settings/email_templates';
    public const PERMISSION__ACCESS_SETTINGS__STATUSES            = 'settings/statuses';
    public const PERMISSION__ACCESS_SETTINGS__DEMO_TEMPLATES      = 'settings/demo_templates';

    /**
     * Check if user is allowed in the section
     *
     * @param string  $method  - NavigationLink's method
     * @param integer $groupId - EE Member group's id
     *
     * @return bool
     */
    public function canUserAccessSection(string $method, $groupId): bool
    {
        if ((int) $groupId === 1) {
            return true;
        }

        $settings     = PermissionsRepository::getInstance()->getOrCreate();
        $propertyName = $method . 'Permissions';

        if (!property_exists($settings, $propertyName)) {
            return true;
        }

        $permissions = $settings->{$propertyName} ?: [];

        return in_array($groupId, $permissions, false);
    }

    /**
     * @param $method
     * @param $groupId
     *
     * @return bool
     */
    public function canUserSeeSectionInNavigation($method, $groupId): bool
    {
        if ((int) $groupId === 1) {
            return true;
        }

        // Some method names have to be translated
        if (array_key_exists($method, $this->getMethodTransformation())) {
            $method = $this->getMethodTransformation()[$method];
        }

        // Only some methods can be hidden in the menu
        if (!in_array($method, $this->getRestrictedNavigationSections(), false)) {
            return true;
        }

        return $this->canUserAccessSection($method, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canManageForms($groupId): bool
    {
        return $this->canUserAccessSection(self::PERMISSION__MANAGE_FORMS, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canAccessSubmissions($groupId): bool
    {
        return $this->canUserAccessSection(self::PERMISSION__ACCESS_SUBMISSIONS, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canManageSubmissions($groupId): bool
    {
        if (!$this->canAccessSubmissions($groupId)) {
            return false;
        }

        return $this->canUserAccessSection(self::PERMISSION__MANAGE_SUBMISSIONS, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canAccessFields($groupId): bool
    {
        return $this->canUserAccessSection(self::PERMISSION__ACCESS_FIELDS, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canAccessExport($groupId): bool
    {
        return $this->canUserAccessSection(self::PERMISSION__ACCESS_EXPORT, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canAccessNotifications($groupId): bool
    {
        return $this->canUserAccessSection(self::PERMISSION__ACCESS_NOTIFICATIONS, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canAccessSettings($groupId): bool
    {
        return $this->canUserAccessSection(self::PERMISSION__ACCESS_SETTINGS, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canAccessIntegrations($groupId): bool
    {
        return $this->canUserAccessSection(self::PERMISSION__ACCESS_INTEGRATIONS, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canAccessResources($groupId): bool
    {
        return $this->canUserAccessSection(self::PERMISSION__ACCESS_RESOURCES, $groupId);
    }

    /**
     * @param int $groupId
     *
     * @return bool
     */
    public function canAccessLogs($groupId): bool
    {
        return $this->canUserAccessSection(self::PERMISSION__ACCESS_LOGS, $groupId);
    }

    /**
     * @return array
     */
    private function getMethodTransformation(): array
    {
        return [
            'export_profiles' => 'export',
        ];
    }

    /**
     * @return array
     */
    private function getRestrictedNavigationSections(): array
    {
        return [
            self::PERMISSION__ACCESS_SUBMISSIONS,
            self::PERMISSION__ACCESS_FIELDS,
            self::PERMISSION__ACCESS_EXPORT,
            self::PERMISSION__ACCESS_NOTIFICATIONS,
            self::PERMISSION__ACCESS_SETTINGS,
            self::PERMISSION__ACCESS_RESOURCES,
            self::PERMISSION__ACCESS_INTEGRATIONS,
            self::PERMISSION__ACCESS_LOGS,
        ];
    }
}
