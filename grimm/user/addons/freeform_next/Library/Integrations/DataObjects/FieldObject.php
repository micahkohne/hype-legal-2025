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

namespace Solspace\Addons\FreeformNext\Library\Integrations\DataObjects;

use JsonSerializable;
class FieldObject implements JsonSerializable
{
    public const TYPE_STRING  = 'string';
    public const TYPE_ARRAY   = 'array';
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_BOOLEAN = 'boolean';

    private bool $required;

    /**
     * @return array
     */
    public static function getTypes(): array
    {
        return [self::TYPE_STRING, self::TYPE_NUMERIC, self::TYPE_BOOLEAN, self::TYPE_ARRAY];
    }

    /**
     * @return string
     */
    public static function getDefaultType(): string
    {
        return self::TYPE_STRING;
    }

    /**
     * @param string $handle
     * @param string $label
     * @param string $type
     * @param bool   $required
     */
    public function __construct(private $handle, private $label, private $type, $required = false)
    {
        $this->required = (bool)$required;
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
    public function getType()
    {
        return $this->type;
    }

    /**
     * @return boolean
     */
    public function isRequired(): bool
    {
        return (bool)$this->required;
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'handle'   => $this->getHandle(),
            'label'    => $this->getLabel(),
            'required' => $this->isRequired(),
        ];
    }
}
