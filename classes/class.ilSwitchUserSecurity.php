<?php

declare(strict_types=1);

/**
 * Common security helpers for the SwitchUser plugin.
 */
class ilSwitchUserSecurity
{
    public const SESSION_ACCOUNT_ID = 'AccountId';
    public const SESSION_AUTH_USER_ID = '_authsession_user_id';
    public const SESSION_ORIGINAL_USER_ID = 'switch_user_original_user_id';
    public const SESSION_ORIGINAL_USER_LOGIN = 'switch_user_original_user_login';
    public const SESSION_CSRF_TOKEN = 'switch_user_csrf_token';
    public const SESSION_STARTED_AT = 'switch_user_started_at';

    public static function issueCsrfToken(): string
    {
        if (!isset($_SESSION[self::SESSION_CSRF_TOKEN]) || !is_string($_SESSION[self::SESSION_CSRF_TOKEN])) {
            $_SESSION[self::SESSION_CSRF_TOKEN] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_CSRF_TOKEN];
    }

    public static function validateCsrfToken(?string $token): bool
    {
        if (!is_string($token) || $token === '') {
            return false;
        }

        $expected = $_SESSION[self::SESSION_CSRF_TOKEN] ?? null;
        if (!is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $token);
    }

    public static function clearCsrfToken(): void
    {
        unset($_SESSION[self::SESSION_CSRF_TOKEN]);
    }

    public static function getPlugin(): ?ilSwitchUserPlugin
    {
        if (!class_exists('ilPlugin') || !method_exists('ilPlugin', 'getPluginObject')) {
            return null;
        }

        /** @var mixed $plugin */
        $plugin = ilPlugin::getPluginObject('Services', 'UIComponent', 'uihk', 'SwitchUser');

        return $plugin instanceof ilSwitchUserPlugin ? $plugin : null;
    }

    public static function txt(string $key): string
    {
        return self::pluginText(self::getPlugin(), $key);
    }

    /**
     * Resolve a plugin text with safe built-in fallbacks.
     */
    public static function pluginText(?ilSwitchUserPlugin $plugin, string $key): string
    {
        if ($plugin instanceof ilSwitchUserPlugin) {
            $translated = $plugin->txt($key);
            if (is_string($translated) && $translated !== '' && $translated !== $key) {
                return $translated;
            }
        }

        $lang_key = self::currentLangKey();
        $catalog = self::fallbackCatalog();

        if (isset($catalog[$lang_key][$key])) {
            return $catalog[$lang_key][$key];
        }

        if (isset($catalog['en'][$key])) {
            return $catalog['en'][$key];
        }

        return $key;
    }

