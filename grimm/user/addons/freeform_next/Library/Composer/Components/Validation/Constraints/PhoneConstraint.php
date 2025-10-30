<?php

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Constraints;

use Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Errors\ConstraintViolationList;

class PhoneConstraint implements ConstraintInterface
{
    /**
     * The pattern is going to look like this:
     * (xxx) xxxx xxx
     * Anything other than an X is going to be assumed literal
     * an X stands for any digit between 0 and 9
     *
     * @var string
     */
    private $pattern;

    /**
     * RegexConstraint constructor.
     *
     * @param string $message
     * @param string $pattern
     */
    public function __construct(private $message = 'Invalid phone number', $pattern = null)
    {
        $this->pattern = !empty($pattern) ? $pattern : null;
    }

    /**
     * @inheritDoc
     */
    public function validate($value)
    {
        $violationList = new ConstraintViolationList();
        $pattern       = $this->pattern;

        if (null !== $pattern) {
            $compiledPattern = $pattern;
            $compiledPattern = preg_replace('/([\[\](){}$+_\-+])/', '\\\\$1', $compiledPattern);
            preg_match_all('/(x+)/i', (string) $compiledPattern, $matches);

            if (isset($matches[1])) {
                foreach ($matches[1] as $match) {
                    $compiledPattern = preg_replace(
                        '/' . $match . '/',
                        '[0-9]{' . strlen($match) . '}',
                        (string) $compiledPattern,
                        1
                    );
                }
            }

            $compiledPattern = '/^' . $compiledPattern . '$/';

            if (!preg_match($compiledPattern, (string) $value)) {
                $violationList->addError($this->message);
            }

            return $violationList;
        }

        if (!preg_match('/^\+?[0-9\- ,.\(\)]+$/', (string) $value)) {
            $violationList->addError('Phone number is invalid.');
        }

        return $violationList;
    }
}
