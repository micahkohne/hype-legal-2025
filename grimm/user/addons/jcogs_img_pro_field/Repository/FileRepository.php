<?php

/**
 * JCOGS Image Pro Field - FileRepository
 *======================================
 * Wraps EE model access for files.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.2
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 * @since      0.1.6
 */

namespace JCOGSDesign\JcogsImgProField\Repository;

final class FileRepository
{
    /**
     * Fetch file model with UploadDestination eager-loaded.
     *
     * @return object|null
     */
    public function findFileWithUploadDestination(int $fileId): ?object
    {
        if ($fileId <= 0) {
            return null;
        }

        try {
            $file = ee('Model')->get('File', $fileId)->with('UploadDestination')->first();
            return is_object($file) ? $file : null;
        } catch (\Throwable $e) {
            return null;
        }
    }
}
