<?php

declare(strict_types=1);

/**
 * Admin controller used by the plugin configuration page.
 */
class ilSwitchUserAdminGUI
{
    private ?ilSwitchUserPlugin $plugin;

    public function __construct(?ilSwitchUserPlugin $plugin = null)
    {
        $this->plugin = $plugin;
    }

    public function performCommand(string $cmd = 'show'): void
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
                ilSwitchUserSecurity::issueCsrfToken()
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
                $rows
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
        return new ilSwitchUserSearchPage($this->plugin);
    }

    private function txt(string $key): string
    {
        return ilSwitchUserSecurity::pluginText($this->plugin, $key);
    }
}
