<?php

/**
 * @ilCtrl_IsCalledBy ilSwitchUserConfigGUI: ilObjComponentSettingsGUI
 */
class ilSwitchUserConfigGUI extends ilPluginConfigGUI
{
    public function performCommand(string $cmd): void
    {
        switch ($cmd) {
            case 'search':
                $this->search();
                return;

            case 'show':
            default:
                $this->show();
                return;
        }
    }

    public function show(): void
    {
        $this->guardAdmin();
        $this->tpl()->setTitle($this->txt('admin_menu_title'));
        if (method_exists($this->tpl(), 'setDescription')) {
            $this->tpl()->setDescription($this->txt('admin_menu_hint'));
        }
        $this->tpl()->setContent(
            $this->searchPage()->render(
                $this->ctrl()->getFormAction($this, 'search'),
                ilSwitchUserSecurity::actionUrl(),
                ilSwitchUserSecurity::issueCsrfToken(),
                '',
                [],
                true,
                null
            )
        );
    }

    public function search(): void
    {
        $this->guardAdmin();

        if (!$this->isPost() || !$this->hasValidCsrf()) {
            $this->tpl()->setOnScreenMessage('failure', $this->txt('msg_invalid_request'), true);
            $this->ctrl()->redirect($this, 'show');
        }

        $term = trim((string) ($_POST['query'] ?? ''));
        $rows = $this->searchPage()->findUsers($term);

        $this->tpl()->setTitle($this->txt('admin_menu_title'));
        if (method_exists($this->tpl(), 'setDescription')) {
            $this->tpl()->setDescription($this->txt('admin_menu_hint'));
        }
        $this->tpl()->setContent(
            $this->searchPage()->render(
                $this->ctrl()->getFormAction($this, 'search'),
                ilSwitchUserSecurity::actionUrl(),
                ilSwitchUserSecurity::issueCsrfToken(),
                $term,
                $rows,
                true,
                null
            )
        );
    }

    private function guardAdmin(): void
    {
        if (ilSwitchUserSecurity::isAdministrativeUser()) {
            return;
        }

        $this->tpl()->setOnScreenMessage('failure', $this->txt('msg_no_permission'), true);
        $this->ctrl()->redirectByClass('ildashboardgui', 'jumpToSelectedItems');
    }

    private function hasValidCsrf(): bool
    {
        return ilSwitchUserSecurity::validateCsrfToken((string) ($_POST['swus_csrf'] ?? ''));
    }

    private function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    private function ctrl(): ilCtrl
    {
        global $DIC;

        return $DIC->ctrl();
    }

    private function tpl(): ilGlobalTemplateInterface
    {
        global $DIC;

        return $DIC->ui()->mainTemplate();
    }

    private function searchPage(): ilSwitchUserSearchPage
    {
        return new ilSwitchUserSearchPage(ilSwitchUserSecurity::getPlugin());
    }

    private function txt(string $key): string
    {
        $plugin = $this->getPluginObject();

        return ilSwitchUserSecurity::pluginText($plugin instanceof ilSwitchUserPlugin ? $plugin : null, $key);
    }
}
