<?php

namespace Solspace\Addons\FreeformNext\Library\Pro\Fields;

use Override;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\TextField;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Constraints\WebsiteConstraint;

class WebsiteField extends TextField
{
    /**
     * Return the field TYPE
     *
     * @return string
     */
    #[Override]
    public function getType(): string
    {
        return self::TYPE_WEBSITE;
    }

    /**
     * @inheritDoc
     */
    #[Override]
    public function getConstraints(): array
    {
        return [
            new WebsiteConstraint($this->translate('Website not valid')),
        ];
    }
}
