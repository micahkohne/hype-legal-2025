<?php

namespace Solspace\Addons\FreeformNext\Library\Composer\Components\Fields;

use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\Interfaces\NoStorageInterface;
use Solspace\Addons\FreeformNext\Library\Composer\Components\Fields\Interfaces\RememberPostedValueInterface;
use Solspace\Addons\FreeformNext\Library\Exceptions\FreeformException;

class ConfirmationField extends TextField implements NoStorageInterface, RememberPostedValueInterface
{
    /** @var int */
    protected $targetFieldHash;

    /**
     * Return the field TYPE
     *
     * @return string
     */
    public function getType(): string
    {
        return self::TYPE_CONFIRMATION;
    }

    /**
     * @return int|null
     */
    public function getTargetFieldHash()
    {
        return $this->targetFieldHash;
    }

    /**
     * @return array
     */
    protected function validate()
    {
        $errors = parent::validate();

        try {
            $field = $this->getForm()->getLayout()->getFieldByHash($this->getTargetFieldHash());

            $value = $field->getValue();
            if ($field instanceof EmailField) {
                if ((is_countable($value) ? count($value) : 0) >= 1) {
                    $value = reset($value);
                } else {
                    $value = '';
                }
            }

            if ($value !== $this->getValue()) {
                $errors[] = $this->translate(
                    'This value must match the value for {targetFieldLabel}',
                    ['targetFieldLabel' => $field->getLabel()]
                );
            }
        } catch (FreeformException) {
        }

        return $errors;
    }

    /**
     * @inheritDoc
     */
    protected function getInputHtml()
    {
        $attributes = $this->getCustomAttributes();

        try {
            $field = $this->getForm()->getLayout()->getFieldByHash($this->getTargetFieldHash());

            $output = $field->getInputHtml();
            $output = str_replace('/>', '', $output);

            $output = $this->injectAttribute($output, 'name', $this->getHandle());
            $output = $this->injectAttribute($output, 'id', $this->getIdAttribute());
            $output = $this->injectAttribute($output, 'class', $attributes->getClass());
            $output = $this->injectAttribute($output, 'value', $this->getValue(), true);
            $output = $this->injectAttribute(
                $output,
                'placeholder',
                $this->getForm()->getTranslator()->translate(
                    $attributes->getPlaceholder() ?: $this->getPlaceholder()
                )
            );

            $output = str_replace(' required', '', $output);
            $output .= $this->getRequiredAttribute();
            $output .= $attributes->getInputAttributesAsString();

            $output .= ' />';

            return $output;
        } catch (FreeformException) {
            return parent::getInputHtml();
        }
    }

    /**
     * @param string $string
     * @return string
     */
    private function injectAttribute(string|array|null $string, string $name, mixed $value, bool $escapeValue = true): string|array|null
    {
        if (preg_match('/' . $name . '=[\'"][^\'"]*[\'"]/', $string)) {
            $string = preg_replace(
                '/' . $name . '=[\'"][^\'"]*[\'"]/',
                $this->getAttributeString($name, $value, $escapeValue),
                $string
            );
        } else {
            $string .= $this->getAttributeString($name, $value, $escapeValue);
        }

        return $string;
    }
}
