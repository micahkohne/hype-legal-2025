<?php

namespace Solspace\Addons\FreeformNext\Services;

use SimpleXMLElement;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\FileUploadField;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\Interfaces\MultipleValueInterface;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\TextareaField;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Form;
use Solspace\Addons\FreeformNext\Library\DataExport\ExportDataCSV;
use Solspace\Addons\FreeformNext\Library\Exceptions\FreeformException;
use Solspace\Addons\FreeformNext\Library\Pro\Fields\TableField;
use Solspace\Addons\FreeformNext\Model\SubmissionModel;
use Solspace\Addons\FreeformNext\Repositories\SettingsRepository;

class ExportProfilesService
{
    public function exportCsv(Form $form, array $labels, array $data): void
    {
        $data = $this->normalizeArrayData($form, $data);

        $csvData = $data;

        array_unshift($csvData, array_values($labels));

        $fileName = sprintf('%s submissions %s.csv', $form->getName(), date('Y-m-d H:i', time()));

        $export = new ExportDataCSV('browser', $fileName);
        $export->initialize();

        foreach ($csvData as $csv) {
            $export->addRow($csv);
        }

        $export->finalize();
        exit();
    }

    public function exportJson(Form $form, array $data): void
    {
        $data = $this->normalizeArrayData($form, $data, false);

        $export = [];
        foreach ($data as $itemList) {
            $sub = [];
            foreach ($itemList as $id => $value) {
                $label = $this->getHandleFromIdentificator($form, $id);

                $sub[$label] = $value;
            }

            $export[] = $sub;
        }

        $fileName = sprintf('%s submissions %s.json', $form->getName(), date('Y-m-d H:i', time()));

        $output = json_encode($export, JSON_PRETTY_PRINT);

        $this->outputFile($output, $fileName, 'application/octet-stream');
    }

    public function exportText(Form $form, array $data): void
    {
        $data = $this->normalizeArrayData($form, $data);

        $output = '';
        foreach ($data as $itemList) {
            foreach ($itemList as $id => $value) {
                $label = $this->getHandleFromIdentificator($form, $id);

                $output .= $label . ': ' . $value . "\n";
            }

            $output .= "\n";
        }

        $fileName = sprintf('%s submissions %s.txt', $form->getName(), date('Y-m-d H:i', time()));

        $this->outputFile($output, $fileName, 'text/plain');
    }

    public function exportXml(Form $form, array $data): void
    {
        $data = $this->normalizeArrayData($form, $data);

        $xml = new SimpleXMLElement('<root/>');

        foreach ($data as $itemList) {
            $submission = $xml->addChild('submission');

            foreach ($itemList as $id => $value) {
                $label = $this->getHandleFromIdentificator($form, $id);

                if (is_null($value)) {
                    $value = '';
                }
                $node = $submission->addChild($label, htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML401, 'UTF-8'));
                $node->addAttribute('label', $this->getLabelFromIdentificator($form, $id));
            }
        }

        $fileName = sprintf('%s submissions %s.xml', $form->getName(), date('Y-m-d H:i', time()));

