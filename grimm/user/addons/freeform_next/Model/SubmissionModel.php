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

namespace Solspace\Addons\FreeformNext\Model;

use ExpressionEngine\Service\Model\Model;
use Solspace\Addons\FreeformNext\Library\Composer\Components\AbstractField;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Form;
use Solspace\Addons\FreeformNext\Library\Exceptions\FreeformException;
use Solspace\Addons\FreeformNext\Library\Helpers\CryptoHelper;
use Solspace\Addons\FreeformNext\Library\Helpers\FreeformHelper;
use Solspace\Addons\FreeformNext\Library\Helpers\HashHelper;
use Solspace\Addons\FreeformNext\Library\Helpers\StringHelper;
use Solspace\Addons\FreeformNext\Library\Helpers\TemplateHelper;
use Solspace\Addons\FreeformNext\Repositories\FormRepository;
use Solspace\Addons\FreeformNext\Repositories\SubmissionRepository;

/**
 * @property int    $id
 * @property int    $siteId
 * @property string $token
 * @property int    $statusId
 * @property string $statusName
 * @property string $statusHandle
 * @property string $statusColor
 * @property int    $formId
 * @property string $title
 * @property int    $isSpam
 * @property string $spamReasonType
 * @property string $spamReasonMessage
 * @property string $spamReasonValue
 */
class SubmissionModel extends Model
{
    use TimestampableTrait;

    const MODEL = 'freeform_next:SubmissionModel';
    const TABLE = 'freeform_next_submissions';

    const FIELD_COLUMN_PREFIX = 'field_';

    /** @var AbstractField[] */
    private static $fieldMetadata = [];

    /** @var array */
    private static $handleToFieldIdMap = [];

    protected static $_primary_key = 'id';
    protected static $_table_name  = self::TABLE;

    protected $id;
    protected $siteId;
    protected $token;
    protected $statusId;
    protected $statusName;
    protected $statusHandle;
    protected $statusColor;
    protected $formId;
    protected $title;
    protected $isSpam;
    protected $spamReasonType;
    protected $spamReasonMessage;
    protected $spamReasonValue;

    /** @var array */
    private $fieldValues = [];

    /**
     * Get the submission table field column name
     *
     * @param int $fieldId
     *
     * @return string
     */
    public static function getFieldColumnName($fieldId): string
    {
        return self::FIELD_COLUMN_PREFIX . $fieldId;
    }

    /**
     * Creates a Field object with default settings
     *
     * @param Form  $form
     * @param array $fetchedValues
     *
     * @return SubmissionModel
     */
    public static function create(Form $form, array $fetchedValues)
    {
        $token = $form->getAssociatedSubmissionToken();

        $submission = null;
        if ($token && FreeformHelper::get('version') === FREEFORM_PRO) {
            $submission = SubmissionRepository::getInstance()->getSubmissionByToken($form, $token);
        }

        if (!$submission) {
            /** @var SubmissionModel $submission */
            $submission = ee('Model')->make(
                self::MODEL,
                [
                    'siteId'   => ee()->config->item('site_id'),
                    'formId'   => $form->getId(),
                    'isSpam'   => $form->isMarkedAsSpam(),
                    'statusId' => $form->getDefaultStatus(),
                    'token'    => CryptoHelper::getUniqueToken(100),
                ]
            );
        }

        foreach ($fetchedValues as $key => $value) {
            if (property_exists(self::class, $key)) {
                $submission->{$key} = $value;
            } else if (preg_match('/^' . SubmissionModel::FIELD_COLUMN_PREFIX . '(\d+)$/', $key, $matches)) {
                $fieldId = (int) $matches[1];
                $submission->setFieldColumnValue($fieldId, $value);
            } else {
                $submission->setFieldValue($key, $value);
            }
        }

        $submission->setTitle($form, $fetchedValues);

        return $submission;
    }

    /**
     * Creates a Field object with default settings
     *
     * @param Form  $form
     * @param array $fetchedValues
     *
     * @return SubmissionModel
     */
    public static function createFromDatabase(Form $form, array $fetchedValues)
    {
        /** @var SubmissionModel $submission */
        $submission = ee('Model')->make(
            self::MODEL,
            [
                'id'                => $fetchedValues['id'],
                'siteId'            => $fetchedValues['siteId'],
                'token'             => $fetchedValues['token'],
                'formId'            => $fetchedValues['formId'],
                'isSpam'            => $fetchedValues['isSpam'],
                'statusId'          => $fetchedValues['statusId'],
                'title'             => $fetchedValues['title'],
                'dateCreated'       => $fetchedValues['dateCreated'],
                'dateUpdated'       => $fetchedValues['dateUpdated'],
                'statusName'        => $fetchedValues['statusName'],
                'statusHandle'      => $fetchedValues['statusHandle'],
                'statusColor'       => $fetchedValues['statusColor'],
                'spamReasonType'    => $fetchedValues['spamReasonType'] ?? null,
                'spamReasonMessage' => $fetchedValues['spamReasonMessage'] ?? null,
                'spamReasonValue'   => $fetchedValues['spamReasonValue'] ?? null,
            ]
        );

        $submission->_new = false;

        foreach ($fetchedValues as $key => $value) {
            if (preg_match('/^' . SubmissionModel::FIELD_COLUMN_PREFIX . '(\d+)$/', $key, $matches)) {
                $fieldId = (int) $matches[1];
                $submission->setFieldColumnValue($fieldId, $value);
            }
        }

        return $submission;
    }

