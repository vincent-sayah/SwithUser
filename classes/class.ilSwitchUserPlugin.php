<?php

declare(strict_types=1);

/**
 * Hardened SwitchUser plugin for ILIAS 10.
 */
class ilSwitchUserPlugin extends ilUserInterfaceHookPlugin
{
    public const PLUGIN_ID = 'swus';
    public const TARGET_OPEN = 'swus_open';
    public const TARGET_ACTION = 'swus_action';

    protected function init(): void
    {
        global $DIC;

        if (isset($DIC) && method_exists($DIC, 'isDependencyAvailable') && $DIC->isDependencyAvailable('globalScreen')) {
            $this->provider_collection->setMetaBarProvider(new ilSwitchUserMetaBarProvider($DIC, $this));
        }
    }

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
