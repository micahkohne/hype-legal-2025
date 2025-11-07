<?php

namespace Solspace\Addons\FreeformNext\Utilities\Extension;

class Hook
{
    /**
     * Hook constructor.
     *
     * @param string $class
     * @param string $method
     * @param string $hook
     * @param string $version
     * @param int    $priority
     * @param bool   $enabled
     */
    public function __construct(private $class, private $method, private ?string $hook = null, private $version = '1.0.0', private array $settings = [], private $priority = 10, private $enabled = true)
    {
    }

    /**
     * @return string
     */
    public function getClass()
    {
        return $this->class;
    }

    /**
     * @return string
     */
    public function getMethod()
    {
        return $this->method;
    }

    /**
     * @return string
     */
    public function getHook()
    {
        return $this->hook;
    }

    /**
     * @return array
     */
    public function getSettings(): array
    {
        return $this->settings;
    }

    /**
     * @return int
     */
    public function getPriority(): int
    {
        return (int) $this->priority;
    }

    /**
     * @return string
     */
    public function getVersion()
    {
        return $this->version;
    }

    /**
     * @return bool
     */
    public function isEnabled(): bool
    {
        return (bool) $this->enabled;
    }
}
