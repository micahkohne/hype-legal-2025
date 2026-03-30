<?php

namespace BoldMinded\Carson\Menu;

class Heading
{
    public function __construct(
        private readonly string $heading
    ){}

    public function __toString(): string
    {
        return sprintf('<div class="field-instruct" style="padding-left: 3em;"><em>%s</em></div>', $this->heading);
    }
}
