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

namespace Solspace\Addons\FreeformNext\Repositories;

use Solspace\Addons\FreeformNext\Model\FormModel;
use Solspace\Addons\FreeformNext\Model\SubmissionModel;

class FormRepository extends Repository
{
    /** @var FormModel[] */
    private static $cacheById;

    /** @var FormModel[] */
    private static $cacheByHandle;

    /**
     * @return FormRepository
     */
    public static function getInstance()
    {
        return parent::getInstance();
    }

    /**
     * @param int $id
     *
     * @return FormModel|null
     */
    public function getOrCreateForm(mixed $id = null)
    {
        $model = null;
        if ($id) {
            $model = $this->getFormById($id);
        }

        if (!$model) {
            return FormModel::create();
        }

        return $model;
    }

    /**
     * @param string $ids
     * @param string $handles
     *
     * @return FormModel[]
     */
    public function getAllForms(?string $ids = null, ?string $handles = null)
    {
        $query = ee('Model')
            ->get(FormModel::MODEL);

        if (null !== $ids) {
            $operator = 'IN';
            if (str_starts_with($ids, 'not ')) {
                $ids      = substr($ids, 4);
                $operator = 'NOT IN';
            }

            $ids = explode('|', $ids);

            $query->filter('id', $operator, $ids);
        }

        if (null !== $handles) {
            $operator = 'IN';
            if (str_starts_with($handles, 'not ')) {
                $handles  = substr($handles, 4);
                $operator = 'NOT IN';
            }

            $handles = explode('|', $handles);

            $query->filter('handle', $operator, $handles);
        }

        return $query
            ->all()
            ->asArray();
    }

    /**
     * @param int $id
     *
     * @return FormModel|null
     */
    public function getFormById($id)
    {
        if (!isset(self::$cacheById[$id])) {
            $form = ee('Model')
                ->get(FormModel::MODEL)
                ->filter('id', $id)
                ->first();

            self::$cacheById[$id] = $form;
            if ($form) {
                self::$cacheByHandle[$form->handle] = $form;
            }
        }

        return self::$cacheById[$id];
    }

    /**
     * @return FormModel[]
     */
    public function getFormByIdList(array $ids)
    {
        if (empty($ids)) {
            return [];
        }

        return ee('Model')
            ->get(FormModel::MODEL)
            ->filter('id', 'IN', $ids)
            ->all()
            ->asArray();
    }

    /**
     * @param string $idOrHandle
     *
     * @return FormModel|null
     */
    public function getFormByIdOrHandle($idOrHandle)
    {
        if (isset(self::$cacheById[$idOrHandle])) {
            return self::$cacheById[$idOrHandle];
        }

        if (isset(self::$cacheByHandle[$idOrHandle])) {
            return self::$cacheByHandle[$idOrHandle];
        }

        $target = is_numeric($idOrHandle) ? 'id' : 'handle';
        $form   = ee('Model')
            ->get(FormModel::MODEL)
            ->filter($target, $idOrHandle)
            ->first();

        if ($form) {
            self::$cacheById[$form->id]         = $form;
            self::$cacheByHandle[$form->handle] = $form;
        }

        return $form;
    }

    /**
     * @return array
     */
    public function getFormSubmissionCount(array $formIds): array
    {
        if (empty($formIds)) {
            return [];
        }

        $data = ee()->db
            ->select('formId, COUNT(id) as total')
            ->group_by('formId')
            ->where_in('formId', $formIds ?: [])
            ->get(SubmissionModel::TABLE)
            ->result_array();

        return array_column($data, 'total', 'formId');
    }
}
