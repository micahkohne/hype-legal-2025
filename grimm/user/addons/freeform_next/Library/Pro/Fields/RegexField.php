<?php

namespace Solspace\Addons\FreeformNext\Library\Pro\Fields;

use Override;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\TextField;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Constraints\RegexConstraint;

class RegexField extends TextField
{
    /** @var string */
    protected $pattern;

    /** @var string */
    protected $message;

    /**
     * Return the field TYPE
     *
     * @return string
     */
    #[Override]
    public function getType(): string
    {
        return self::TYPE_REGEX;
    }

    /**
     * @return string
     */
    public function getPattern()
    {
        return $this->pattern;
    }

    /**
     * @return string
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getConstraints(): array
    {
        return [
            new RegexConstraint(
                $this->translate($this->getMessage()),
                $this->getPattern()
            ),
        ];
    }
}
