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

namespace Solspace\Addons\FreeformNext\Utilities\ControlPanel;

use Solspace\Addons\FreeformNext\Utilities\AddonInfo;
use Solspace\Addons\FreeformNext\Utilities\ControlPanel\Extras\Modal;
use Solspace\Addons\FreeformNext\Utilities\ControlPanel\Navigation\NavigationLink;

class PlainView extends View
{
    /**
     * CpView constructor.
     *
     * @param       $template
     * @param string $template
     */
    public function __construct(private $template, private array $templateVariables = [])
    {
    }

    /**
     * @return string
     */
    public function compile(): string|bool
    {
        ob_start();
        extract($this->templateVariables, EXTR_SKIP);

        include __DIR__ . "/../../View/{$this->template}.php";

        return ob_get_clean();
    }

    /**
     * @return string
     */
    public function getTemplate()
    {
        return $this->template;
    }

    /**
     * @param string $template
     *
     * @return $this
     */
    public function setTemplate($template)
    {
        $this->template = $template;

        return $this;
    }

    /**
     * @return array
     */
    public function getTemplateVariables(): array
    {
        return $this->templateVariables ?: [];
    }

    /**
     * @return $this
     */
    public function setTemplateVariables(array $templateVariables)
    {
        $this->templateVariables = $templateVariables;

        return $this;
    }
}
