<?php

namespace BoldMinded\Carson\Menu;

class Menu
{
    private array $groups;

    public function __construct(...$groups)
    {
        $this->groups = $groups;
    }

    public function __toString(): string
    {
        $template = '<div class="carson-omni-container" data-target="{{target}}">
            <a class="dropdown-toggle js-dropdown-toggle carson-dropdown-toggle carson-omni-icon" data-dropdown-pos="bottom-end"><i class="fas fa-wand-magic-sparkles"></i></a>
            <div class="dropdown dropdown--accent carson-dropdown" x-placement="bottom-end" style="position: absolute; min-width: 18em">
             %s
            </div>
        </div>';

        return sprintf($template, implode('', $this->groups));
    }

    public function addGroup(Group $group): Menu
    {
        $this->groups[] = $group;

        return $this;
    }
}
