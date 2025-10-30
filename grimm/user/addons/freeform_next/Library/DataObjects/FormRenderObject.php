<?php

namespace Solspace\Addons\FreeformNext\Library\DataObjects;

use Solspace\Addons\FreeformNext\Library\Composer\Components\Form;

class FormRenderObject
{
    /** @var string[] */
    private array $outputChunks;

    /**
     * FormRenderEvent constructor.
     *
     * @param Form $form
     */
    public function __construct(private readonly Form $form)
    {
        $this->outputChunks = [];
    }

    /**
     * @return Form
     */
    public function getForm(): Form
    {
        return $this->form;
    }

    /**
     * @return string
     */
    public function getCompiledOutput(): string
    {
        return implode("\n", $this->outputChunks);
    }

    /**
     * @param string $value
     *
     * @return FormRenderObject
     */
    public function appendToOutput($value): static
    {
        $this->outputChunks[] = $value;

        return $this;
    }

    /**
     * @param string $value
     *
     * @return FormRenderObject
     */
    public function appendJsToOutput($value): static
    {
        $this->outputChunks[] = "<script>$value</script>";

        return $this;
    }

    /**
     * @param string $value
     *
     * @return FormRenderObject
     */
    public function appendCssToOutput($value): static
    {
        $this->outputChunks[] = "<style>$value</style>";

        return $this;
    }
}
