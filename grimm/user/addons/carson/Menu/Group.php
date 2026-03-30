<?php

namespace BoldMinded\Carson\Menu;

class Group
{
    private array $items;

    public function __construct(...$items)
    {
        $this->items = $items;
    }

    public function __toString(): string
    {
        return implode('', $this->items);
    }

    public function addItem(Item|Divider|Heading $item): Group
    {
        $this->items[] = $item;

        return $this;
    }
}
