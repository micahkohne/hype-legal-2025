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

namespace Solspace\Addons\FreeformNext\Library\Composer\Components;

use JsonSerializable;
use Iterator;
use ArrayAccess;
use ReturnTypeWillChange;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\Interfaces\NoStorageInterface;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\Interfaces\RememberPostedValueInterface;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\Interfaces\StaticValueInterface;
use Solspace\Addons\FreeformNext\Library\Exceptions\FreeformException;

class Page implements JsonSerializable, Iterator, ArrayAccess
{
    private readonly int $index;

    /**
     * Page constructor.
     *
     * @param int              $index
     * @param string           $label
     * @param Row[]            $rows
     * @param FieldInterface[] $fields
     */
    public function __construct($index, private $label, private array $rows, private readonly array $fields)
    {
        $this->index  = (int)$index;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return int
     */
    public function getIndex(): int
    {
        return $this->index;
    }

    /**
     * @return Row[]
     */
    public function getRows(): array
    {
        return $this->rows;
    }

    /**
     * @return FieldInterface[]
     */
    public function getFields(): array
    {
        return $this->fields;
    }

    /**
     * @return array
     */
    public function getStorableFieldValues(): array
    {
        $submittedValues = [];

        foreach ($this->getFields() as $field) {
            if ($field instanceof NoStorageInterface && !$field instanceof RememberPostedValueInterface) {
                continue;
            }

            $value = $field->getValue();
            if ($field instanceof StaticValueInterface && !empty($value)) {
                $value = $field->getStaticValue();
            }

            $submittedValues[$field->getHandle()] = $value;
        }

        return $submittedValues;
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return array
	 */
    public function jsonSerialize(): array
	{
        return $this->rows;
    }

    /**
     * Return the current element
     *
     * @return mixed
     */
	#[ReturnTypeWillChange]
    public function current()
    {
        return current($this->rows);
    }

    /**
     * Move forward to next element
     *
     * @return void
     */
	#[ReturnTypeWillChange]
    public function next(): void
    {
        next($this->rows);
    }

    /**
     * Return the key of the current element
     *
     * @return mixed
     */
	#[ReturnTypeWillChange]
    public function key()
    {
        return key($this->rows);
    }

    /**
     * Checks if current position is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return $this->key() !== null && $this->key() !== false;
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @return void
     */
	#[ReturnTypeWillChange]
    public function rewind(): void
    {
        reset($this->rows);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return isset($this->rows[$offset]);
    }

    /**
     * @inheritDoc
     */
	#[ReturnTypeWillChange]
    public function offsetGet($offset)
    {
        return $this->offsetExists($offset) ? $this->rows[$offset] : null;
    }

    /**
     * @inheritDoc
     */
	#[ReturnTypeWillChange]
    public function offsetSet($offset, $value)
    {
        throw new FreeformException("Form Page ArrayAccess does not allow for setting values");
    }

    /**
     * @inheritDoc
     */
	#[ReturnTypeWillChange]
    public function offsetUnset($offset)
    {
        throw new FreeformException("Form Page ArrayAccess does not allow unsetting values");
    }
}
