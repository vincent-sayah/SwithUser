<?php

declare(strict_types=1);

/**
 * HTML renderer and user lookup helper for SwitchUser.
 */
class ilSwitchUserSearchPage
{
    private ?ilSwitchUserPlugin $plugin;

    public function __construct(?ilSwitchUserPlugin $plugin = null)
    {
        $this->plugin = $plugin;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    public function render(
        string $search_action,
        string $action_url,
        string $csrf_token,
        string $term = '',
        array $rows = [],
        bool $allow_search = true,
        ?string $original_login = null
    ): string
    {
        $title = $this->esc($this->txt('cfg_title'));
        $description = $this->esc($this->txt('cfg_description'));
        $security_note = $this->esc($this->txt('cfg_security_note'));
        $placeholder = $this->esc($this->txt('cfg_placeholder'));
        $search_label = $this->esc($this->txt('cfg_search'));
        $help = nl2br($this->esc($this->txt('cfg_help')));
        $value = $this->esc($term);
        $search_action_attr = $this->esc($search_action);
        $action_url_attr = $this->esc($action_url);
        $csrf_attr = $this->esc($csrf_token);

        $html = <<<HTML
<div class="switchuser-wrapper" style="max-width:1100px;margin:0 auto;">
  <div class="switchuser-box" style="background:#fff;border:1px solid #dcdfe3;border-radius:12px;padding:24px;box-shadow:0 2px 8px rgba(0,0,0,.05);">
    <h2 style="margin:0 0 12px 0;">{$title}</h2>
    <p style="margin:0 0 8px 0;">{$description}</p>
    <p style="margin:0 0 18px 0;color:#7a1212;font-weight:600;">{$security_note}</p>
HTML;

        if ($allow_search === false && $original_login !== null && $original_login !== '') {
            $active_message = $this->esc(sprintf($this->txt('msg_active_impersonation'), $original_login));
            $return_label = $this->esc($this->txt('cfg_return_original'));

            $html .= <<<HTML
    <div style="margin:0 0 20px 0;padding:14px 16px;background:#fff5f5;border:1px solid #fecaca;border-left:4px solid #b91c1c;border-radius:8px;display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap;">
      <div style="font-weight:600;color:#7f1d1d;">{$active_message}</div>
      <form method="post" action="{$action_url_attr}" style="margin:0;">
        <input type="hidden" name="op" value="stop">
        <input type="hidden" name="swus_csrf" value="{$csrf_attr}">
        <button type="submit" style="padding:10px 14px;border:0;border-radius:8px;background:#b91c1c;color:#fff;font-weight:600;cursor:pointer;">{$return_label}</button>
      </form>
    </div>
HTML;
        }

        if ($allow_search) {
            $html .= <<<HTML
    <div style="margin:0 0 20px 0;padding:12px 14px;background:#f6f8fa;border-left:4px solid #6a737d;border-radius:6px;">{$help}</div>
    <form method="post" action="{$search_action_attr}" style="display:flex;gap:10px;align-items:flex-start;flex-wrap:wrap;margin:0 0 18px 0;">
      <input type="hidden" name="swus_csrf" value="{$csrf_attr}">
      <input type="text" name="query" value="{$value}" placeholder="{$placeholder}" style="flex:1 1 420px;min-width:260px;padding:10px 12px;border:1px solid #c9ced6;border-radius:8px;">
      <button type="submit" style="padding:10px 16px;border:0;border-radius:8px;background:#1f4b99;color:#fff;font-weight:600;cursor:pointer;">{$search_label}</button>
    </form>
HTML;

            if ($term !== '') {
                $html .= $this->renderResults($rows, $action_url_attr, $csrf_attr);
            }
        }

        $html .= <<<HTML
  </div>
</div>
HTML;

        return $html;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findUsers(string $term): array
    {
        $term = trim($term);
        if ($term === '') {
            return [];
        }

        $quoted = '%' . $this->db()->escape($term, false) . '%';
        $sql = "SELECT usr_id, login, firstname, lastname, email, active FROM usr_data"
            . " WHERE active = " . $this->db()->quote(1, 'integer')
            . " AND usr_id != " . $this->db()->quote(ANONYMOUS_USER_ID, 'integer')
            . " AND (login LIKE " . $this->db()->quote($quoted, 'text')
            . " OR firstname LIKE " . $this->db()->quote($quoted, 'text')
            . " OR lastname LIKE " . $this->db()->quote($quoted, 'text')
            . " OR email LIKE " . $this->db()->quote($quoted, 'text') . ")"
            . " ORDER BY login ASC";

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

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function renderResults(array $rows, string $action_url_attr, string $csrf_attr): string
    {
        if ($rows === []) {
            return '<div style="padding:12px 14px;background:#fff7ed;border:1px solid #fed7aa;border-radius:8px;">'
                . $this->esc($this->txt('cfg_no_results'))
                . '</div>';
        }

        $header_login = $this->esc($this->txt('cfg_col_login'));
        $header_name = $this->esc($this->txt('cfg_col_name'));
        $header_email = $this->esc($this->txt('cfg_col_email'));
        $header_action = $this->esc($this->txt('cfg_col_action'));
        $action_label = $this->esc($this->txt('cfg_takeover'));

        $html = <<<HTML
<div style="overflow-x:auto;">
  <table style="width:100%;border-collapse:collapse;background:#fff;">
    <thead>
      <tr>
        <th style="text-align:left;padding:12px;border-bottom:2px solid #dcdfe3;">{$header_login}</th>
        <th style="text-align:left;padding:12px;border-bottom:2px solid #dcdfe3;">{$header_name}</th>
        <th style="text-align:left;padding:12px;border-bottom:2px solid #dcdfe3;">{$header_email}</th>
        <th style="text-align:left;padding:12px;border-bottom:2px solid #dcdfe3;">{$header_action}</th>
      </tr>
    </thead>
    <tbody>
HTML;

        foreach ($rows as $row) {
            $user_id = (int) ($row['usr_id'] ?? 0);
            $login = $this->esc((string) ($row['login'] ?? ''));
            $name = $this->esc(trim((string) ($row['firstname'] ?? '') . ' ' . (string) ($row['lastname'] ?? '')));
            $email = $this->esc((string) ($row['email'] ?? ''));
            $id_attr = $this->esc((string) $user_id);

            $html .= <<<HTML
      <tr>
        <td style="padding:12px;border-bottom:1px solid #edf0f2;">{$login}</td>
        <td style="padding:12px;border-bottom:1px solid #edf0f2;">{$name}</td>
        <td style="padding:12px;border-bottom:1px solid #edf0f2;">{$email}</td>
        <td style="padding:12px;border-bottom:1px solid #edf0f2;">
          <form method="post" action="{$action_url_attr}" style="margin:0;">
            <input type="hidden" name="op" value="start">
            <input type="hidden" name="user_id" value="{$id_attr}">
            <input type="hidden" name="swus_csrf" value="{$csrf_attr}">
            <button type="submit" style="padding:8px 12px;border:0;border-radius:8px;background:#0f766e;color:#fff;font-weight:600;cursor:pointer;">{$action_label}</button>
          </form>
        </td>
      </tr>
HTML;
        }

        $html .= <<<HTML
    </tbody>
  </table>
</div>
HTML;

        return $html;
    }

    private function db(): ilDBInterface
    {
        global $DIC;

        return $DIC->database();
    }

    private function txt(string $key): string
    {
        return ilSwitchUserSecurity::pluginText($this->plugin, $key);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
