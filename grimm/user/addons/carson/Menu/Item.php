<?php

namespace BoldMinded\Carson\Menu;

class Item
{
    public function __construct(
        private readonly string $label,
        private readonly string $prompt,
        private readonly string $icon,
        private readonly bool $isHidden = false,
        private readonly string $extraClass = '',
        private readonly array $dataAttributes = [],
    ){}

    public function __toString(): string
    {
        $itemClass = $this->extraClass . ($this->isHidden ? ' hidden' : '');
        $dataAttributes = '';

        foreach ($this->dataAttributes as $key => $value) {
            $dataAttributes .= sprintf('data-%s="%s"', $key, $value);
        }

        $template = '<a class="dropdown__link carson-prompt %s"
            data-work-text="Working..."
            data-target="{{target}}"
            data-type="assistant"
            data-prompt="%s"
            %s
            href="#"
            >
                <i class="fas fa-%s"></i> <span>%s</span>
            </a>';

        return sprintf($template, $itemClass, $this->prompt, $dataAttributes, $this->icon, $this->label);
    }
}
