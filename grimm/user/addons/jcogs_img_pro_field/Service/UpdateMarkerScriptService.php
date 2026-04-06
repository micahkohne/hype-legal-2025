<?php

/**
 * JCOGS Image Pro Field - UpdateMarkerScriptService
 * =================================================
 * Builds reusable CP update marker JavaScript.
 *
 * @category   ExpressionEngine Add-on
 * @package    JCOGS Image Pro Field
 * @author     JCOGS Design <contact@jcogs.net>
 * @copyright  2026 JCOGS Design
 * @license    JCOGS Design Commercial License
 * @version    1.0.2
 * @link       https://jcogs.net/documentation/jcogs_img_pro_field
 */

namespace JCOGSDesign\JcogsImgProField\Service;

class UpdateMarkerScriptService
{
    public function buildScript(string $addonShortName, string $tileLabel, string $cpLabel, string $changelogUrl): string
    {
        $tileLabelJs = json_encode($tileLabel, JSON_UNESCAPED_SLASHES);
        $cpLabelJs = json_encode($cpLabel, JSON_UNESCAPED_SLASHES);
        $changelogUrlJs = json_encode($changelogUrl, JSON_UNESCAPED_SLASHES);
        $isValidUrlJs = $changelogUrl !== '' ? 'true' : 'false';
        $addonShortNameJs = json_encode($addonShortName, JSON_UNESCAPED_SLASHES);

        $script = "(function($){
            var addonShortName = " . $addonShortNameJs . ";
            var changelogUrl = " . $changelogUrlJs . ";
            var hasChangelog = " . $isValidUrlJs . ";
            var tileLabel = " . $tileLabelJs . ";
            var cpLabel = " . $cpLabelJs . ";

            var card = $('div[data-addon=\"' + addonShortName + '\"]');
            if (card.length && card.find('.jcogs-update-inline-marker').length === 0) {
                card.css('position', 'relative');
                var markerHtml = '<span class=\"jcogs-update-inline-marker button button--default button--small badge badge--info\" title=\"' + tileLabel + '\" style=\"margin-top: 8px; white-space: nowrap; pointer-events: none; position: absolute; bottom: 5px; left: 90px; line-height:5px;\">' + tileLabel + '</span>';
                card.append(markerHtml);
            }

            if (window.location.href.indexOf('/addons/settings/' + addonShortName) !== -1 && $('body.add-on-layout .main-nav__title .jcogs-update-status-link').length === 0) {
                var cpMarkerHtml = hasChangelog
                    ? '<a class=\"jcogs-update-status-link button button--default button--small badge badge--info\" href=\"' + changelogUrl + '\" target=\"_blank\" rel=\"noopener noreferrer\" title=\"' + cpLabel + '\" style=\"margin-left:0px;white-space:nowrap;line-height:5px;display:inline-block;pointer-events:auto;position:relative;z-index:10;\">' + cpLabel + '</a>'
                    : '<span class=\"jcogs-update-status-link button button--default button--small badge badge--info\" title=\"' + cpLabel + '\" style=\"margin-left:0px;white-space:nowrap;line-height:5px;\">' + cpLabel + '</span>';
                $('body.add-on-layout .main-nav__title').append(cpMarkerHtml);
            }
        })(jQuery);";

        return preg_replace('/\s+/', ' ', $script) ?: '';
    }
}
