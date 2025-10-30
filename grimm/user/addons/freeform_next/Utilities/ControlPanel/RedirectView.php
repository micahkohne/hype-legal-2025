<?php

namespace Solspace\Addons\FreeformNext\Utilities\ControlPanel;

class RedirectView extends View implements RenderlessViewInterface
{
    /**
     * RedirectView constructor.
     *
     * @param string $url
     */
    public function __construct(private $url)
    {
    }

    public function compile(): never
    {
        header('Location: ' . $this->url);
        die();
    }

}
