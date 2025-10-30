<?php

namespace Solspace\Addons\FreeformNext\Utilities\AddonUpdater;

class PluginExtension
{
    /**
     * PluginExtension constructor.
     *
     * @param string $className
     * @param string $methodName
     * @param string $hookName
     * @param array  $settings
     * @param int    $priority
     * @param bool   $enabled
     */
    public function __construct(private $methodName, private $hookName, private readonly array $settings = [], private $priority = 5, private $enabled = true)
    {
    }

    /**
     * @return string
     */
    public function getMethodName()
    {
        return $this->methodName;
    }

    /**
     * @return string
     */
    public function getHookName()
    {
        return $this->hookName;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings ?: [];
    }

    /**
     * @return int
     */
    public function getPriority()
    {
        return $this->priority;
    }

    /**
     * @return bool
     */
    public function isEnabled()
    {
        return $this->enabled;
    }
}
