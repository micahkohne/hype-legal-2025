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

namespace Solspace\Addons\FreeformNext\Library\Integrations;

class SettingBlueprint
{
    const TYPE_INTERNAL = 'internal';
    const TYPE_CONFIG   = 'config';
    const TYPE_TEXT     = 'text';
    const TYPE_PASSWORD = 'password';
    const TYPE_BOOL     = 'bool';

    /** @var string */
    private $type;

    /** @var string */
    private $handle;

    /** @var string */
    private $label;

    /** @var string */
    private $instructions;

    /** @var bool */
    private $required;

    /** @var string */
    private $attributes;

    /** @var string */
    private $value;

    /**
     * @return array
     */
    public static function getEditableTypes()
    {
        return [
            self::TYPE_TEXT,
            self::TYPE_PASSWORD,
            self::TYPE_BOOL,
        ];
    }

    /**
     * SettingObject constructor.
     *
     * @param string $type
     * @param string $handle
     * @param string $label
     * @param string $instructions
     * @param bool   $required
     * @param string $attributes
     * @param string $value
     */
    public function __construct(
        $type,
        $handle,
        $label,
        $instructions,
        $required = false,
        $attributes = "",
        $value = ""
    ) {
        $this->type         = $type;
        $this->handle       = $handle;
        $this->label        = $label;
        $this->instructions = $instructions;
        $this->required     = (bool)$required;
        $this->attributes   = $attributes;
        $this->value        = $value;
    }

    /**
     * @return string
     */
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return string
     */
    public function getInstructions()
    {
        return $this->instructions;
    }

    /**
     * @return boolean
     */
    public function isRequired()
    {
        return $this->required;
    }

    /**
     * @return string
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * @return bool
     */
    public function isEditable()
    {
        return in_array($this->getType(), self::getEditableTypes(), true);
    }
}
