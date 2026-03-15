<?php

declare(strict_types=1);

use ILIAS\GlobalScreen\Scope\MainMenu\Provider\AbstractStaticMainMenuProvider;

/**
 * Disabled main menu provider kept only for backward compatibility.
 *
 * The previous implementation was evaluated too early during the bootstrap
 * sequence and could break the public login page. The actual SwitchUser entry
 * is injected by the UI hook inside the administration area.
 */
class ilSwitchUserMainBarProvider extends AbstractStaticMainMenuProvider
{
    public function getStaticTopItems(): array
    {
        return [];
    }

    public function getStaticSubItems(): array
    {
        return [];
    }
}