    /**
     * @param int $formId
     *
     * @return AbstractField
     */
    private static function getFieldMetadataByFormId($formId)
    {
        if (!isset(self::$fieldMetadata[$formId])) {
            $form   = FormRepository::getInstance()->getFormById($formId);
            $fields = $form->getComposer()->getForm()->getLayout()->getFieldsByHandle();

            $metadataArray      = [];
            $handleToFieldIdMap = [];
            foreach ($fields as $field) {
                $id     = $field->getId();
                $handle = $field->getHandle();

                $metadataArray[$id]          = $field;
                $handleToFieldIdMap[$handle] = $id;
            }

            self::$fieldMetadata[$formId]      = $metadataArray;
            self::$handleToFieldIdMap[$formId] = $handleToFieldIdMap;
        }

        return self::$fieldMetadata[$formId];
    }

    /**
     * @param int $formId
     * @param int $fieldId
     *
     * @return AbstractField
     */
    private static function getFieldMetadataById($formId, $fieldId)
    {
        $metadata = self::getFieldMetadataByFormId($formId);

        return $metadata[$fieldId] ?? null;
    }

    /**
     * @param String $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->fieldValues[$key] ?? parent::__get($key);
    }

    /**
     * @return null|FormModel
     */
    public function getForm()
    {
        return FormRepository::getInstance()->getFormById($this->formId);
    }

    /**
     * @return string
     */
    public function getHash(): string
    {
        return HashHelper::hash($this->id);
    }

    /**
     * @param string $handle
     *
     * @return string
     * @throws FreeformException
     */
    public function getFieldValue($handle)
    {
        if (!array_key_exists($handle, $this->fieldValues)) {
            throw new FreeformException(sprintf('Field "%s" not in found in form', $handle));
        }

        return $this->fieldValues[$handle];
    }

    /**
     * @param string $handle
     *
     * @return string
     * @throws FreeformException
     */
    public function getFieldValueAsString($handle)
    {
        if (!array_key_exists($handle, $this->fieldValues)) {
            throw new FreeformException(sprintf('Field "%s" not in found in form', $handle));
        }

        $value = $this->fieldValues[$handle];

        if (is_array($value)) {
            $value = StringHelper::implodeRecursively(', ', $value);
        }

        return $value;
    }

    /**
     * @param string $handle
     * @param mixed  $value
     *
     * @return $this
     */
    public function setFieldValue($handle, $value): static
    {
        $this->fieldValues[$handle] = $value;

        return $this;
    }

    /**
     * Overriding the SAVE method
     */
    public function save(): void
    {
        $dateFormat = 'Y-m-d H:i:s';
        $insertData = [
            'siteId'      => $this->siteId,
            'formId'      => $this->formId,
            'isSpam'      => $this->isSpam,
            'statusId'    => $this->statusId,
            'title'       => $this->title,
            'token'       => $this->token,
            'dateUpdated' => date($dateFormat, time()),
        ];

        $insertData = array_merge($insertData, $this->assembleInsertData());

        if ($this->id) {
            ee()->db
                ->where(['id' => $this->id])
                ->update(
                    self::TABLE,
                    $insertData
                );
        } else {
            if (!empty($this->dateCreated)) {
                if (is_string($this->dateCreated)) {
                    $dateCreated = $this->dateCreated;
                } else {
                    $dateCreated = $this->dateCreated->format($dateFormat);
                }
            } else {
                $dateCreated = date($dateFormat);
            }

            $insertData['dateCreated'] = $dateCreated;

            ee()->db
                ->insert(
                    self::TABLE,
                    $insertData
                );

            $this->id = ee()->db->insert_id();
        }
    }

    /**
     * @return bool
     */
    public function isTitleBlank(): bool
    {
        if (
            ctype_space($this->title) ||
            empty($this->title)
        ) {
            return true;
        }

        return false;
    }

    /**
     * @param Form $form
     * @param      $savableFields
     *
     * @return $this
     */
    public function setTitle(Form $form, ?array $savableFields): static
    {
        $this->title = '';

        if (!$form->isSubmissionTitleFormatBlank()) {
            $this->title = TemplateHelper::renderStringWithForm($form->getSubmissionTitleFormat(), $form);
            $this->title = TemplateHelper::renderString($this->title, $savableFields);

            if ($this->isTitleBlank()) {
                $this->title = TemplateHelper::renderString($form->getSubmissionTitleFormat(), $savableFields);
            }
        }

        return $this;
    }

    /**
     * @param int   $fieldId
     * @param mixed $value
     *
     * @return $this
     */
    private function setFieldColumnValue(int $fieldId, $value): static
    {
        $field = self::getFieldMetadataById($this->formId, $fieldId);

        if (!$field) {
            return $this;
        }

        if ($field->isArrayValue()) {
            $value = is_null($value) ? '' : $value;
            $value = json_decode($value, true);
        }

        $this->fieldValues[$field->getHandle()] = $value;

        return $this;
    }

    /**
     * @return array
     */
    private function assembleInsertData(): array
    {
        if (!isset(self::$handleToFieldIdMap[$this->formId])) {
            self::getFieldMetadataByFormId($this->formId);
        }

        $insertData = [];
        foreach ($this->fieldValues as $key => $value) {
            $id         = self::$handleToFieldIdMap[$this->formId][$key];
            $columnName = self::getFieldColumnName($id);

            $field = self::getFieldMetadataById($this->formId, $id);

            if ($field->isArrayValue()) {
                $value = json_encode($value);
            }

            $insertData[$columnName] = $value;
        }

        return $insertData;
    }
}
