<?php

namespace Solspace\Addons\FreeformNext\Library\Pro\Fields;

use Override;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\TextField;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Constraints\PhoneConstraint;

class PhoneField extends TextField
{
    /** @var string */
    protected $pattern;

    /**
     * Return the field TYPE
     *
     * @return string
     */
    #[Override]
    public function getType(): string
    {
        return self::TYPE_PHONE;
    }

    /**
     * @return string|null
     */
    public function getPattern()
    {
        return !empty($this->pattern) ? $this->pattern : null;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getConstraints(): array
    {
        return [
            new PhoneConstraint(
                $this->translate('Invalid phone number'),
                $this->getPattern()
            ),
        ];
    }
}
