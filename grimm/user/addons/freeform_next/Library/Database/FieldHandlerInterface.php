<?php

namespace Solspace\Addons\FreeformNext\Library\Database;

use Solspace\Addons\FreeformNext\Library\Composer\Components\AbstractField;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\DataContainers\Option;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Form;
use Solspace\Addons\FreeformNext\Library\Configuration\ExternalOptionsConfiguration;

interface FieldHandlerInterface
{
    /**
     * Perform actions with a field before validation takes place
     */
    public function beforeValidate(AbstractField $field, Form $form);

    /**
     * Perform actions with a field after validation takes place
     */
    public function afterValidate(AbstractField $field, Form $form);

    /**
     * @param string $source
     *
     * @return Option[]
     */
    public function getOptionsFromSource($source, mixed $target, array $configuration = [], mixed $selectedValues = []);
}
