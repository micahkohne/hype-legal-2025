<?php

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Constraints;

use Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Errors\ConstraintViolationList;

class NumericConstraint implements ConstraintInterface
{
    private ?int $min = null;

    private ?int $max = null;

    private ?int $decimalCount = null;

    private string $decimalSeparator;

    private bool $allowNegativeNumbers;

    /**
     * NumericConstraint constructor.
     *
     * @param int    $min
     * @param int    $max
     * @param int    $decimalCount
     * @param string $decimalSeparator
     * @param string $thousandsSeparator
     * @param bool   $allowNegativeNumbers
     * @param string $message
     * @param string $messageMax
     * @param string $messageMin
     * @param string $messageMinMax
     * @param string $messageDecimals
     * @param string $messageNegative
     */
    public function __construct(
        $min = null,
        $max = null,
        $decimalCount = null,
        $decimalSeparator = null,
        private $thousandsSeparator = ',',
        $allowNegativeNumbers = false,
        private $message = 'Value must be numeric',
        private $messageMax = 'The value must be no more than {{max}}',
        private $messageMin = 'The value must be no less than {{min}}',
        private $messageMinMax = 'The value must be between {{min}} and {{max}}',
        private $messageDecimals = '{{dec}} decimal places allowed',
        private $messageNegative = 'Only positive numbers allowed'
    ) {
        $this->min                  = $min > 0 ? (int) $min : null;
        $this->max                  = $max > 0 ? (int) $max : null;
        $this->decimalCount         = $decimalCount > 0 ? (int) $decimalCount : null;
        $this->decimalSeparator     = $decimalSeparator ?: '.';
        $this->allowNegativeNumbers = (bool) $allowNegativeNumbers;
    }

    /**
     * @inheritDoc
     */
    public function validate($value)
    {
        $violationList = new ConstraintViolationList();

        $decimalSeparator = $this->decimalSeparator ? "\\" . $this->decimalSeparator : '';
        $thousandsSeparator = $this->thousandsSeparator ? "\\" . $this->thousandsSeparator : '';

        $pattern = "/^-?\d*({$thousandsSeparator}\d{3})*({$decimalSeparator}\d+)?$/";

        if (!preg_match($pattern, $value, $matches)) {
            $violationList->addError($this->message);

            return $violationList;
        }

        // If there are decimals specified
        if (isset($matches[2])) {
            if ($this->decimalCount !== null) {
                $decimals = substr($matches[2], 1);

                if (strlen($decimals) > $this->decimalCount) {
                    $message = str_replace('{{dec}}', $this->decimalCount, $this->messageDecimals);
                    $violationList->addError($message);
                }
            } else {
                $message = str_replace('{{dec}}', 0, $this->messageDecimals);
                $violationList->addError($message);
            }
        }

        // Normalize null → '' to avoid PHP 8.3+ deprecation
        $thousandsSeparatorValue = $this->thousandsSeparator ?? '';
        $decimalSeparatorValue   = $this->decimalSeparator ?? '';

        $numericValue = str_replace($thousandsSeparatorValue, '', $value);
        $numericValue = preg_replace("/[^0-9\-{$decimalSeparatorValue}]/", '', $numericValue);

        if (!$this->allowNegativeNumbers && $numericValue < 0) {
            $violationList->addError($this->messageNegative);
        }

        $minEnabled = $this->min !== null;
        $maxEnabled = $this->max !== null;

        if ($minEnabled && !$maxEnabled && $numericValue < $this->min) {
            $message = str_replace('{{min}}', $this->min, $this->messageMin);
            $violationList->addError($message);
        } else if ($maxEnabled && !$minEnabled && $numericValue > $this->max) {
            $message = str_replace('{{max}}', $this->max, $this->messageMax);
            $violationList->addError($message);
        } else if ($minEnabled && $maxEnabled && ($numericValue < $this->min || $numericValue > $this->max)) {
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
