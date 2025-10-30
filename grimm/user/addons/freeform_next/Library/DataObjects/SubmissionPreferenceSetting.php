<?php

namespace Solspace\Addons\FreeformNext\Library\DataObjects;

use JsonSerializable;
use Solspace\Addons\FreeformNext\Library\Composer\Components\AbstractField;

class SubmissionPreferenceSetting implements JsonSerializable
{
    private readonly bool $checked;

    /**
     * @param AbstractField $field
     * @param bool          $checked
     *
     * @return SubmissionPreferenceSetting
     */
    public static function createFromField(AbstractField $field, $checked): \Solspace\Addons\FreeformNext\Library\DataObjects\SubmissionPreferenceSetting
    {
        return new SubmissionPreferenceSetting(
            $field->getId(),
            $field->getHandle(),
            $field->getLabel(),
            $checked
        );
    }

    /**
     * @param array $data
     *
     * @return SubmissionPreferenceSetting
     */
    public static function createFromArray(array $data): \Solspace\Addons\FreeformNext\Library\DataObjects\SubmissionPreferenceSetting
    {
        return new SubmissionPreferenceSetting(
            $data['id'],
            $data['handle'],
            $data['label'],
            $data['checked']
        );
    }

    /**
     * SubmissionPreferenceSetting constructor.
     *
     * @param int    $id
     * @param string $handle
     * @param string $label
     * @param bool   $checked
     */
    public function __construct(private $id, private $handle, private $label, $checked)
    {
        $this->checked = (bool) $checked;
    }

    /**
     * @return int
     */
    public function getId()
    {
        return is_numeric($this->id) ? (int) $this->id : $this->id;
    }

    /**
     * @return string
     */
    public function getHandle()
    {
        return $this->handle;
    }

    /**
     * @return string
     */
    public function getLabel()
    {
        return $this->label;
    }

    /**
     * @return bool
     */
    public function isChecked(): bool
    {
        return $this->checked;
    }

    /**
     * Specify data which should be serialized to JSON
     */
    public function jsonSerialize(): array
    {
        return [
            'id'      => $this->getId(),
            'handle'  => $this->getHandle(),
            'label'   => $this->getLabel(),
            'checked' => $this->isChecked(),
        ];
    }
}
