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
    public const TYPE_INTERNAL = 'internal';
    public const TYPE_CONFIG   = 'config';
    public const TYPE_TEXT     = 'text';
    public const TYPE_PASSWORD = 'password';
    public const TYPE_BOOL     = 'bool';

    private bool $required;

    /**
     * @return array
     */
    public static function getEditableTypes(): array
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
        private $type,
        private $handle,
        private $label,
        private $instructions,
        $required = false,
        private $attributes = "",
        private $value = ""
    ) {
        $this->required     = (bool)$required;
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
    public function isRequired(): bool
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
    public function isEditable(): bool
    {
        return in_array($this->getType(), self::getEditableTypes(), true);
    }
}
