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

namespace Solspace\Addons\FreeformNext\Library\Codepack\Components;

use Solspace\Addons\FreeformNext\Library\Codepack\Exceptions\FileObject\FileNotFoundException;

class AssetsFileComponent extends AbstractFileComponent
{
    private static array $modifiableFileExtensions = ['css', 'scss', 'sass', 'less', 'js', 'coffee'];

    private static array $modifiableCssFiles = ['css', 'scss', 'sass', 'less'];

    /**
     * @return string
     */
    protected function getInstallDirectory(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/assets';
    }

    /**
     * @return string
     */
    protected function getTargetFilesDirectory(): string
    {
        return 'assets';
    }

    /**
     * If anything has to be done with a file once it's copied over
     * This method does it
     *
     * @param string $content
     * @param string|null $prefix
     *
     * @return string|null
     * @throws FileNotFoundException
     */
    public function fileContentModification($content, ?string $prefix = null): ?string
    {
        if (!file_exists($content)) {
            throw new FileNotFoundException(
                sprintf('Could not find file: %s', $content)
            );
        }

        $extension = strtolower(pathinfo($content, PATHINFO_EXTENSION));

        // Prevent from editing anything other than css and js files
        if (!in_array($extension, self::$modifiableFileExtensions, true)) {
            return null;
        }

        $contents = file_get_contents($content);
        if ($contents === false) {
            // Couldn’t read; don’t proceed
            return null;
        }

        if (in_array($extension, self::$modifiableCssFiles, true)) {
            $contents = $this->updateImagesURL($contents, $prefix);
            //$contents = $this->updateRelativePaths($contents, $prefix);
            $contents = $this->replaceCustomPrefixCalls($contents, $prefix);
        }

        file_put_contents($content, $contents);

        return $contents;
    }

    /**
     * This pattern matches all url(/images[..]) with or without surrounding quotes
     * And replaces it with the prefixed asset path
     *
     * @param mixed $content
     * @param string|null $prefix
     *
     * @return string|array|null
     */
    private function updateImagesURL(mixed $content, ?string $prefix = null): string|array|null
    {
        $pattern = '/url\s*\(\s*([\'"]?)\/((?:images)\/[a-zA-Z1-9_\-\.\/]+)[\'"]?\s*\)/';
        $replace = 'url($1/assets/' . $prefix . '/$2$1)';

        return preg_replace($pattern, $replace, $content);
    }

    /**
     * Updates all "../somePath/" urls to "../$prefix_somePath/" urls
     *
     * @param mixed $content
     * @param string|null $prefix
     *
     * @return string|array|null
     */
    private function updateRelativePaths(mixed $content, ?string $prefix = null): string|array|null
    {
        $pattern = '/([\(\'"])\.\.\/([^"\'())]+)([\'"\)])/';
        $replace = '$1../' . $prefix . '$2$3';

        return preg_replace($pattern, $replace, $content);
    }

    /**
     * @param mixed $content
     * @param string|null $prefix
     *
     * @return string|array|null
     */
    private function replaceCustomPrefixCalls(mixed $content, ?string $prefix = null): string|array|null
    {
        return preg_replace('/(%prefix%)/', $prefix, $content);
    }
}
