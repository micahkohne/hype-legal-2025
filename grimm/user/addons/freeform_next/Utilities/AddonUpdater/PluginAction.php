<?php

namespace Solspace\Addons\FreeformNext\Utilities\AddonUpdater;

class PluginAction
{
    /**
     * PluginAction constructor.
     *
     * @param string $methodName
     * @param string $className
     * @param bool $csrfExempt
     */
    public function __construct(private $methodName, private $className, private $csrfExempt = false)
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
    public function getClassName()
    {
        return $this->className;
    }

    /**
     * @return bool
     */
    public function isCsrfExempt()
    {
        return $this->csrfExempt;
    }
}
