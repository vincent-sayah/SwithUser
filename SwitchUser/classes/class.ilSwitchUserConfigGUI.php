<?php

declare(strict_types=1);

/**
 * @ilCtrl_IsCalledBy ilSwitchUserConfigGUI: ilObjComponentSettingsGUI
 */
class ilSwitchUserConfigGUI extends ilPluginConfigGUI
{
    public function performCommand(string $cmd = ''): void
    {
        $cmd = $cmd !== '' ? $cmd : (string) $this->ctrl()->getCmd('configure');

        switch ($cmd) {
            case 'search':
                $this->search();
                return;

            case 'configure':
            default:
                $this->configure();
                return;
        }
    }

    public function configure(): void
    {
        $this->tpl()->setContent($this->renderPage());
    }

    public function search(): void
    {
        $term = trim((string) ($_POST['query'] ?? ''));
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

    private function plugin(): ilSwitchUserPlugin
    {
        if (method_exists($this, 'getPluginObject')) {
            /** @var mixed $plugin */
            $plugin = $this->getPluginObject();
            if ($plugin instanceof ilSwitchUserPlugin) {
                return $plugin;
            }
        }

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

        throw new RuntimeException('Unable to resolve SwitchUser plugin object.');
    }

    private function renderPage(string $term = '', array $rows = []): string
    {
        $title = htmlspecialchars($this->plugin()->txt('cfg_title'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $description = htmlspecialchars($this->plugin()->txt('cfg_description'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $placeholder = htmlspecialchars($this->plugin()->txt('cfg_placeholder'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $button = htmlspecialchars($this->plugin()->txt('cfg_search'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $help = htmlspecialchars($this->plugin()->txt('cfg_help'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
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
            return '<p>' . htmlspecialchars($this->plugin()->txt('cfg_no_results'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }

        $header_login = htmlspecialchars($this->plugin()->txt('cfg_col_login'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $header_name = htmlspecialchars($this->plugin()->txt('cfg_col_name'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $header_email = htmlspecialchars($this->plugin()->txt('cfg_col_email'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $header_action = htmlspecialchars($this->plugin()->txt('cfg_col_action'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $action_label = htmlspecialchars($this->plugin()->txt('cfg_takeover'), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

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
