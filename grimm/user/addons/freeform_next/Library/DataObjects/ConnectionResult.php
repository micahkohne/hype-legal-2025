<?php

namespace Solspace\Addons\FreeformNext\Library\DataObjects;

class ConnectionResult
{
    private array $formErrors;

    private array $fieldErrors;

    /**
     * ConnectionResult constructor.
     */
    public function __construct()
    {
        $this->formErrors  = [];
        $this->fieldErrors = [];
    }

    /**
     * @return bool
     */
    public function isSuccessful()
    {
        $isEmpty = empty($this->formErrors) && empty($this->fieldErrors);

        return $isEmpty;
    }

    /**
     * @return string
     */
    public function getAllErrorJson()
    {
        $conjoinedErrors = [
            'formErrors' => $this->getFormErrors(),
            'fieldErrors' => $this->getFieldErrors(),
        ];

        return json_encode($conjoinedErrors);
    }

    /**
     * @return array
     */
    public function getFormErrors(): array
    {
        return $this->formErrors;
    }

    /**
     * @return array
     */
    public function getFieldErrors(): array
    {
        return $this->fieldErrors;
    }

    /**
     * @param string $message
     *
     * @return $this
     */
    public function addFormError($message): static
    {
        $this->formErrors[] = $message;

        return $this;
    }

    /**
     * @param array $errors
     *
     * @return $this
     */
    public function addFormErrors(array $errors): static
    {
        foreach ($errors as $error) {
            $this->formErrors[] = $error;
        }

        return $this;
    }

    /**
     * @param string $fieldName
     * @param string $message
     *
     * @return ConnectionResult
     */
    public function addFieldError($fieldName, $message): static
    {
        if (!isset($this->fieldErrors[$fieldName])) {
            $this->fieldErrors[$fieldName] = [];
        }

        $this->fieldErrors[$fieldName][] = $message;

        return $this;
    }

    /**
     * @param array $errors
     *
     * @return ConnectionResult
     */
    public function addFieldErrors(array $errors): static
    {
        foreach ($errors as $key => $message) {
            $this->addFieldError($key, $message);
        }

        return $this;
    }
}