    private static function currentLangKey(): string
    {
        global $DIC;

        try {
            if (isset($DIC) && method_exists($DIC, 'language')) {
                $lang = $DIC->language();
                if (is_object($lang) && method_exists($lang, 'getLangKey')) {
                    $key = strtolower((string) $lang->getLangKey());
                    if ($key !== '') {
                        return substr($key, 0, 2);
                    }
                }
            }
        } catch (Throwable $e) {
            // ignore and fall back to session/server hints
        }

        $candidates = [
            $_GET['lang'] ?? null,
            $_POST['lang'] ?? null,
            $_SESSION['lang'] ?? null,
            $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? null,
        ];

        foreach ($candidates as $candidate) {
            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            if (preg_match('/^[a-zA-Z]{2}/', $candidate, $matches) === 1) {
                return strtolower($matches[0]);
            }
        }

        return 'en';
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function fallbackCatalog(): array
    {
        return [
            'fr' => [
                'plugin_name' => 'SwitchUser',
                'cfg_title' => 'SwitchUser',
                'cfg_description' => 'Recherchez un compte utilisateur et démarrez une session de bascule contrôlée.',
                'cfg_security_note' => 'Mode renforcé SSI : POST obligatoire, jeton CSRF requis, trace d’audit activée.',
                'cfg_placeholder' => 'Login, prénom, nom ou e-mail',
                'cfg_search' => 'Rechercher',
                'cfg_help' => 'Réservé aux opérations légitimes d’administration ou de support. N’utilisez pas ce mécanisme sur des comptes privilégiés. Revenez au compte d’origine dès la fin de l’intervention.',
                'cfg_no_results' => 'Aucun utilisateur correspondant trouvé.',
                'cfg_col_login' => 'Login',
                'cfg_col_name' => 'Nom',
                'cfg_col_email' => 'E-mail',
                'cfg_col_action' => 'Action',
                'cfg_takeover' => 'Démarrer la bascule',
                'cfg_return_original' => 'Revenir au compte d’origine',
                'msg_no_permission' => 'Vous n’êtes pas autorisé à utiliser SwitchUser.',
                'msg_user_not_found' => 'L’utilisateur sélectionné est introuvable.',
                'msg_same_user' => 'Vous êtes déjà cet utilisateur.',
                'msg_invalid_stop' => 'Le compte d’origine ne peut pas être restauré dans l’état actuel de la session.',
                'msg_takeover_started' => 'Vous êtes maintenant connecté en tant que « %s ».',
                'msg_takeover_stopped' => 'La session d’origine « %s » a été restaurée.',
                'msg_active_impersonation' => 'Bascule active. Compte d’origine : %s.',
                'msg_invalid_request' => 'La requête est invalide ou expirée. Relancez l’action depuis la page SwitchUser.',
                'msg_post_only' => 'Cette action n’est acceptée qu’en POST.',
                'msg_already_impersonating' => 'Une session de bascule est déjà active.',
                'msg_not_impersonating' => 'Aucune session de bascule n’est actuellement active.',
                'msg_admin_target_forbidden' => 'SwitchUser refuse la bascule vers un compte administrateur.',
                'admin_menu_title' => 'SwitchUser',
                'admin_menu_hint' => 'Page d’administration renforcée.',
                'nav_mainmenu_title' => 'Accès rapide SwitchUser',
                'nav_mainmenu_hint' => 'Ouvrir l’interface SwitchUser depuis le menu latéral',
                'topbar_icon_label' => 'Ouvrir SwitchUser',
                'topbar_icon_hint' => 'Ouvrir l’interface SwitchUser depuis la barre du haut',
            ],
            'en' => [
                'plugin_name' => 'SwitchUser',
                'cfg_title' => 'SwitchUser',
                'cfg_description' => 'Search for a user account and start a controlled impersonation session.',
                'cfg_security_note' => 'Security-hardened mode: POST only, CSRF token required, audit trace enabled.',
                'cfg_placeholder' => 'Login, first name, last name or e-mail',
                'cfg_search' => 'Search',
                'cfg_help' => 'Reserved for legitimate administration or support actions. Do not use on privileged accounts. Return to the original account immediately after the intervention.',
                'cfg_no_results' => 'No matching user found.',
                'cfg_col_login' => 'Login',
                'cfg_col_name' => 'Name',
                'cfg_col_email' => 'E-mail',
                'cfg_col_action' => 'Action',
                'cfg_takeover' => 'Start impersonation',
                'cfg_return_original' => 'Return to the original account',
                'msg_no_permission' => 'You are not allowed to use SwitchUser.',
                'msg_user_not_found' => 'The selected user could not be found.',
                'msg_same_user' => 'You are already this user.',
                'msg_invalid_stop' => 'The original account cannot be restored from the current session state.',
                'msg_takeover_started' => 'You are now logged in as “%s”.',
                'msg_takeover_stopped' => 'The original session “%s” has been restored.',
                'msg_active_impersonation' => 'Impersonation active. Original account: %s.',
                'msg_invalid_request' => 'The request is invalid or has expired. Please retry from the SwitchUser page.',
                'msg_post_only' => 'This action is only accepted by POST.',
                'msg_already_impersonating' => 'An impersonation session is already active.',
                'msg_not_impersonating' => 'No impersonation session is currently active.',
                'msg_admin_target_forbidden' => 'SwitchUser refuses to impersonate an administrator account.',
                'admin_menu_title' => 'SwitchUser',
                'admin_menu_hint' => 'Hardened administration page.',
                'nav_mainmenu_title' => 'SwitchUser quick access',
                'nav_mainmenu_hint' => 'Open the SwitchUser interface from the side menu',
                'topbar_icon_label' => 'Open SwitchUser',
                'topbar_icon_hint' => 'Open the SwitchUser interface from the top bar',
            ],
        ];
    }


    public static function isAdministrativeUser(): bool
    {
        if (method_exists('ilUtil', 'checkAdmin')) {
            return (bool) ilUtil::checkAdmin();
        }

        global $DIC;
        if (!isset($DIC)) {
            return false;
        }

        $roles = $DIC->rbac()->review()->assignedRoles((int) $DIC->user()->getId());

        return in_array(SYSTEM_ROLE_ID, $roles, true);
    }

    public static function isAdministrativeUserId(int $user_id): bool
    {
        global $DIC;
        if (!isset($DIC) || $user_id <= 0) {
            return false;
        }

        $roles = $DIC->rbac()->review()->assignedRoles($user_id);

        return in_array(SYSTEM_ROLE_ID, $roles, true);
    }

    public static function isImpersonationActive(): bool
    {
        global $DIC;
        if (!isset($DIC)) {
            return false;
        }

        $original_user_id = self::getOriginalUserId();
        if ($original_user_id === null) {
            return false;
        }

        return (int) $DIC->user()->getId() !== $original_user_id;
    }

    public static function getOriginalUserId(): ?int
    {
        if (!isset($_SESSION[self::SESSION_ORIGINAL_USER_ID])) {
            return null;
        }

        return (int) $_SESSION[self::SESSION_ORIGINAL_USER_ID];
    }

    public static function getOriginalUserLogin(): ?string
    {
        if (!isset($_SESSION[self::SESSION_ORIGINAL_USER_LOGIN])) {
            return null;
        }

        return (string) $_SESSION[self::SESSION_ORIGINAL_USER_LOGIN];
    }

    public static function searchUrl(): string
    {
        return ilUtil::appendUrlParameterString(
            'goto.php',
            'target=' . rawurlencode(ilSwitchUserPlugin::TARGET_OPEN)
        );
    }

    public static function actionUrl(): string
    {
        return ilUtil::appendUrlParameterString(
            'goto.php',
            'target=' . rawurlencode(ilSwitchUserPlugin::TARGET_ACTION)
        );
    }

    public static function regenerateSession(): void
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            @session_regenerate_id(true);
        }
    }

    /**
     * @param array<string, scalar|null> $context
     */
    public static function audit(string $event, array $context = []): void
    {
        global $DIC;

        $base = [
            'event' => $event,
            'actor_user_id' => isset($DIC) ? (int) $DIC->user()->getId() : null,
            'actor_login' => isset($DIC) ? (string) $DIC->user()->getLogin() : null,
            'original_user_id' => self::getOriginalUserId(),
            'remote_ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 180),
        ];

        $payload = '[SwitchUser] ' . json_encode(
            array_merge($base, $context),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );

        try {
            if (class_exists('ilLoggerFactory')) {
                $logger = ilLoggerFactory::getLogger('root');
                if (is_object($logger) && method_exists($logger, 'info')) {
                    $logger->info($payload);
                    return;
                }
            }
        } catch (Throwable $e) {
            // fall through to error_log
        }

        error_log($payload);
    }
}