        $this->outputFile($xml->asXML(), $fileName, 'text/xml');
    }

    /**
     * @param string $id
     * @return string
     */
    private function getLabelFromIdentificator(Form $form, $id)
    {
        static $cache;

        if (null === $cache) {
            $cache = [];
        }

        if (!isset($cache[$id])) {
            $label = $id;
            if (preg_match('/^(?:field_)?(\d+)$/', $label, $matches)) {
                $fieldId = $matches[1];
                try {
                    $field = $form->getLayout()->getFieldById($fieldId);
                    $label = $field->getLabel();
                } catch (FreeformException) {
                }
            } else {
                $label = match ($id) {
                    'id' => 'ID',
                    'dateCreated' => 'Date Created',
                    default => ucfirst($label),
                };
            }

            $cache[$id] = $label;
        }

        return $cache[$id];
    }

    /**
     * @param string $id
     * @return string
     */
    private function getHandleFromIdentificator(Form $form, $id)
    {
        static $cache;

        if (null === $cache) {
            $cache = [];
        }

        if (!isset($cache[$id])) {
            $label = $id;
            if (preg_match('/^field_(\d+)$/', $label, $matches)) {
                $fieldId = $matches[1];
                try {
                    $field = $form->getLayout()->getFieldById($fieldId);

                    $label = $field->getHandle();

                    if ($field instanceof TableField) {
                        $tableColumns = $field->getLayout();

                        foreach ($tableColumns as $tableColumn) {
                            $label = $tableColumn['label'];
                        }
                    }

                } catch (FreeformException) {
                }
            }

            $cache[$id] = $label;
        }

        return $cache[$id];
    }

    /**
     * @return array
     */
    private function normalizeArrayData(Form $form, array $data, bool $flattenArrays = true)
    {
        $isRemoveNewlines = (bool) SettingsRepository::getInstance()->getOrCreate()->removeNewlines;

        $tableRowsData = null;
        $tableFieldIds = [];

        foreach ($data as $index => $item) {
            foreach ($item as $fieldId => $value) {
                if (!preg_match('/^' . SubmissionModel::FIELD_COLUMN_PREFIX . '(\d+)$/', $fieldId, $matches)) {
                    continue;
                }

                try {
                    $field = $form->getLayout()->getFieldById($matches[1]);

                    if ($field instanceof FileUploadField) {
                        $value = (array) json_decode($value ?: '[]', true);
                        $combo = [];

                        foreach ($value as $assetId) {
                            /** @var File $asset */
                            $asset = ee('Model')
                                ->get('File')
                                ->filter('file_id', (int) $assetId)
                                ->first();

                            if ($asset) {
                                $assetValue = $asset->file_name;
                                if ($asset->getAbsoluteURL()) {
                                    $assetValue = $asset->getAbsoluteURL();
                                }

                                $combo[] = $assetValue;
                            }
                        }

                        $data[$index][$fieldId] = implode(', ', $combo);

                        continue;
                    }

                    if ($field instanceof TableField) {
                        $rowsValues = json_decode($value ?: '[]', true);
                        $rowsValuesFormatted = [];

                        if ($rowsValues) {
                            $tableRowsData[$index][$fieldId] = $rowsValues;

                            if (!in_array($fieldId, $tableFieldIds)) {
                                $tableFieldIds[] = $fieldId;
                            }

                            if ($flattenArrays && is_array($rowsValues)) {

                                foreach ($rowsValues as $rowsValue) {
                                    $rowsValuesFormatted[] = implode(',', $rowsValue);
                                }

                                $rowsValues = implode('|', $rowsValuesFormatted);

                            }

                            $data[$index][$fieldId] = $rowsValues;
                        }

                        continue;

                    }

                    if ($field instanceof MultipleValueInterface) {
                        $value = json_decode($value ?: '[]', true);
                        if ($flattenArrays && is_array($value)) {
                            $value = implode(', ', $value);
                        }

                        $data[$index][$fieldId] = $value;
                    }

                    if ($isRemoveNewlines && $field instanceof TextareaField) {
                        $data[$index][$fieldId] = trim(preg_replace('/\s+/', ' ', $value));
                    }
                } catch (FreeformException) {
                    continue;
                }
            }
        }

        if ($tableRowsData) {
            $data = $this->populateDataWithTableDate($data, $tableRowsData, $tableFieldIds, $form);
        }

        return $data;
    }

    /**
     * @param string $content
     */
    private function outputFile($content, string $fileName, string $contentType): void
    {
        header('Content-Description: File Transfer');
        header('Content-Type: ' . $contentType);
        header('Content-Disposition: attachment; filename=' . $fileName);
        header('Content-Transfer-Encoding: binary');
        header('Expires: 0');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        header('Content-Length: ' . strlen($content));

        echo $content;

        exit();
    }


    /**
     * @return mixed[]
     */
    private function populateDataWithTableDate(array $data, array $tableRowsData, array $tableFieldIds, Form $form): array
    {
        $newData = [];

        $artificialRowsCount = $this->getArtificialRowsCount($tableRowsData);

        foreach ($data as $submissionId => $rowValues) {

        	if(! isset($tableRowsData[$submissionId]))
			{
				$newRow = $rowValues;
				$newData[] = $newRow;
				continue;
			}

            $submissionTableData = $tableRowsData[$submissionId];

            for ($i = 1; $i <= $artificialRowsCount[$submissionId]; $i++) {

                $newRow = $rowValues;

                if ($i > 1) {
                    foreach ($newRow as $newFieldId => $newFieldValue) {
                        $newRow[$newFieldId] = null;
                    }
                }

                foreach ($tableFieldIds as $tableFieldId) {


                    if (array_key_exists($tableFieldId, $submissionTableData)) {

                        $submissionsFieldData = $submissionTableData[$tableFieldId];

                        $tableFieldRowValue = 'no value';

                        if (array_key_exists($i-1, $submissionsFieldData)) {
                            $tableFieldRowValue = $submissionTableData[$tableFieldId][$i-1];

                        } else {
                            preg_match('/^' . SubmissionModel::FIELD_COLUMN_PREFIX . '(\d+)$/', $tableFieldId, $matches);

                            if (array_key_exists(1, $matches)) {
                                $field = $form->getLayout()->getFieldById($matches[1]);

                                if ($field instanceof TableField) {
                                    $tableColumns = $field->getLayout();

                                    $emptyFields = [];

                                    foreach ($tableColumns as $tableColumn) {
                                        $emptyFields[] = '';
                                    }

                                    $tableFieldRowValue = $emptyFields;
                                }
                            }
                        }

                        $thisKey = null;

                        $keyCounter = 0;
                        foreach ($newRow as $newRowFieldId => $newRowFieldValue) {
                            if ($newRowFieldId === $tableFieldId) {
                                $thisKey = $keyCounter;
                            }
                            $keyCounter++;
                        }

                        unset($newRow[$tableFieldId]);
                        array_splice($newRow, $thisKey, 0, $tableFieldRowValue);
                    }
                }

                $newData[] = $newRow;
            }
        }

        return $newData;
    }

    /**
     * @return int[]
     */
    private function getArtificialRowsCount(array $tableRowsData): array
    {
        $artificialRowsCount = [];

        foreach ($tableRowsData as $submissionId => $submissionTableFields) {
            foreach ($submissionTableFields as $submissionTableFieldId => $submissionTableFieldValues) {
                if (!array_key_exists($submissionId, $artificialRowsCount)) {
                    $artificialRowsCount[$submissionId] = is_countable($submissionTableFieldValues) ? count($submissionTableFieldValues) : 0;
                } elseif ($artificialRowsCount[$submissionId] < (is_countable($submissionTableFieldValues) ? count($submissionTableFieldValues) : 0)) {
                    $artificialRowsCount[$submissionId] = is_countable($submissionTableFieldValues) ? count($submissionTableFieldValues) : 0;
                }
            }
        }

        return $artificialRowsCount;
    }
}
