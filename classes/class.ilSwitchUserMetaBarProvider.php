<?php

declare(strict_types=1);

use ILIAS\GlobalScreen\Identification\IdentificationInterface;
use ILIAS\GlobalScreen\Scope\MetaBar\Provider\AbstractStaticMetaBarPluginProvider;

/**
 * Metabar provider for SwitchUser.
 *
 * Adds a native entry to the top header metabar as a <li>, next to the built-in icons.
 */
class ilSwitchUserMetaBarProvider extends AbstractStaticMetaBarPluginProvider
{
    private function getId(): IdentificationInterface
    {
        return $this->if->identifier('switchuser');
    }

    public function getAllIdentifications(): array
    {
        return [$this->getId()];
    }

    public function getMetaBarItems(): array
    {
        if (!$this->isVisibleForCurrentSession()) {
            return [];
        }

        $factory = $this->dic->ui()->factory();
        $mb = $this->globalScreen()->metaBar();

        $item = $mb->topLinkItem($this->getId())
            ->withAction(ilSwitchUserSecurity::searchUrl())
            ->withSymbol($factory->symbol()->icon()->custom('./Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SwitchUser/templates/images/switchuser_switch.svg', 'SwitchUser')->withSize('large'))
            ->withTitle(ilSwitchUserSecurity::txt('plugin_name'))
            ->withPosition(1)
            ->withAvailableCallable(function (): bool {
                return $this->isVisibleForCurrentSession();
            })
            ->withVisibilityCallable(function (): bool {
                return $this->isVisibleForCurrentSession();
            });

        return [$item];
    }

    private function isVisibleForCurrentSession(): bool
    {
        return ilSwitchUserSecurity::isAdministrativeUser()
            || ilSwitchUserSecurity::isImpersonationActive();
    }
}
