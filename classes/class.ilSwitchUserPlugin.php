<?php

declare(strict_types=1);

/**
 * SwitchUser plugin for ILIAS 10.
 */
class ilSwitchUserPlugin extends ilUserInterfaceHookPlugin
{
    public const PLUGIN_ID = 'swus';
    public const TARGET_TAKEOVER = 'swus';
    public const TARGET_OPEN = 'swus_open';

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
