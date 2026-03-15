<?php

declare(strict_types=1);

/**
 * @ilCtrl_IsCalledBy ilSwitchUserConfigGUI: ilObjComponentSettingsGUI
 */
class ilSwitchUserConfigGUI extends ilPluginConfigGUI
{
    public function performCommand(string $cmd = ''): void
    {
        $this->configure();
    }

    public function configure(): void
    {
        $this->tpl()->setTitle('SwitchUser');
        $this->tpl()->setContent($this->renderInfoPage());
    }

    private function tpl(): ilGlobalTemplateInterface
    {
        global $DIC;
        return $DIC->ui()->mainTemplate();
    }

    private function openLink(): string
    {
        return ilUtil::appendUrlParameterString('goto.php', 'target=' . rawurlencode(ilSwitchUserPlugin::TARGET_OPEN));
    }

    private function renderInfoPage(): string
    {
        $url = htmlspecialchars($this->openLink(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return <<<HTML
<div class="ilFormHeader">SwitchUser 1.6.1</div>
<p>L'accès direct à la recherche utilisateur se fait via <code>goto.php?target=swus_open</code>.</p>
<p>Le bouton ci-dessous ouvre exactement ce même écran de recherche.</p>
<p><a class="btn btn-primary" href="{$url}">Ouvrir SwitchUser</a></p>
HTML;
    }
}
