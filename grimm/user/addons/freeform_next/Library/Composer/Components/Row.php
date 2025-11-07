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
use Countable;
use ReturnTypeWillChange;
use Solspace\Addons\FreeformNext\Library\Exceptions\FreeformException;

class Row implements JsonSerializable, Iterator, ArrayAccess, Countable
{
    /**
     * @param string $id
     * @param AbstractField[] $fields
     */
    public function __construct(private $id, private array $fields)
    {
    }

    /**
     * Specify data which should be serialized to JSON
     *
     * @return mixed
     */
    public function jsonSerialize(): array
    {
        return [
            "id"      => $this->id,
            "columns" => $this->fields,
        ];
    }

    /**
     * Return the current element
     *
     * @return mixed
     */
	#[ReturnTypeWillChange]
	public function current(): mixed
    {
        return current($this->fields);
    }

    /**
     * Move forward to next element
     *
     * @return void
     */
	#[ReturnTypeWillChange]
    public function next(): void
    {
        next($this->fields);
    }

	/**
	 * Return the key of the current element
	 *
	 * @return int|null
	 */
	#[ReturnTypeWillChange]
	public function key(): mixed
    {
        return key($this->fields);
    }

    /**
     * Checks if current position is valid
     *
     * @return bool
     */
    public function valid(): bool
    {
        return !is_null($this->key()) && $this->key() !== false;
    }

    /**
     * Rewind the Iterator to the first element
     *
     * @return void
     */
	#[ReturnTypeWillChange]
    public function rewind(): void
    {
        reset($this->fields);
    }

    /**
     * Count elements of an object
     *
     * @return int
     */
    public function count(): int
    {
        return count($this->fields);
    }

    /**
     * @inheritDoc
     */
    public function offsetExists($offset): bool
    {
        return isset($this->fields[$offset]);
    }

    /**
     * @inheritDoc
     */
	#[ReturnTypeWillChange]
    public function offsetGet($offset): mixed
    {
        return $this->offsetExists($offset) ? $this->fields[$offset] : null;
    }

    /**
     * @inheritDoc
     */
	#[ReturnTypeWillChange]
    public function offsetSet($offset, $value): void
    {
        throw new FreeformException("Form Page Row ArrayAccess does not allow unsetting values");
    }

    /**
     * @inheritDoc
     */
	#[ReturnTypeWillChange]
    public function offsetUnset($offset): void
    {
        throw new FreeformException("Form Page Row ArrayAccess does not allow unsetting values");
    }
}
