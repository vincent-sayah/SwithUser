<?php

/**
 * UI hook and secured goto handler for the SwitchUser plugin.
 */
class ilSwitchUserUIHookGUI extends ilUIHookPluginGUI
{
    private static $banner_rendered = false;

    public function checkGotoHook(string $a_target): array
    {
        return [
            'target' => in_array($a_target, [
                ilSwitchUserPlugin::TARGET_OPEN,
                ilSwitchUserPlugin::TARGET_ACTION,
            ], true),
        ];
    }

    public function gotoHook(): void
    {
        $target = (string) ($_GET['target'] ?? '');

        if ($target === ilSwitchUserPlugin::TARGET_OPEN) {
            $this->openSearchPage();
            return;
        }

        if ($target === ilSwitchUserPlugin::TARGET_ACTION) {
            $this->handleAction();
            return;
        }
    }

    public function getHTML(string $a_comp, string $a_part, array $a_par = []): array
    {
        if (!ilSwitchUserSecurity::isImpersonationActive() || self::$banner_rendered) {
            return [
                'mode' => self::KEEP,
                'html' => '',
            ];
        }

        $original_user_id = ilSwitchUserSecurity::getOriginalUserId();
        if ($original_user_id === null) {
            return [
                'mode' => self::KEEP,
                'html' => '',
            ];
        }

        self::$banner_rendered = true;

        return [
            'mode' => self::APPEND,
            'html' => $this->buildActiveBanner($original_user_id),
        ];
    }

