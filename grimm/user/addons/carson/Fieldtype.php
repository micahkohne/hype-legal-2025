<?php

abstract class Fieldtype extends \EE_Fieldtype
{
    public function __construct()
    {
        ee()->lang->loadfile('carson');

        parent::__construct();
    }

    public function accepts_content_type($name): bool
    {
        $acceptedTypes = [
            'channel',
        ];

        return in_array($name, $acceptedTypes);
    }

    public function replace_tag($data, $params = array(), $tagdata = false): string
    {
        return '';
    }

    #[NoReturn] protected function loadAssets(): void
    {
        if (!isset(ee()->session->cache['carsonHelperAssets'])) {
            $scripts[] = file_get_contents(PATH_THIRD .'carson/scripts/fieldtype.js');

            ee()->cp->add_to_foot('<script type="text/javascript">'. implode('', $scripts) .'</script>');

            $styles[] = file_get_contents(PATH_THIRD .'carson/styles/fieldtype.css');

            ee()->cp->add_to_head('<style>'. implode('', $styles) .'</style>');

            ee()->session->cache['carsonHelperAssets'] = true;
        }
    }
}
