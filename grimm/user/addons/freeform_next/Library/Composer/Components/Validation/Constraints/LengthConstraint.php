<?php

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Constraints;

use Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Errors\ConstraintViolationList;

class LengthConstraint implements ConstraintInterface
{
    private readonly ?int $min;

    private readonly ?int $max;

    /**
     * NumericConstraint constructor.
     *
     * @param int    $min
     * @param int    $max
     * @param string $messageMax
     * @param string $messageMin
     * @param string $messageMinMax
     */
    public function __construct(
        $min = null,
        $max = null,
        private $messageMax = 'The value must be no more than {{max}} characters',
        private $messageMin = 'The value must be no less than {{min}} characters',
        private $messageMinMax = 'The value must be between {{min}} and {{max}} characters'
    ) {
        $this->min                  = $min > 0 ? (int) $min : null;
        $this->max                  = $max > 0 ? (int) $max : null;
    }

    /**
     * @inheritDoc
     */
    public function validate($value): ConstraintViolationList
    {
        $violationList = new ConstraintViolationList();

        $length     = strlen((string) $value);
        $minEnabled = $this->min !== null;
        $maxEnabled = $this->max !== null;

        if ($minEnabled && !$maxEnabled && $length < $this->min) {
            $message = str_replace('{{min}}', $this->min, $this->messageMin);
            $violationList->addError($message);
        } else if ($maxEnabled && !$minEnabled && $length > $this->max) {
            $message = str_replace('{{max}}', $this->max, $this->messageMax);
            $violationList->addError($message);
        } else if ($minEnabled && $maxEnabled && ($length < $this->min || $length > $this->max)) {
            $message = str_replace(
                ['{{min}}', '{{max}}'],
                [$this->min, $this->max],
                $this->messageMinMax
            );
            $violationList->addError($message);
        }

        return $violationList;
    }
}
