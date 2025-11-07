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

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\DataContainers;

use JsonSerializable;
class Option implements JsonSerializable
{
    /**
     * Option constructor.
     *
     * @param string $label
     * @param string $value
     * @param bool   $checked
     */
    public function __construct(private $label, private $value, private $checked = false)
    {
    }

    /**
     * @return string
     */
    public function getLabel(): string
    {
        return (string) $this->label;
    }

    /**
     * @return string
     */
    public function getValue(): string
    {
        return (string) $this->value;
    }

    /**
     * @return bool
     */
    public function isChecked()
    {
        return $this->checked;
    }

	/**
	 * @return array
	 */
    public function jsonSerialize(): array
    {
        return [
            'label'   => $this->getLabel(),
            'value'   => $this->getValue(),
        ];
    }
}