    private function buildActiveBanner(int $original_user_id): string
    {
        $login = ilSwitchUserSecurity::getOriginalUserLogin() ?? ('#' . $original_user_id);
        $message = $this->esc(sprintf($this->txt('msg_active_impersonation'), $login));
        $button = $this->esc($this->txt('cfg_return_original'));
        $action = $this->esc(ilSwitchUserSecurity::actionUrl());
        $token = $this->esc(ilSwitchUserSecurity::issueCsrfToken());

        return <<<HTML
<div id="switch-user-banner" style="position:fixed;top:0;left:0;right:0;z-index:100000;background:#7a1212;color:#fff;padding:10px 14px;box-shadow:0 2px 6px rgba(0,0,0,.18);font-size:14px;line-height:1.4;">
  <div style="max-width:1280px;margin:0 auto;display:flex;gap:12px;justify-content:space-between;align-items:center;flex-wrap:wrap;">
    <div>{$message}</div>
    <form method="post" action="{$action}" style="margin:0;">
      <input type="hidden" name="op" value="stop">
      <input type="hidden" name="swus_csrf" value="{$token}">
      <button type="submit" style="padding:8px 12px;border:1px solid rgba(255,255,255,.35);border-radius:8px;background:#fff;color:#7a1212;font-weight:700;cursor:pointer;">{$button}</button>
    </form>
  </div>
</div>
<script>
(function () {
  if (window.__switchUserBannerOffsetApplied) {
    return;
  }
  window.__switchUserBannerOffsetApplied = true;
  var applyOffset = function () {
    var banner = document.getElementById('switch-user-banner');
    if (!banner || !document.body) {
      return;
    }
    var height = Math.ceil(banner.getBoundingClientRect().height || 0);
    document.body.style.paddingTop = height + 'px';
    if (document.documentElement) {
      document.documentElement.style.scrollPaddingTop = (height + 8) + 'px';
    }
  };
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyOffset);
  } else {
    applyOffset();
  }
  window.addEventListener('resize', applyOffset);
})();
</script>
HTML;
    }

    private function openSearchPage(): void
    {
        global $DIC;

        $is_admin = ilSwitchUserSecurity::isAdministrativeUser();
        $is_active = ilSwitchUserSecurity::isImpersonationActive();

        if (!$is_admin && !$is_active) {
            ilSwitchUserSecurity::audit('open_denied_not_admin');
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_no_permission'), true);
            $this->redirectToDashboard();
        }

        $term = '';
        $rows = [];
        if ($is_admin && !$is_active && $this->isPost()) {
            if (!$this->hasValidCsrf()) {
                ilSwitchUserSecurity::audit('search_denied_bad_csrf');
                $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_invalid_request'), true);
                $this->redirectToDashboard();
            }

            $term = trim((string) ($_POST['query'] ?? ''));
            $rows = $this->searchPage()->findUsers($term);
            ilSwitchUserSecurity::audit('search_performed', ['query_length' => strlen($term)]);
        }

        $tpl = $DIC->ui()->mainTemplate();
        $tpl->setTitle($this->txt('admin_menu_title'));
        if (method_exists($tpl, 'setDescription')) {
            $tpl->setDescription($this->txt('admin_menu_hint'));
        }
        $tpl->setContent(
            $this->searchPage()->render(
                ilSwitchUserSecurity::searchUrl(),
                ilSwitchUserSecurity::actionUrl(),
                ilSwitchUserSecurity::issueCsrfToken(),
                $term,
                $rows,
                $is_admin && !$is_active,
                ilSwitchUserSecurity::getOriginalUserLogin()
            )
        );

        if (method_exists($tpl, 'printToStdout')) {
            $tpl->printToStdout();
        } elseif (method_exists($tpl, 'show')) {
            $tpl->show();
        }

        exit;
    }

    private function handleAction(): void
    {
        global $DIC;

        if (!$this->isPost()) {
            ilSwitchUserSecurity::audit('action_denied_wrong_method', ['method' => (string) ($_SERVER['REQUEST_METHOD'] ?? '')]);
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_post_only'), true);
            $this->redirectToDashboard();
        }

        if (!$this->hasValidCsrf()) {
            ilSwitchUserSecurity::audit('action_denied_bad_csrf');
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_invalid_request'), true);
            $this->redirectToDashboard();
        }

        $op = (string) ($_POST['op'] ?? '');
        if ($op === 'stop') {
            $this->handleStop();
            return;
        }

        if ($op === 'start') {
            $this->handleStart();
            return;
        }

        ilSwitchUserSecurity::audit('action_denied_unknown_op', ['op' => $op]);
        $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_invalid_request'), true);
        $this->redirectToDashboard();
    }

    private function handleStart(): void
    {
        global $DIC;

        if (ilSwitchUserSecurity::isImpersonationActive()) {
            ilSwitchUserSecurity::audit('start_denied_already_active');
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_already_impersonating'), true);
            $this->redirectToDashboard();
        }

        if (!ilSwitchUserSecurity::isAdministrativeUser()) {
            ilSwitchUserSecurity::audit('start_denied_not_admin');
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_no_permission'), true);
            $this->redirectToDashboard();
        }

        $user_id = (int) ($_POST['user_id'] ?? 0);
        if ($user_id <= 0 || !ilObject::_exists($user_id, false, 'usr')) {
            ilSwitchUserSecurity::audit('start_denied_user_not_found', ['target_user_id' => $user_id]);
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_user_not_found'), true);
            $this->redirectToReferrerOrDashboard();
        }

        if ($user_id === ANONYMOUS_USER_ID || !$this->isUserActive($user_id)) {
            ilSwitchUserSecurity::audit('start_denied_inactive_or_anonymous', ['target_user_id' => $user_id]);
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_user_not_found'), true);
            $this->redirectToReferrerOrDashboard();
        }

        $current_user = $DIC->user();
        if ($user_id === (int) $current_user->getId()) {
            ilSwitchUserSecurity::audit('start_denied_same_user', ['target_user_id' => $user_id]);
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_same_user'), true);
            $this->redirectToReferrerOrDashboard();
        }

        if (ilSwitchUserSecurity::isAdministrativeUserId($user_id)) {
            ilSwitchUserSecurity::audit('start_denied_admin_target', ['target_user_id' => $user_id]);
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_admin_target_forbidden'), true);
            $this->redirectToReferrerOrDashboard();
        }

        $target_user = new ilObjUser($user_id);
        $_SESSION[ilSwitchUserSecurity::SESSION_ORIGINAL_USER_ID] = (int) $current_user->getId();
        $_SESSION[ilSwitchUserSecurity::SESSION_ORIGINAL_USER_LOGIN] = (string) $current_user->getLogin();
        $_SESSION[ilSwitchUserSecurity::SESSION_STARTED_AT] = time();
        $_SESSION[ilSwitchUserSecurity::SESSION_ACCOUNT_ID] = (int) $target_user->getId();
        $_SESSION[ilSwitchUserSecurity::SESSION_AUTH_USER_ID] = (int) $target_user->getId();
        ilSwitchUserSecurity::regenerateSession();
        ilSwitchUserSecurity::clearCsrfToken();

        ilSwitchUserSecurity::audit('start_success', [
            'target_user_id' => (int) $target_user->getId(),
            'target_login' => (string) $target_user->getLogin(),
        ]);

        $message = sprintf($this->txt('msg_takeover_started'), $this->esc((string) $target_user->getLogin()));
        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $message, true);
        $this->redirectToDashboard();
    }

    private function handleStop(): void
    {
        global $DIC;

        if (!ilSwitchUserSecurity::isImpersonationActive()) {
            ilSwitchUserSecurity::audit('stop_denied_not_active');
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_not_impersonating'), true);
            $this->redirectToDashboard();
        }

        $original_user_id = ilSwitchUserSecurity::getOriginalUserId();
        if ($original_user_id === null || !ilObject::_exists($original_user_id, false, 'usr')) {
            ilSwitchUserSecurity::audit('stop_denied_original_user_missing');
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_invalid_stop'), true);
            $this->forceLocalCleanup();
            $this->redirectToDashboard();
        }

        $original_user = new ilObjUser($original_user_id);
        $_SESSION[ilSwitchUserSecurity::SESSION_ACCOUNT_ID] = $original_user_id;
        $_SESSION[ilSwitchUserSecurity::SESSION_AUTH_USER_ID] = $original_user_id;
        unset(
            $_SESSION[ilSwitchUserSecurity::SESSION_ORIGINAL_USER_ID],
            $_SESSION[ilSwitchUserSecurity::SESSION_ORIGINAL_USER_LOGIN],
            $_SESSION[ilSwitchUserSecurity::SESSION_STARTED_AT]
        );
        ilSwitchUserSecurity::regenerateSession();
        ilSwitchUserSecurity::clearCsrfToken();

        ilSwitchUserSecurity::audit('stop_success', [
            'restored_user_id' => $original_user_id,
            'restored_login' => (string) $original_user->getLogin(),
        ]);

        $message = sprintf($this->txt('msg_takeover_stopped'), $this->esc((string) $original_user->getLogin()));
        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $message, true);
        $this->redirectToDashboard();
    }

    private function forceLocalCleanup(): void
    {
        unset(
            $_SESSION[ilSwitchUserSecurity::SESSION_ORIGINAL_USER_ID],
            $_SESSION[ilSwitchUserSecurity::SESSION_ORIGINAL_USER_LOGIN],
            $_SESSION[ilSwitchUserSecurity::SESSION_STARTED_AT]
        );
        ilSwitchUserSecurity::clearCsrfToken();
    }

    private function isUserActive(int $user_id): bool
    {
        global $DIC;

        $sql = 'SELECT active FROM usr_data WHERE usr_id = ' . $DIC->database()->quote($user_id, 'integer');
        $set = $DIC->database()->query($sql);
        $row = $DIC->database()->fetchAssoc($set);

        return ((int) ($row['active'] ?? 0)) === 1;
    }

    private function hasValidCsrf(): bool
    {
        return ilSwitchUserSecurity::validateCsrfToken((string) ($_POST['swus_csrf'] ?? ''));
    }

    private function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    private function searchPage(): ilSwitchUserSearchPage
    {
        $plugin = $this->getPluginObject();

        return new ilSwitchUserSearchPage($plugin instanceof ilSwitchUserPlugin ? $plugin : null);
    }

    private function redirectToReferrerOrDashboard(): void
    {
        global $DIC;

        $referer = (string) $DIC->http()->request()->getHeaderLine('referer');
        $base = (string) $DIC->http()->request()->getUri()->withQuery('')->withFragment('');
        if ($referer !== '' && $base !== '' && strpos($referer, $base) === 0) {
            $DIC->ctrl()->redirectToURL($referer);
        }

        $this->redirectToDashboard();
    }

    private function redirectToDashboard(): void
    {
        global $DIC;

        $DIC->ctrl()->redirectByClass('ildashboardgui', 'jumpToSelectedItems');
        exit;
    }

    private function txt(string $key): string
    {
        $plugin = $this->getPluginObject();

        return ilSwitchUserSecurity::pluginText($plugin instanceof ilSwitchUserPlugin ? $plugin : null, $key);
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
