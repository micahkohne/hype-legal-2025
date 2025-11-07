<?php

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Constraints;

use DateTime;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Errors\ConstraintViolationList;

class DateTimeConstraint implements ConstraintInterface
{
    public const PATTERN_MATCH_FORMAT = '/^((?:[Yy])|(?:[mn])|(?:[dj]))(.*?)((?:[Yy])|(?:[mn])|(?:[dj]))(.*?)((?:[Yy])|(?:[mn])|(?:[dj]))$/';

    /**
     * DateConstraint constructor.
     *
     * @param string $message
     * @param string $format
     */
    public function __construct(private $message, private $format)
    {
    }

    /**
     * @inheritDoc
     */
    public function validate($value): ConstraintViolationList
    {
        $violationList = new ConstraintViolationList();

        $format = $this->parseFormat($this->format);
        $value  = $this->parseValue($value);

        $date = DateTime::createFromFormat($format, $value);
        if (!$date || $date->format($format) !== $value) {
            $violationList->addError($this->message);
        }

        return $violationList;
    }

    /**
     * Forces lowercase AM/PM in the format
     *
     * @param string $format
     *
     * @return string
     */
    private function parseFormat($format): string|array|null
    {
        $format = preg_replace('/\s?A/i', 'a', $format);

        return $format;
    }

    /**
     * Makes any combination of AM/PM into a lowercase "am/pm" equivalent
     *
     * @param string $value
     *
     * @return string
     */
    private function parseValue($value)
    {
        if (preg_match('/(\d)\s?([AaPp])\.?([Mm])?\.?\s*/', $value, $matches)) {
            $value = preg_replace(
                '/(\d)\s?([AaPp])\.?([Mm])?\.?\s*/',
                $matches[1] . strtolower($matches[2]) . 'm',
                $value
            );
        }

        return $value;
    }
}
