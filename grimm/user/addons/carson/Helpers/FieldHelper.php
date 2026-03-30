<?php

namespace BoldMinded\Carson\Helpers;

class FieldHelper
{
    public function getFieldsAsOptions(): array
    {
        $options = [];

        foreach ($this->getFields() as $row) {
            $label = $row['field_label'];

            if (isset($row['group_name'])) {
                if (is_array($row['group_name'])) {
                    $groupName = implode(', ', $row['group_name']);
                } else {
                    $groupName = $row['group_name'];
                    $label = $row['group_name'] .' » '. $label;
                }

                if ($groupName !== '') {
                    $label = $groupName .' » '. $label;
                }
            }

            $options[$row['field_id']] = $label;
        }

        return $options;
    }

    public function getFields(): array
    {
        $channelFields = ee('Model')->get('ChannelField')
            ->with('ChannelFieldGroups')
            // ->filter('site_id', 'IN', [ee()->config->item('site_id'), 0])
            ->filter('field_type', 'IN', ['text', 'textarea', 'rte', 'wyvern'])
            ->all();

        $fields = [];

        foreach ($channelFields as $field) {
            $groupNames = [];

            foreach ($field->ChannelFieldGroups as $group) {
                $groupNames[] = $group->group_name;
            }

            /** @var ChannelField $field */
            $field = $field->toArray();
            $field['group_name'] = $groupNames;

            $fields[] = $field;
        }

        return $fields;
    }
}
