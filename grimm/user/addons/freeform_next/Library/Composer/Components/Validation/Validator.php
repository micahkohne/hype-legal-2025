<?php

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Validation;

use Solspace\Addons\FreeformNext\Library\Composer\Components\AbstractField;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Validation\Errors\ConstraintViolationList;

class Validator
{
    /**
     *
     * @return ConstraintViolationList
     */
    public function validate(AbstractField $field, mixed $value): ConstraintViolationList
    {
        $violationList = new ConstraintViolationList();

        $constraints = $field->getConstraints();
        foreach ($constraints as $constraint) {
            $violationList->merge($constraint->validate($value));
        }

        return $violationList;
    }
}
