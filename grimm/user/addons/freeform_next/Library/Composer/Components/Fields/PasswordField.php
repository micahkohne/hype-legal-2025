<?php
/**
 * Freeform for ExpressionEngine
 *
 * @package       Solspace:Freeform
 * @author        Solspace, Inc.
 * @copyright     Copyright (c) 2008-2025, Solspace, Inc.
 * @link          https://docs.solspace.com/expressionengine/freeform/v3/
 * @license       https://docs.solspace.com/license-agreement/
 */

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Fields;

use Override;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\Interfaces\NoStorageInterface;

class PasswordField extends TextField implements NoStorageInterface
{
    /**
     * Return the field TYPE
     *
     * @return string
     */
    #[Override]
    public function getType(): string
    {
        return self::TYPE_PASSWORD;
    }

    /**
     * Outputs the HTML of input
     *
     * @return string
     */
    #[Override]
    public function getInputHtml(): string|array
    {
        $output = parent::getInputHtml();
        $output = str_replace('type="text"', 'type="password"', $output);

        return $output;
    }
}
