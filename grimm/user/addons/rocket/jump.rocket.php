<?php

use ExpressionEngine\Service\JumpMenu\AbstractJumpMenu;

class rocket_jump extends AbstractJumpMenu
{
    protected static $items = [
        [
            'commandTitle' => 'settings',
            'icon' => 'fa-rocket',
            'command' => 'rocket settings',
            'command_title' => 'Settings',
            'target' => '',
            'dynamic' => false,
        ],
        [
            'commandTitle' => 'purgeCache',
            'icon' => 'fa-rocket',
            'command' => 'purge rocket cache',
            'command_title' => 'Purge <b>cache</b>',
            'target' => 'purge_cache',
            'dynamic' => false,
        ],
    ];
}
