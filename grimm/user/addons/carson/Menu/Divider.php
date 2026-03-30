<?php

namespace BoldMinded\Carson\Menu;

class Divider
{
    public function __construct(
        private readonly bool $isHidden = false,
        private readonly string $extraClass = '',
    ){}

    public function __toString(): string
    {
        $itemClass = $this->extraClass . ($this->isHidden ? ' hidden' : '');

        return sprintf('<div class="dropdown__divider %s"></div>', $itemClass);
    }
}
