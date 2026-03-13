<?php

declare(strict_types=1);

/**
 * Minimal ILIAS 10-compatible SwitchUser plugin.
 */
class ilSwitchUserPlugin extends ilUserInterfaceHookPlugin
{
    public const PLUGIN_ID = 'swus';

    public function getPluginName(): string
    {
        return 'SwitchUser';
    }

    public function hasConfiguration(): bool
    {
        return true;
    }

    public function getConfigClass(): string
    {
        return 'ilSwitchUserConfigGUI';
    }
}
