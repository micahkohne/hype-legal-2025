<?php

namespace BoldMinded\Carson\Tags;

use BoldMinded\Carson\Services\AiRequest;
use ExpressionEngine\Service\Addon\Controllers\Tag\AbstractRoute;

class Assistant extends AbstractRoute
{
    /*
     * {exp:carson:assistant instructions="Summarize this text in a few sentences."}
     *    Some long text to summarize...
     * {/exp:carson:assistant}
     */
    public function process()
    {
        $providerResponse = AiRequest::make(
            ee()->TMPL->tagdata,
            ee()->TMPL->fetch_param('instructions', ''),
        );

        if (isset($providerResponse['content'])) {
            return $providerResponse['content'];
        }

        return '';
    }
}
