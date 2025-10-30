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

use EllisLab\ExpressionEngine\Service\Model\Model;

/**
 * @property int        $id
 * @property int        $siteId
 * @property int        $submissionId
 * @property string     $reasonType
 * @property string     $reasonMessage
 * @property string     $reasonValue
 * @property \DateTime  $dateCreated
 * @property \DateTime  $dateUpdated
 */
class SpamReasonModel extends Model
{
    use TimestampableTrait;

    const MODEL = 'freeform_next:SpamReasonModel';
    const TABLE = 'freeform_next_spam_reasons';

    public const TYPE_GENERIC = 'generic';
    public const TYPE_HONEYPOT = 'honeypot';
    public const TYPE_JS_TEST = 'js_test';
    public const TYPE_CAPTCHA = 'captcha';

    protected static $_primary_key = 'id';
    protected static $_table_name  = self::TABLE;

    protected ?int $id = null;
    protected ?int $siteId = null;
    protected ?int $submissionId = null;
    protected ?string $reasonType = null;
    protected ?string $reasonMessage = null;
    protected ?string $reasonValue = null;

    /**
     * Creates a Spam Reason object
     *
     * @param int       $submissionId
     * @param string    $reasonType
     * @param string    $reasonMessage
     * @param string    $reasonValue
     *
     * @return SpamReasonModel
     */
    public static function create(int $submissionId, string $reasonType, string $reasonMessage, string $reasonValue): SpamReasonModel
    {
        return ee('Model')
            ->make(
                self::MODEL,
                [
                    'siteId'        => ee()->config->item('site_id'),
                    'submissionId'  => $submissionId,
                    'reasonType'    => $reasonType,
                    'reasonMessage' => $reasonMessage,
                    'reasonValue'   => $reasonValue,
                ]
            );
    }

    /**
     * Overriding the SAVE method
     */
    public function save(): void
    {
        $dateFormat = 'Y-m-d H:i:s';

        $insertData = [
            'siteId'        => $this->siteId,
            'submissionId'  => $this->submissionId,
            'reasonType'    => $this->reasonType,
            'reasonMessage' => $this->reasonMessage,
            'reasonValue'   => $this->reasonValue,
        ];

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

            if (!empty($this->dateUpdated)) {
                if (is_string($this->dateUpdated)) {
                    $dateUpdated = $this->dateUpdated;
                } else {
                    $dateUpdated = $this->dateUpdated->format($dateFormat);
                }
            } else {
                $dateUpdated = date($dateFormat);
            }

            $insertData['dateUpdated'] = $dateUpdated;

            ee()->db
                ->insert(
                    self::TABLE,
                    $insertData
                );

            $this->id = ee()->db->insert_id();
        }
    }

    /**
     * Event beforeInsert sets the $dateCreated and $dateUpdated properties
     */
    public function onBeforeInsert(): void
    {
        $this->set(
            [
                'dateCreated' => $this->getTimestampableDate(),
                'dateUpdated' => $this->getTimestampableDate(),
            ]
        );
    }

    /**
     * Event beforeUpdate sets the $dateUpdated property
     */
    public function onBeforeUpdate(): void
    {
        $this->set(['dateUpdated' => $this->getTimestampableDate()]);
    }

    public static function getReasons(): array
    {
        return [
            self::TYPE_GENERIC,
            self::TYPE_HONEYPOT,
            self::TYPE_JS_TEST,
            self::TYPE_CAPTCHA,
        ];
    }

    public function getType(): string
    {
        return $this->reasonType;
    }

    public function getMessage(): string
    {
        return $this->reasonMessage;
    }

    public function getValue(): ?string
    {
        return $this->reasonValue;
    }

    private function getTimestampableDate(): string
    {
        return date('Y-m-d H:i:s');
    }
}
