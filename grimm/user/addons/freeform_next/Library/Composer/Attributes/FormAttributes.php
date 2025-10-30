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

namespace Solspace\Addons\FreeformNext\Library\Composer\Attributes;

use Solspace\Addons\FreeformNext\Library\Helpers\HashHelper;
use Solspace\Addons\FreeformNext\Library\Session\FormValueContext;
use Solspace\Addons\FreeformNext\Library\Session\RequestInterface;
use Solspace\Addons\FreeformNext\Library\Session\SessionInterface;

class FormAttributes
{
    /** @var bool */
    private $csrfEnabled;

    /** @var string */
    private $csrfToken;

    /** @var string */
    private $csrfTokenName;

    /** @var string */
    private $actionUrl;

    private string $method;

    /** @var FormValueContext */
    private $formValueContext;

    /**
     * FormAttributes constructor.
     *
     * @param                  $formId
     * @param SessionInterface $session
     * @param RequestInterface $request
     * @param int $formId
     */
    public function __construct(private $id, SessionInterface $session, RequestInterface $request)
    {
        $this->method = 'POST';
        $this->setFormValueContext($session, $request);
    }

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return boolean
     */
    public function isCsrfEnabled()
    {
        return $this->csrfEnabled;
    }

    /**
     * @param boolean $csrfEnabled
     *
     * @return $this
     */
    public function setCsrfEnabled($csrfEnabled): static
    {
        $this->csrfEnabled = $csrfEnabled;

        return $this;
    }

    /**
     * @return string
     */
    public function getCsrfToken()
    {
        return $this->csrfToken;
    }

    /**
     * @param string $csrfToken
     *
     * @return $this
     */
    public function setCsrfToken($csrfToken): static
    {
        $this->csrfToken = $csrfToken;

        return $this;
    }

    /**
     * @return string
     */
    public function getCsrfTokenName()
    {
        return $this->csrfTokenName;
    }

    /**
     * @param string $csrfTokenName
     *
     * @return $this
     */
    public function setCsrfTokenName($csrfTokenName): static
    {
        $this->csrfTokenName = $csrfTokenName;

        return $this;
    }

    /**
     * @return string
     */
    public function getActionUrl()
    {
        return $this->actionUrl;
    }

    /**
     * @param string $actionUrl
     *
     * @return $this
     */
    public function setActionUrl($actionUrl): static
    {
        $this->actionUrl = $actionUrl;

        return $this;
    }

    /**
     * @return string
     */
    public function getMethod(): string
    {
        return $this->method;
    }

    /**
     * @param string $method
     *
     * @return $this
     */
    public function setMethod(string $method): static
    {
        $this->method = $method;

        return $this;
    }

    /**
     * @return FormValueContext
     */
    public function getFormValueContext()
    {
        return $this->formValueContext;
    }

    /**
     * @param SessionInterface $session
     * @param RequestInterface $request
     *
     * @return FormValueContext
     */
    private function setFormValueContext(SessionInterface $session, RequestInterface $request): void
    {
        $hashPrefix = HashHelper::hash($this->id);

        $this->formValueContext = $session->get(
            $hashPrefix . '_form_context',
            new FormValueContext($this->getId(), $session, $request)
        );
    }
}
