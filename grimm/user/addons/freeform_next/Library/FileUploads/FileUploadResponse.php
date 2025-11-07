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

namespace Solspace\Addons\FreeformNext\Library\FileUploads;

class FileUploadResponse
{
    /** @var int[] */
    private array $assetIds;

    /**
     * FileUploadResponse constructor.
     *
     * @param int[] $assetIds
     */
    public function __construct(?array $assetIds = null, private array $errors = [])
    {
        $this->assetIds = $assetIds ?: [];
    }

    /**
     * @return int[]
     */
    public function getAssetIds(): array
    {
        return $this->assetIds;
    }

    /**
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }
}
