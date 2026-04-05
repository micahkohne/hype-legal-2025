<?php

/**
 * @author TJ Draper <tj@buzzingpixel.com>
 * @copyright 2018 BuzzingPixel, LLC
 * @license http://www.apache.org/licenses/LICENSE-2.0
 */

// Include configuration file
include_once PATH_THIRD . '/marksmin/addon.setup.php';

/**
 * Class Marksmin_ext
 */
class Marksmin_ext
{
    // Set properties for EE
    public $description = MARKSMIN_DESCRIPTION;
    public $docs_url = MARKSMIN_DOCS_URL;
    public $name = MARKSMIN_NAME;
    public $settings_exist = 'n';
    public $version = MARKSMIN_VER;

    /**
     * Activate Extension
     *
     * @return void
     */
    public function activate_extension()
    {
        ee()->db->insert('extensions', array(
            'class' => __CLASS__,
            'method' => 'template_post_parse',
            'hook' => 'template_post_parse',
            'settings' => '',
            'priority' => 10,
            'version' => $this->version,
            'enabled' => 'y'
        ));
    }

    /**
     * Update Extension
     * @param string $current
     * @return bool
     */
    public function update_extension($current = '')
    {
        if ($current === '' || $current === $this->version) {
            return false;
        }

        ee()->db->where('class', __CLASS__);
        ee()->db->update('extensions', array(
            'version' => $this->version
        ));

        return true;
    }

    /**
     * Disable Extension
     */
    public function disable_extension()
    {
        ee()->db->where('class', __CLASS__);
        ee()->db->delete('extensions');
    }

    /**
     * Whether parsed output is plausibly HTML when TMPL is unavailable (avoid minifying XML/JSON, etc.).
     *
     * @param string $template
     * @return bool
     */
    private static function isPlausiblyHtmlForMinification($template)
    {
        if (! is_string($template) || $template === '') {
            return false;
        }
        if (preg_match('/^\s*<\?xml\b/i', $template)) {
            return false;
        }

        return (bool) preg_match('/^\s*(?:<!DOCTYPE\b|<html\b)/i', $template);
    }

    /**
     * Resolve template type and group/name from TMPL or from EE6+ hook metadata.
     *
     * @param array|null $currentTemplateInfo EE passes an array from template_post_parse (EE6+); may be absent on older EE.
     * @return array{type: string|null, group: string, name: string}
     */
    private function resolveTemplateRoutingContext($currentTemplateInfo)
    {
        $ee = ee();
        if (is_object($ee) && method_exists($ee, 'has') && $ee->has('TMPL')) {
            /** @var \EE_Template $tmpl */
            $tmpl = $ee->TMPL;

            return array(
                'type' => $tmpl->template_type,
                'group' => $tmpl->group_name,
                'name' => $tmpl->template_name,
            );
        }

        if (is_array($currentTemplateInfo)) {
            return array(
                'type' => isset($currentTemplateInfo['template_type'])
                    ? $currentTemplateInfo['template_type']
                    : null,
                'group' => isset($currentTemplateInfo['template_group'])
                    ? $currentTemplateInfo['template_group']
                    : '',
                'name' => isset($currentTemplateInfo['template_name'])
                    ? $currentTemplateInfo['template_name']
                    : '',
            );
        }

        return array('type' => null, 'group' => '', 'name' => '');
    }

    /**
     * Method for template_post_parse hook
     * @param string $template Parsed template string
     * @param bool $sub Whether an embed, layout, or other partial (EE: $is_partial)
     * @param string|null $site_id Site ID (EE6+)
     * @param array|null $currentTemplateInfo Template group/name (EE6+); see ExpressionEngine #1195 / #1335
     * @return string Template string
     */
    public function template_post_parse($template, $sub, $site_id = null, $currentTemplateInfo = null)
    {
        /** @var \EE_Config $eeConfigService */
        $eeConfigService = ee()->config;

        /** @var \EE_Extensions $eeExtensionsService */
        $eeExtensionsService = ee()->extensions;

        $ctx = $this->resolveTemplateRoutingContext($currentTemplateInfo);
        $type = $ctx['type'];
        $groupName = $ctx['group'];
        $templateName = $ctx['name'];

        $currentTemplate = "{$groupName}/{$templateName}";
        $notFoundTemplate = $eeConfigService->item('site_404');

        if ($type === 'webpage' ||
            $type === '404' ||
            $currentTemplate === $notFoundTemplate ||
            ($type === null && self::isPlausiblyHtmlForMinification($template))
        ) {
            // Play nice with other extensions
            if (isset($eeExtensionsService->last_call) &&
                $eeExtensionsService->last_call
            ) {
                $template = $eeExtensionsService->last_call;
            }

            // Do nothing if not final template
            if ($sub !== false) {
                return $template;
            }

            // Is HTML minification disabled
            if ($eeConfigService->item('marksmin_enabled') !== true) {
                return $template;
            }

            $options = array(
                'xhtml' => ee()->config->item('marksmin_xhtml')
            );

            if (! class_exists('Minify_HTML', false)) {
                $autoloadPaths = array(
                    __DIR__ . '/vendor/autoload.php',
                    PATH_THIRD . '/marksmin/vendor/autoload.php',
                );
                foreach ($autoloadPaths as $autoload) {
                    if (is_readable($autoload)) {
                        require_once $autoload;
                        break;
                    }
                }
            }

            if (! class_exists('Minify_HTML', false)) {
                $minifyHtml = __DIR__ . '/vendor/mrclay/minify/lib/Minify/HTML.php';
                if (is_readable($minifyHtml)) {
                    require_once $minifyHtml;
                }
            }

            if (! class_exists('Minify_HTML', false)) {
                throw new \RuntimeException(
                    'Marksmin is missing vendor/mrclay/minify (Composer install not run or vendor/ not deployed). '
                    . 'From the marksmin add-on folder, run: composer install'
                );
            }

            return \Minify_HTML::minify($template, $options);
        }

        return $template;
    }
}
