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

use Logger;
class EELogger implements LoggerInterface
{
    /** @var \Logger[] */
    private static array $loggers = [];

    private static ?bool $loggerInitiated = null;

	public function __construct()
	{
		ee()->load->library('logger');
	}

	/**
     * @param string $category
     *
     * @return \Logger
     */
    public static function get($category = self::DEFAULT_LOGGER_CATEGORY)
    {
        if (!isset(self::$loggers[$category])) {
            if (null === self::$loggerInitiated) {
                $config = include __DIR__ . '/logger_config.php';
                Logger::configure($config);

                self::$loggerInitiated = true;
            }

            self::$loggers[$category] = Logger::getLogger($category);
        }

        return self::$loggers[$category];
    }

    /**
     * @param string $level
     * @param string $message
     * @param string $category
     */
    public function log($level, $message, $category = self::DEFAULT_LOGGER_CATEGORY): void
    {
		ee()->logger->developer("[{$category}][{$this->getLevel($level)}]: " . $message);
    }

    /**
     * @param string $message
     * @param string $category
     */
    public function debug($message, $category = self::DEFAULT_LOGGER_CATEGORY): void
    {
		ee()->logger->developer("[{$category}][{$this->getLevel('debug')}]: " . $message);
    }

    /**
     * @param string $message
     * @param string $category
     */
    public function info($message, $category = self::DEFAULT_LOGGER_CATEGORY): void
    {
		ee()->logger->developer("[{$category}][{$this->getLevel('info')}]: " . $message);
    }

    /**
     * @param string $message
     * @param string $category
     */
    public function warn($message, $category = self::DEFAULT_LOGGER_CATEGORY): void
    {
		ee()->logger->developer("[{$category}][{$this->getLevel('warn')}]: " . $message);
    }

    /**
     * @param string $message
     * @param string $category
     */
    public function error($message, $category = self::DEFAULT_LOGGER_CATEGORY): void
    {
		ee()->logger->developer("[{$category}][{$this->getLevel('error')}]: " . $message);
    }

    /**
     * @param string $message
     * @param string $category
     */
    public function fatal($message, $category = self::DEFAULT_LOGGER_CATEGORY): void
    {
		ee()->logger->developer("[{$category}][{$this->getLevel('fatal')}]: " . $message);
    }

    /**
     * @param string $level
     *
     * @return string
	 */
    private function getLevel($level): string
    {
        return match ($level) {
            self::LEVEL_DEBUG => self::LEVEL_DEBUG,
            self::LEVEL_FATAL => self::LEVEL_FATAL,
            self::LEVEL_INFO => self::LEVEL_INFO,
            self::LEVEL_WARNING => self::LEVEL_WARNING,
            default => self::LEVEL_ERROR,
        };
    }
}
