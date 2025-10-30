<?php

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Constraints;

use Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Errors\ConstraintViolationList;

class WebsiteConstraint implements ConstraintInterface
{
    const PATTERN = '/^((((http(s)?)|(sftp)|(ftp)|(ssh)):\/\/)|(\/\/))?(www\.)?[-a-zA-Z0-9@:%._\+~#=]{2,256}\.[a-z]{2,6}\b([-a-zA-Z0-9@:%_\+.~#?&\/=]*)$/i';

    /**
     * WebsiteConstraint constructor.
     *
     * @param string $message
     */
    public function __construct(private $message = 'Website not valid')
    {
    }

    /**
     * @inheritDoc
     */
    public function validate($value): ConstraintViolationList
    {
        $violationList = new ConstraintViolationList();

        if (!preg_match(self::PATTERN, (string) $value)) {
            $violationList->addError($this->message);
        }

        return $violationList;
    }
}
