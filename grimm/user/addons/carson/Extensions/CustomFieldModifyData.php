<?php

namespace BoldMinded\Carson\Extensions;

use ExpressionEngine\Service\Addon\Controllers\Extension\AbstractRoute;

class CustomFieldModifyData extends AbstractRoute
{
    public function process($ft, $method, $parameters)
    {
        if (ee()->extensions->last_call !== false) {
            $parameters = ee()->extensions->last_call;
        }

        $fieldName = $ft->field_name ?? null;

        if (is_null($fieldName)) {
            return $parameters;
        }

        $isCustomField = str_contains($ft->field_name, 'field_id_') && $method === '_init';
        // _init does not get called when a field is inside of Grid (or Bloqs) 🤔
        $isColumnField = str_contains($ft->field_name, 'col_id_') && $method === 'display_field';

        $supportedFieldTypes = ['Text', 'Textarea', 'Rte'];
        $userConfigSupportedFieldTypes = ee()->config->item('carson_fieldtypes') ?: [];

        if (!empty($userConfigSupportedFieldTypes)) {
            $supportedFieldTypes = array_merge($supportedFieldTypes, $userConfigSupportedFieldTypes);
        }

        $supportedFieldTypes = array_map(function($fieldType) {
            return $fieldType . '_ft';
        }, $supportedFieldTypes);

        if (
            REQ !== 'CP' ||
            (!$isCustomField && !$isColumnField) ||
            !in_array(get_class($ft), $supportedFieldTypes)
        ) {
            return $parameters;
        }

        if (!isset(ee()->session->cache['carsonFieldTypes'])) {
            ee()->session->cache['carsonFieldTypes'] = [];
        }

        $type = get_class($ft) === 'Text_ft' ? 'input' : 'textarea';
        $fieldName = $ft->field_name;

        // Fluid fields - Could also simplify the regex field_id_(\d+)\[fields\] and reference $ft->field_id, then
        // concatenate 'field_id_' . $ft->field_id, but that is making some assumptions.
        if (preg_match('/field_id_(\d+)\[fields\]\[(.*?)\]\[field_group_id_(\d+)\]\[(.*?)\]/', $fieldName, $fluidMatches)) {
            $fieldName = $fluidMatches[4];
        }

        // @todo add hidden config option to ignore specific fields?

        ee()->session->cache['carsonFieldTypes'][$type][] = $fieldName;

        ee()->javascript->set_global(
            'carson.fieldTypes.' . $type , json_encode(
                array_values(
                    array_unique(
                        ee()->session->cache['carsonFieldTypes'][$type]
                    )
                ), true
            )
        );

        return $parameters;
    }
}
