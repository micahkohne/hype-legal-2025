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

class CpView extends View
{
    /** @var string */
    private $heading;

    private array $cssList;

    private array $javascriptList;

    private ?bool $sidebarDisabled = null;

    /** @var array */
    private $sections;

    /** @var Modal[] */
    private array $modals;

    /** @var NavigationLink[] */
    private array $breadcrumbs;

    /**
     * CpView constructor.
     *
     * @param       $template
     * @param array $templateVariables
     * @param string $template
     */
    public function __construct(private $template, private array $templateVariables = [])
    {
        $this->cssList           = [];
        $this->javascriptList    = [];
        $this->modals            = [];
        $this->breadcrumbs       = [];
    }

    /**
     * @return string
     */
    public function compile()
    {
        foreach ($this->javascriptList as $path) {
            ee()->cp->load_package_js(preg_replace('/\.js$/is', '', (string) $path));
        }

        foreach ($this->cssList as $path) {
            ee()->cp->load_package_css(preg_replace('/\.css$/is', '', (string) $path));
        }

        foreach ($this->modals as $modal) {
            $modal->compile();
        }

        return ee('View')
            ->make(AddonInfo::getInstance()->getLowerName() . ':' . $this->template)
            ->render($this->getTemplateVariables());
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
    public function setTemplate($template): static
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
     * @param array $templateVariables
     *
     * @return $this
     */
    public function setTemplateVariables(array $templateVariables): static
    {
        $this->templateVariables = $templateVariables;

        return $this;
    }

    /**
     * @param array $templateVariables
     *
     * @return $this
     */
    public function addTemplateVariables(array $templateVariables): static
    {
        if (null === $this->templateVariables) {
            $this->templateVariables = $templateVariables;

            return $this;
        }

        $this->templateVariables = array_merge($this->templateVariables, $templateVariables);

        return $this;
    }

    /**
     * @return string
     */
    public function getHeading()
    {
        return $this->heading;
    }

    /**
     * @param string $heading
     *
     * @return $this
     */
    public function setHeading($heading): static
    {
        $this->heading = $heading;

        return $this;
    }

    /**
     * @return bool
     */
    public function isSidebarDisabled(): bool
    {
        return (bool) $this->sidebarDisabled;
    }

    /**
     * @param bool $sidebarDisabled
     *
     * @return $this
     */
    public function setSidebarDisabled($sidebarDisabled): static
    {
        $this->sidebarDisabled = (bool) $sidebarDisabled;

        return $this;
    }

    /**
     * @param string $scriptPath
     *
     * @return $this
     */
    public function addJavascript($scriptPath): static
    {
        $this->javascriptList[] = $scriptPath;

        return $this;
    }

    /**
     * @param string $cssPath
     *
     * @return $this
     */
    public function addCss($cssPath): static
    {
        $this->cssList[] = $cssPath;

        return $this;
    }

    /**
     * @return array
     */
    public function getSections()
    {
        return $this->sections;
    }

    /**
     * @param array $sections
     */
    public function setSections($sections): void
    {
        $this->sections = $sections;
    }

    /**
     * @param Modal $modal
     *
     * @return $this
     */
    public function addModal(Modal $modal): static
    {
        $this->modals[] = $modal;

        return $this;
    }

    /**
     * @param NavigationLink $link
     *
     * @return $this
     */
    public function addBreadcrumb(NavigationLink $link): static
    {
        $this->breadcrumbs[] = $link;

        return $this;
    }

    /**
     * @return NavigationLink[]
     */
    public function getBreadcrumbs(): array
    {
        return $this->breadcrumbs;
    }
}
