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

namespace Solspace\Addons\FreeformNext\Library\Logging;

interface LoggerInterface
{
    public const DEFAULT_LOGGER_CATEGORY = 'freeform_next';

    public const LEVEL_DEBUG   = 'debug';
    public const LEVEL_INFO    = 'info';
    public const LEVEL_WARNING = 'warning';
    public const LEVEL_ERROR   = 'error';
    public const LEVEL_FATAL   = 'fatal';

    /**
     * @param string $level
     * @param string $message
     * @param string $category
     */
    public function log($level, $message, $category = self::DEFAULT_LOGGER_CATEGORY);

    /**
     * @param string $message
     * @param string $category
     */
    public function debug($message, $category = self::DEFAULT_LOGGER_CATEGORY);

    /**
     * @param string $message
     * @param string $category
     */
    public function info($message, $category = self::DEFAULT_LOGGER_CATEGORY);

    /**
     * @param string $message
     * @param string $category
     */
    public function warn($message, $category = self::DEFAULT_LOGGER_CATEGORY);

    /**
     * @param string $message
     * @param string $category
     */
    public function error($message, $category = self::DEFAULT_LOGGER_CATEGORY);

    /**
     * @param string $message
     * @param string $category
     */
    public function fatal($message, $category = self::DEFAULT_LOGGER_CATEGORY);
}
