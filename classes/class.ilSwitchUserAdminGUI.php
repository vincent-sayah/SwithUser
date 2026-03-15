<?php

declare(strict_types=1);

/**
 * @ilCtrl_Calls ilAdministrationGUI: ilSwitchUserAdminGUI
 * @ilCtrl_Calls ilObjUserFolderGUI: ilSwitchUserAdminGUI
 * @ilCtrl_IsCalledBy ilSwitchUserAdminGUI: ilAdministrationGUI
 * @ilCtrl_IsCalledBy ilSwitchUserAdminGUI: ilObjUserFolderGUI
 */
class ilSwitchUserAdminGUI
{
    public function __construct(...$args)
    {
    }

    public function executeCommand(): void
    {
        $cmd = (string) $this->ctrl()->getCmd('show');

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
        $this->tpl()->setTitle($this->txt('admin_menu_title'));
        $this->tpl()->setContent($this->renderPage());
    }

    public function search(): void
    {
        $term = trim((string) ($_POST['query'] ?? ''));
        $this->tpl()->setTitle($this->txt('admin_menu_title'));
        $this->tpl()->setContent($this->renderPage($term, $this->findUsers($term)));
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

    private function db(): ilDBInterface
    {
        global $DIC;
        return $DIC->database();
    }

    private function plugin(): ?ilSwitchUserPlugin
    {
        if (class_exists('ilPlugin') && method_exists('ilPlugin', 'getPluginObject')) {
            /** @var mixed $plugin */
            $plugin = ilPlugin::getPluginObject(
                'Services',
                'UIComponent',
                'uihk',
                'SwitchUser'
            );
            if ($plugin instanceof ilSwitchUserPlugin) {
                return $plugin;
            }
        }

        return null;
    }

    private function txt(string $key): string
    {
        $plugin = $this->plugin();
        if ($plugin instanceof ilSwitchUserPlugin) {
            return $plugin->txt($key);
        }

        $lang = 'en';
        if (isset($_COOKIE['lang'])) {
            $lang = strtolower((string) $_COOKIE['lang']);
        }

        if ($lang === 'fr' || str_starts_with($lang, 'fr')) {
            $map = [
                'admin_menu_title' => 'SwitchUser',
                'cfg_description' => 'Recherchez un compte utilisateur pour se connecter temporairement avec l\'identité de l’utilisateur ciblé.',
                'cfg_placeholder' => 'Login, prénom, nom ou e-mail',
                'cfg_search' => 'Rechercher',
                'cfg_help' => 'À utiliser uniquement dans un contexte d’administration ou de support. Tester d’abord sur une préproduction.',
                'cfg_no_results' => 'Aucun utilisateur correspondant trouvé.',
                'cfg_col_login' => 'Login',
                'cfg_col_name' => 'Nom',
                'cfg_col_email' => 'E-mail',
                'cfg_col_action' => 'Action',
                'cfg_takeover' => 'Se connecter en tant que',
            ];
        } else {
            $map = [
                'admin_menu_title' => 'SwitchUser',
                'cfg_description' => 'Search for a user account and start impersonation.',
                'cfg_placeholder' => 'Login, first name, last name or e-mail',
                'cfg_search' => 'Search',
                'cfg_help' => 'Use only for administration or support purposes. Test on staging first.',
                'cfg_no_results' => 'No matching user found.',
                'cfg_col_login' => 'Login',
                'cfg_col_name' => 'Name',
                'cfg_col_email' => 'E-mail',
                'cfg_col_action' => 'Action',
                'cfg_takeover' => 'Log in as user',
            ];
        }

        return $map[$key] ?? $key;
    }

    private function renderPage(string $term = '', array $rows = []): string
    {
        $title = htmlspecialchars($this->txt('admin_menu_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $description = htmlspecialchars($this->txt('cfg_description'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $placeholder = htmlspecialchars($this->txt('cfg_placeholder'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $button = htmlspecialchars($this->txt('cfg_search'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $help = htmlspecialchars($this->txt('cfg_help'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $value = htmlspecialchars($term, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action = htmlspecialchars($this->ctrl()->getFormAction($this, 'search'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = <<<HTML
<div class="ilFormHeader">{$title}</div>
<p>{$description}</p>
<p><em>{$help}</em></p>
<form method="post" action="{$action}" class="form-horizontal">
    <div class="form-group">
        <label for="swus_query">{$placeholder}</label><br>
        <input type="text" id="swus_query" name="query" value="{$value}" style="min-width: 30rem; max-width: 100%;" />
        <button class="btn btn-primary" type="submit">{$button}</button>
    </div>
</form>
HTML;

        if ($term !== '') {
            $html .= $this->renderResults($rows);
        }

        return $html;
    }

    private function renderResults(array $rows): string
    {
        if ($rows === []) {
            return '<p>' . htmlspecialchars($this->txt('cfg_no_results'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        $header_login = htmlspecialchars($this->txt('cfg_col_login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $header_name = htmlspecialchars($this->txt('cfg_col_name'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $header_email = htmlspecialchars($this->txt('cfg_col_email'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $header_action = htmlspecialchars($this->txt('cfg_col_action'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action_label = htmlspecialchars($this->txt('cfg_takeover'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        $html = <<<HTML
<table class="table table-striped fullwidth">
    <thead>
        <tr>
            <th>{$header_login}</th>
            <th>{$header_name}</th>
            <th>{$header_email}</th>
            <th>{$header_action}</th>
        </tr>
    </thead>
    <tbody>
HTML;

        foreach ($rows as $row) {
            $user_id = (int) $row['usr_id'];
            $login = htmlspecialchars((string) $row['login'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $name = htmlspecialchars(trim((string) $row['firstname'] . ' ' . (string) $row['lastname']), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $email = htmlspecialchars((string) $row['email'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
            $url = htmlspecialchars(
                ilUtil::appendUrlParameterString('goto.php', 'target=' . rawurlencode(ilSwitchUserPlugin::PLUGIN_ID) . '&user_id=' . $user_id),
                ENT_QUOTES | ENT_SUBSTITUTE,
                'UTF-8'
            );

            $html .= <<<HTML
        <tr>
            <td>{$login}</td>
            <td>{$name}</td>
            <td>{$email}</td>
            <td><a class="btn btn-default" href="{$url}">{$action_label}</a></td>
        </tr>
HTML;
        }

        $html .= <<<HTML
    </tbody>
</table>
HTML;

        return $html;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findUsers(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $quoted = '%' . $this->db()->escape($term, false) . '%';
        $sql = "SELECT usr_id, login, firstname, lastname, email
            FROM usr_data
            WHERE active = " . $this->db()->quote(1, 'integer') . "
              AND usr_id != " . $this->db()->quote(ANONYMOUS_USER_ID, 'integer') . "
              AND (
                    login LIKE " . $this->db()->quote($quoted, 'text') . "
                 OR firstname LIKE " . $this->db()->quote($quoted, 'text') . "
                 OR lastname LIKE " . $this->db()->quote($quoted, 'text') . "
                 OR email LIKE " . $this->db()->quote($quoted, 'text') . "
              )
            ORDER BY login ASC";

        $set = $this->db()->query($sql);
        $rows = [];
        while ($row = $this->db()->fetchAssoc($set)) {
            $rows[] = $row;
            if (count($rows) >= 50) {
                break;
            }
        }

        return $rows;
    }
}
