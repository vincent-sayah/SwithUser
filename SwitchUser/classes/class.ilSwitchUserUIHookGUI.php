<?php

declare(strict_types=1);

/**
 * UI hook and goto handler for the ILIAS 10 SwitchUser plugin.
 */
class ilSwitchUserUIHookGUI extends ilUIHookPluginGUI
{
    private const SESSION_ACCOUNT_ID = 'AccountId';
    private const SESSION_AUTH_USER_ID = '_authsession_user_id';
    private const SESSION_ORIGINAL_USER_ID = 'switch_user_original_user_id';
    private const SESSION_ORIGINAL_USER_LOGIN = 'switch_user_original_user_login';

    private static bool $message_rendered = false;

    public function gotoHook(): void
    {
        global $DIC;

        $request = $DIC->http()->wrapper()->query();
        if (!$request->has('target')) {
            return;
        }

        $target = $request->retrieve('target', $DIC->refinery()->kindlyTo()->string());
        if ($target !== ilSwitchUserPlugin::PLUGIN_ID) {
            return;
        }

        if (!$request->has('user_id')) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_user_not_found'), true);
            $this->redirectToReferrerOrDashboard();
        }

        $user_id = $request->retrieve('user_id', $DIC->refinery()->kindlyTo()->int());
        if ($user_id <= 0) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_user_not_found'), true);
            $this->redirectToReferrerOrDashboard();
        }

        if ($this->isImpersonationActive()) {
            $original_user_id = $this->getOriginalUserId();
            if ($original_user_id !== null && $original_user_id === $user_id) {
                $this->stopImpersonation($original_user_id);
            }

            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_invalid_stop'), true);
            $this->redirectToDashboard();
        }

        if (!$this->isAdministrativeUser()) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_no_permission'), true);
            $this->redirectToReferrerOrDashboard();
        }

        if (!ilObject::_exists($user_id, false, 'usr')) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_user_not_found'), true);
            $this->redirectToReferrerOrDashboard();
        }

        $target_user = new ilObjUser($user_id);
        $current_user = $DIC->user();

        if ($target_user->getId() === ANONYMOUS_USER_ID) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_user_not_found'), true);
            $this->redirectToReferrerOrDashboard();
        }

        if ($target_user->getId() === $current_user->getId()) {
            $DIC->ui()->mainTemplate()->setOnScreenMessage('failure', $this->txt('msg_same_user'), true);
            $this->redirectToReferrerOrDashboard();
        }

        $this->startImpersonation($target_user);
    }

    public function getHTML(string $a_comp, string $a_part, array $a_par = []): array
    {
        if (!$this->isImpersonationActive() || self::$message_rendered) {
            return [
                'mode' => self::KEEP,
                'html' => ''
            ];
        }

        $original_user_id = $this->getOriginalUserId();
        if ($original_user_id === null) {
            return [
                'mode' => self::KEEP,
                'html' => ''
            ];
        }

        $link = ilUtil::appendUrlParameterString(
            'goto.php',
            'target=' . rawurlencode(ilSwitchUserPlugin::PLUGIN_ID) . '&user_id=' . $original_user_id
        );
        $login = (string) ($_SESSION[self::SESSION_ORIGINAL_USER_LOGIN] ?? ('#' . $original_user_id));
        $message = sprintf(
            $this->txt('msg_active_impersonation'),
            $link,
            htmlspecialchars($login, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );

        self::$message_rendered = true;

        return [
            'mode' => self::APPEND,
            'html' => $this->buildImpersonationBannerScript($message)
        ];
    }

    private function buildImpersonationBannerScript(string $message): string
    {
        $message_html = json_encode($message, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);

        return <<<HTML
<script>
(function () {
    if (window.__switchUserBannerInjected) {
        return;
    }
    window.__switchUserBannerInjected = true;

    var renderBanner = function () {
        if (!document.body || document.getElementById('switch-user-banner')) {
            return;
        }

        var banner = document.createElement('div');
        banner.id = 'switch-user-banner';
        banner.style.position = 'fixed';
        banner.style.top = '0';
        banner.style.left = '0';
        banner.style.right = '0';
        banner.style.zIndex = '100000';
        banner.style.background = '#7a1212';
        banner.style.color = '#ffffff';
        banner.style.padding = '10px 16px';
        banner.style.textAlign = 'center';
        banner.style.fontSize = '14px';
        banner.style.lineHeight = '1.4';
        banner.style.boxShadow = '0 2px 6px rgba(0,0,0,.2)';
        banner.innerHTML = {$message_html};

        var links = banner.getElementsByTagName('a');
        for (var i = 0; i < links.length; i++) {
            links[i].style.color = '#ffffff';
            links[i].style.fontWeight = '700';
            links[i].style.textDecoration = 'underline';
        }

        document.body.insertBefore(banner, document.body.firstChild);
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', renderBanner);
    } else {
        renderBanner();
    }
})();
</script>
HTML;
    }

    private function txt(string $key): string
    {
        $plugin = $this->getPluginObject();
        return $plugin ? $plugin->txt($key) : $key;
    }

    private function startImpersonation(ilObjUser $target_user): void
    {
        global $DIC;

        $current_user = $DIC->user();
        $_SESSION[self::SESSION_ORIGINAL_USER_ID] = (int) $current_user->getId();
        $_SESSION[self::SESSION_ORIGINAL_USER_LOGIN] = (string) $current_user->getLogin();
        $_SESSION[self::SESSION_ACCOUNT_ID] = (int) $target_user->getId();
        $_SESSION[self::SESSION_AUTH_USER_ID] = (int) $target_user->getId();

        session_write_close();

        $message = sprintf(
            $this->txt('msg_takeover_started'),
            htmlspecialchars($target_user->getLogin(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $message, true);
        $this->redirectToDashboard();
    }

    private function stopImpersonation(int $original_user_id): void
    {
        global $DIC;

        $original_user = new ilObjUser($original_user_id);
        unset($_SESSION[self::SESSION_ORIGINAL_USER_ID], $_SESSION[self::SESSION_ORIGINAL_USER_LOGIN]);
        $_SESSION[self::SESSION_ACCOUNT_ID] = $original_user_id;
        $_SESSION[self::SESSION_AUTH_USER_ID] = $original_user_id;

        session_write_close();

        $message = sprintf(
            $this->txt('msg_takeover_stopped'),
            htmlspecialchars($original_user->getLogin(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
        );
        $DIC->ui()->mainTemplate()->setOnScreenMessage('success', $message, true);
        $this->redirectToDashboard();
    }

    private function isImpersonationActive(): bool
    {
        $original_user_id = $this->getOriginalUserId();
        if ($original_user_id === null) {
            return false;
        }

        global $DIC;
        return (int) $DIC->user()->getId() !== $original_user_id;
    }

    private function getOriginalUserId(): ?int
    {
        if (!isset($_SESSION[self::SESSION_ORIGINAL_USER_ID])) {
            return null;
        }

        return (int) $_SESSION[self::SESSION_ORIGINAL_USER_ID];
    }

    private function isAdministrativeUser(): bool
    {
        if (method_exists('ilUtil', 'checkAdmin')) {
            return (bool) ilUtil::checkAdmin();
        }

        global $DIC;
        $roles = $DIC->rbac()->review()->assignedRoles((int) $DIC->user()->getId());
        return in_array(SYSTEM_ROLE_ID, $roles, true);
    }

    private function redirectToReferrerOrDashboard(): void
    {
        global $DIC;

        $referer = $DIC->http()->request()->getHeaderLine('referer');
        if ($referer !== '') {
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
}
