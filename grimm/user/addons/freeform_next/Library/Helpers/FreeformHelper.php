<?php

namespace Solspace\Addons\FreeformNext\Library\Helpers;

use Exception;
use Solspace\Addons\FreeformNext\Controllers\FieldController;
use Solspace\Addons\FreeformNext\Controllers\FormController;
use Solspace\Addons\FreeformNext\Controllers\StatusController;
use Solspace\Addons\FreeformNext\Model\FieldModel;
use Solspace\Addons\FreeformNext\Model\FormModel;
use Solspace\Addons\FreeformNext\Repositories\FieldRepository;
use Solspace\Addons\FreeformNext\Repositories\FormRepository;
use Solspace\Addons\FreeformNext\Utilities\ControlPanel\Navigation\NavigationLink;

class FreeformHelper
{
    /**
     * @param string $name
     *
     * @return mixed
     */
    public static function get($name)
    {
        $args = func_get_args();

        $return = null;

        if ($name === 'version') {
            if (file_exists(PATH_THIRD . '/freeform_next/Library/Pro')) {
                $return = FREEFORM_PRO;
            } elseif (file_exists(PATH_THIRD . '/freeform_next/ft.freeform_next.php')) {
                $return = FREEFORM_LITE;
            } else {
                $return = FREEFORM_EXPRESS;
            }
        } elseif ($name === 'validate') {
            $version = FreeformHelper::get('version');

            if ($version === FREEFORM_EXPRESS) {
                $item = $args[1];

                if ($item instanceof FormModel) {
                    $count = (int) ee()->db
                        ->select('COUNT(*) as total')
                        ->get('freeform_next_forms')
                        ->row()
                        ->total;

                    if (!$item->id && $count > 0) {
                        throw new Exception('Form limit reached');
                    }
                } elseif ($item instanceof FieldModel) {
                    $count = (int) ee()->db
                        ->select('COUNT(*) as total')
                        ->get('freeform_next_fields')
                        ->row()
                        ->total;

                    if (!$item->id && $count >= 15) {
                        throw new Exception('Maximum limit of 15 fields reached.');
                    }
                }
            }
        } elseif ($name === 'props') {
            $version = FreeformHelper::get('version');

            $return = $version === FREEFORM_EXPRESS;
        } elseif ($name === 'right_links') {
            $version  = FreeformHelper::get('version');
            $item     = $args[1];
            $link     = $title = '';
            $showLink = false;

            if ($item instanceof FormController) {
                $count = count(FormRepository::getInstance()->getAllForms());

                $showLink = $version !== FREEFORM_EXPRESS || $count === 0;
                $link     = 'forms/new';
                $title    = 'New Form';
            } elseif ($item instanceof FieldController) {
                $count = count(FieldRepository::getInstance()->getAllFields());

                $showLink = $version !== FREEFORM_EXPRESS || $count < 15;
                $link     = 'fields/new';
                $title    = 'New Field';
            } elseif ($item instanceof StatusController) {
                $showLink = $version !== FREEFORM_EXPRESS;
                $link     = 'settings/statuses/new';
                $title    = 'New Status';
            }

            if ($showLink) {
                $return = [
                    [
                        'title' => lang($title),
                        'link'  => UrlHelper::getLink($link),
                    ],
                ];
            } else {
                $return = [];
            }
        } elseif ($name === 'name') {
            $version = FreeformHelper::get('version');

            if ($version === FREEFORM_PRO) {
                $return = 'Freeform';
            } elseif ($version === FREEFORM_LITE) {
                $return = 'Freeform  Lite';
            } else {
                $return = 'Freeform Express';
            }
        } elseif ($name === 'columns') {
            $version = FreeformHelper::get('version');
            $columns = $args[1];

            if ($version === FREEFORM_EXPRESS) {
                $columns = array_slice($columns, 0, (is_countable($columns) ? count($columns) : 0) - 2, true);
            }

            $return = $columns;
        } elseif ($name === 'column_count') {
            $version = FreeformHelper::get('version');
            $columns = $args[1];

            if ($version === FREEFORM_EXPRESS) {
                $newColumns = [];

                foreach ($columns as $column) {
                    $data = array_slice($column, 0, (is_countable($column) ? count($column) : 0) - 2, true);
                    $data[1]['content'] = strip_tags($data[1]['content'], '<span>');

                    $newColumns[] = $data;
                }

                $return = $newColumns;
            } else {
                $return = $columns;
            }
        } elseif ($name === 'navigation') {
            $version = FreeformHelper::get('version');

            /** @var NavigationLink $item */
            $item = $args[1];

            $link = '';
            $showLink = false;

            if ($item->getMethod() === 'fields') {
                $count = count(FieldRepository::getInstance()->getAllFields());

                $link = 'fields/new';
                $showLink = $version !== FREEFORM_EXPRESS || ($count < 15);
            } elseif ($item->getMethod() === 'forms') {
                $count = count(FormRepository::getInstance()->getAllForms());

                $link = 'forms/new';
                $showLink = $version !== FREEFORM_EXPRESS || ($count < 1);
            }

            if ($showLink) {
                $item->setButtonLink(new NavigationLink('New', $link));
            }
        }

        return $return;
    }
}
