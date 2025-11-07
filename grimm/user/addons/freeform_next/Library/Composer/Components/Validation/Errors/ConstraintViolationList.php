<?php

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Errors;

use Countable;
use Stringable;
class ConstraintViolationList implements Countable, Stringable
{
    private array $errors;
    /**
     * ValidationErrors constructor.
     */
    public function __construct()
    {
        $this->errors = [];
    }
    /**
     * @return string
     */
    public function __toString(): string
    {
        return implode('; ', $this->errors);
    }
    /**
     * @param string $message
     */
    public function addError($message): void
    {
        $this->errors[] = $message;
    }
    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
    /**
     * @inheritDoc
     */
    public function count(): int
    {
        return count($this->errors);
    }
    /**
     * @return $this
     */
    public function merge(ConstraintViolationList $list)
    {
        foreach ($list->getErrors() as $error) {
            $this->addError($error);
        }

        return $this;
    }
}
