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

class FileDownloadView extends View implements RenderlessViewInterface
{
    /**
     * AjaxView constructor.
     *
     * @param string $fileName
     * @param string $content
     */
    public function __construct(private $fileName, private $content)
    {
    }

    /**
     * @return array
     */
    public function compile(): never
    {
        $fileName = sprintf('"%s"', addcslashes(($this->fileName), '"\\'));
        $size     = strlen($this->content);

        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . $fileName);
        header('Content-Transfer-Encoding: binary');
        header('Connection: Keep-Alive');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . $size);

        echo $this->content;

        die();
    }
}
