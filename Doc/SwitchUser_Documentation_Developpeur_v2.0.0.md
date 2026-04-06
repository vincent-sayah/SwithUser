# SwitchUser 2.0.0 - Document développeur sur les évolutions de sécurité et d'ergonomie

**Explication fonctionnelle et technique des principaux changements**

- Source analysée : archive SwitchUser-release_10.zip (version 2.0.0)
- Public visé : chefs de projet, administrateurs, support, responsables SSI et développeurs.

## 1. Objet du document

Ce document explique, point par point, ce qui a changé dans SwitchUser 2.0.0.

Chaque thème est présenté à deux niveaux : d'abord avec des mots simples, puis avec un angle plus technique sur le fonctionnement réel dans le code.

Pour chaque évolution, le document indique aussi quelles classes, fonctions ou fichiers portent le changement.

## 2. Vue d'ensemble du nouveau fonctionnement

```text
Administrateur
     |
     v
Accès SwitchUser (configuration ou icône du header)
     |
     v
Recherche utilisateur + formulaire POST + jeton CSRF
     |
     v
Contrôles (droits, cible, statut, compte admin interdit...)
     |
     v
Mise à jour de session + régénération d'identifiant + journalisation
     |
     v
Navigation sous identité empruntée
     |
     v
Bannière persistante avec bouton de retour sécurisé
     |
     v
Retour au compte d'origine + régénération de session + journalisation
```

## 3. Synthèse rapide

| Évolution | Parties principales | Idée clé |
|---|---|---|
| POST pour les actions | UIHookGUI, SearchPage, ConfigGUI, AdminGUI, Security, Plugin | Séparer l'ouverture de page et les actions sensibles. |
| Jeton CSRF | Security, SearchPage, UIHookGUI, ConfigGUI, AdminGUI | Valider qu'une action vient bien de l'écran légitime. |
| Journalisation | Security, UIHookGUI | Tracer les actions sensibles et les refus. |
| Régénération de session | Security, UIHookGUI | Changer l'identifiant de session lors des changements d'identité. |
| Blocage des cibles admin | Security, UIHookGUI | Empêcher une bascule vers un compte puissant. |
| Validation stricte des identifiants | UIHookGUI, Security, SearchPage | Vérifier que la cible est cohérente et autorisée. |
| Bannière persistante | UIHookGUI, SearchPage, Security | Rappeler l'état de bascule et sécuriser le retour. |
| Accès rapide header | Plugin, MetaBarProvider, icône SVG | Rendre l'outil visible et rapidement accessible. |

## 4. Abandon des actions de bascule en GET au profit d'actions POST

### Explication simple

- Avant, l'action de bascule pouvait être portée directement par l'URL. Cela signifie qu'un lien pouvait contenir l'ordre de démarrer ou d'arrêter la bascule.
- Désormais, la bascule passe par un formulaire envoyé en POST. En pratique, l'action n'apparaît plus comme une simple URL à copier, à mettre en favori ou à déclencher par inadvertance.
- Pour un non-informaticien, on peut voir cela comme la différence entre « cliquer sur un lien public » et « valider une action dans un formulaire prévu pour cela ».

### Comment cela fonctionne au niveau du développement

- Le plugin conserve un point d'entrée GET pour ouvrir l'écran SwitchUser (`goto.php?target=swus_open`), mais les actions sensibles de démarrage et d'arrêt passent maintenant par un autre point d'entrée, dédié aux actions : `goto.php?target=swus_action`.
- Dans l'interface de recherche, chaque ligne de résultat génère un formulaire HTML en `method="post"` avec les champs cachés `op=start`, `user_id` et `swus_csrf`. Le bouton de retour de la bannière envoie lui aussi un formulaire POST avec `op=stop`.
- Côté traitement, `ilSwitchUserUIHookGUI::handleAction()` commence par vérifier la méthode HTTP avec `isPost()`. Si la requête n'est pas en POST, elle est refusée, journalisée et l'utilisateur est renvoyé vers le tableau de bord.
- Le même principe est appliqué à la recherche exécutée depuis la page de configuration ou l'écran d'administration : les formulaires de recherche sont eux aussi en POST.

### Schéma simplifié

```text
Ouverture de page
GET goto.php?target=swus_open
        |
        v
Écran de recherche SwitchUser
        |
        |  formulaire POST
        v
POST goto.php?target=swus_action
(op=start | op=stop, user_id, swus_csrf)
        |
        v
Contrôles de sécurité puis action
```

### Partie du développement qui agit

| Fichier | Élément | Rôle |
|---|---|---|
| `classes/class.ilSwitchUserUIHookGUI.php` | `gotoHook(), handleAction(), isPost()` | Sépare l'ouverture de page et l'exécution des actions ; refuse toute action sensible hors POST. |
| `classes/class.ilSwitchUserSearchPage.php` | `render(), renderResults()` | Construit les formulaires POST de démarrage et d'arrêt. |
| `classes/class.ilSwitchUserConfigGUI.php` | `search(), isPost()` | Recherche administrateur envoyée en POST. |
| `classes/class.ilSwitchUserAdminGUI.php` | `search(), isPost()` | Même logique côté écran d'administration. |
| `classes/class.ilSwitchUserSecurity.php` | `actionUrl(), searchUrl()` | Fournit les URL de l'écran et des actions. |
| `classes/class.ilSwitchUserPlugin.php` | `TARGET_OPEN, TARGET_ACTION` | Déclare les deux cibles utilisées par le routeur. |

## 5. Ajout d'un jeton CSRF côté session

### Explication simple

- Le jeton CSRF est un petit secret temporaire conservé dans la session de l'administrateur.
- Son rôle est de vérifier que l'action demandée vient bien de l'écran SwitchUser affiché par ILIAS, et non d'une page externe qui essaierait de déclencher la bascule à l'insu de l'administrateur.
- On peut le comparer à un ticket de contrôle : l'action n'est acceptée que si le bon ticket accompagne la demande.

### Comment cela fonctionne au niveau du développement

- `ilSwitchUserSecurity::issueCsrfToken()` crée le jeton une seule fois par session active de travail, avec `random_bytes(32)` puis `bin2hex(...)`. Le résultat est stocké dans `$_SESSION['switch_user_csrf_token']`.
- Toutes les actions POST importantes ajoutent ce jeton dans un champ caché `swus_csrf` : recherche, démarrage de bascule et retour au compte d'origine.
- La validation est assurée par `ilSwitchUserSecurity::validateCsrfToken()`, qui compare la valeur reçue avec celle stockée en session via `hash_equals()`. Cette comparaison évite les comparaisons fragiles ou approximatives.
- Après un démarrage ou un arrêt de bascule, le jeton est supprimé par `clearCsrfToken()`. Au prochain affichage, un nouveau jeton est généré. Cela limite la réutilisation d'un ancien formulaire.

### Schéma simplifié

```text
Session administrateur
        |
        +--> issueCsrfToken() crée un jeton
                 |
                 v
         formulaire HTML caché : swus_csrf=...
                 |
                 v
        POST reçu par le plugin
                 |
                 v
        validateCsrfToken()
          |           |
        OK            KO
          |           |
          v           v
      action      refus + message
```

### Partie du développement qui agit

| Fichier | Élément | Rôle |
|---|---|---|
| `classes/class.ilSwitchUserSecurity.php` | `issueCsrfToken(), validateCsrfToken(), clearCsrfToken()` | Crée, vérifie et réinitialise le jeton CSRF. |
| `classes/class.ilSwitchUserSearchPage.php` | `render(), renderResults()` | Insère le champ caché `swus_csrf` dans les formulaires. |
| `classes/class.ilSwitchUserUIHookGUI.php` | `hasValidCsrf(), handleAction(), buildActiveBanner()` | Refuse les actions sans jeton valide et place le jeton dans le bouton de retour. |
| `classes/class.ilSwitchUserConfigGUI.php` | `hasValidCsrf()` | Vérifie le jeton lors des recherches depuis la configuration. |
| `classes/class.ilSwitchUserAdminGUI.php` | `hasValidCsrf()` | Vérifie le jeton sur l'écran d'administration. |

## 6. Journalisation des événements sensibles

### Explication simple

- La journalisation sert à garder une trace des actions importantes ou anormales.
- Cela permet de savoir qui a tenté quoi, quand, et dans quel contexte. C'est utile pour l'audit, le support et l'analyse d'un incident.
- Pour un non-informaticien, c'est le journal de bord du plugin.

### Comment cela fonctionne au niveau du développement

- La fonction centrale est `ilSwitchUserSecurity::audit($event, $context = [])`.
- À chaque événement sensible, le plugin construit un message structuré contenant au minimum : le type d'événement, l'identité de l'acteur (`actor_user_id`, `actor_login`), l'utilisateur d'origine s'il existe, l'adresse IP distante et une version courte du navigateur (`user_agent`).
- Selon le cas, des informations complémentaires sont ajoutées : `target_user_id`, `target_login`, `restored_user_id`, longueur de recherche, méthode HTTP reçue, etc.
- Le message est envoyé en priorité au logger ILIAS (`ilLoggerFactory::getLogger('root')`). Si ce logger n'est pas disponible, le plugin retombe sur `error_log()` pour ne pas perdre totalement la trace.
- Les appels sont placés aux endroits clés : refus d'accès, CSRF invalide, mauvaise méthode HTTP, recherche exécutée, démarrage réussi, arrêt réussi, tentatives interdites.

### Schéma simplifié

```text
Événement sensible
(start, stop, refus, erreur CSRF...)
        |
        v
audit("nom_evenement", contexte)
        |
        v
Message JSON structuré
        |
        +--> logger ILIAS (si disponible)
        |
        +--> error_log() sinon
```

### Partie du développement qui agit

| Fichier | Élément | Rôle |
|---|---|---|
| `classes/class.ilSwitchUserSecurity.php` | `audit()` | Construit le message d'audit et l'envoie au système de logs. |
| `classes/class.ilSwitchUserUIHookGUI.php` | `openSearchPage(), handleAction(), handleStart(), handleStop()` | Déclenche la journalisation sur succès, refus et anomalies. |
| `Doc/SSI_changes.md` | `journalisation des événements de sécurité` | Documente le changement côté package. |

## 7. Régénération d'identifiant de session au début et à la fin de la bascule

### Explication simple

- Quand l'identité change, l'identifiant de session change aussi.
- L'idée est simple : on ne garde pas le même « badge de session » avant, pendant et après la bascule.
- Cela réduit le risque qu'un ancien identifiant de session soit réutilisé dans un contexte qui n'est plus le bon.

### Comment cela fonctionne au niveau du développement

- La fonction dédiée est `ilSwitchUserSecurity::regenerateSession()`. Elle appelle `session_regenerate_id(true)` si une session PHP est active.
- Au démarrage de la bascule, le plugin mémorise d'abord l'administrateur d'origine dans la session, remplace ensuite l'identité courante ILIAS (`AccountId` et `_authsession_user_id`) par l'utilisateur cible, puis régénère l'identifiant de session.
- Au retour, le plugin restaure les deux variables de session ILIAS avec l'identité d'origine, supprime les métadonnées de bascule, puis régénère à nouveau l'identifiant de session.
- Le fait de régénérer après mise à jour des variables de session permet de repartir sur une nouvelle session cohérente avec la nouvelle identité.

### Schéma simplifié

```text
Début de bascule
  1. mémoriser l'admin d'origine
  2. remplacer l'identité courante
  3. regenerateSession()

Fin de bascule
  1. restaurer l'admin d'origine
  2. supprimer les marqueurs de bascule
  3. regenerateSession()
```

### Partie du développement qui agit

| Fichier | Élément | Rôle |
|---|---|---|
| `classes/class.ilSwitchUserSecurity.php` | `SESSION_ACCOUNT_ID, SESSION_AUTH_USER_ID, regenerateSession()` | Gère les clés de session importantes et la rotation d'identifiant. |
| `classes/class.ilSwitchUserUIHookGUI.php` | `handleStart(), handleStop()` | Change l'identité en session puis déclenche la régénération. |

## 8. Refus de basculer vers un compte administrateur

### Explication simple

- Le plugin interdit maintenant de prendre l'identité d'un autre compte administrateur.
- Cette règle limite les usages les plus risqués, car un compte déjà très puissant ne doit pas être utilisé comme cible d'une bascule de support classique.
- Autrement dit : un administrateur peut aider un utilisateur standard, mais pas se « glisser » dans un autre administrateur.

### Comment cela fonctionne au niveau du développement

- Lors d'un démarrage de bascule, `handleStart()` récupère `user_id`, puis appelle `ilSwitchUserSecurity::isAdministrativeUserId($user_id)`.
- Cette fonction lit les rôles affectés à l'utilisateur ciblé via la couche RBAC d'ILIAS et vérifie la présence du `SYSTEM_ROLE_ID`.
- Si la cible est administratrice, la demande est refusée avant toute modification de session. Le plugin journalise l'événement (`start_denied_admin_target`) et affiche un message explicite à l'écran.
- Cette vérification complète les autres contrôles déjà présents : compte existant, actif, non anonyme et différent de l'utilisateur courant.

### Schéma simplifié

```text
Demande de démarrage
        |
        v
isAdministrativeUserId(user_id) ?
       / \
     oui  non
     /      \
refus       suite des autres contrôles
```

### Partie du développement qui agit

| Fichier | Élément | Rôle |
|---|---|---|
| `classes/class.ilSwitchUserSecurity.php` | `isAdministrativeUserId()` | Détermine si l'utilisateur ciblé possède un rôle administrateur. |
| `classes/class.ilSwitchUserUIHookGUI.php` | `handleStart()` | Bloque la bascule avant tout changement de session. |
| `lang/ilias_fr.lang / lang/ilias_en.lang` | `msg_admin_target_forbidden` | Porte le message utilisateur associé. |

## 9. Validation stricte des identifiants utilisateurs

### Explication simple

- Le plugin vérifie plus sévèrement le compte demandé avant de lancer la bascule.
- Cela évite les demandes incohérentes, les essais sur des comptes inexistants ou les retours vers un compte d'origine devenu introuvable.
- En clair, le plugin ne fait plus confiance à un identifiant juste parce qu'il a été envoyé dans un formulaire.

### Comment cela fonctionne au niveau du développement

- Le `user_id` reçu est d'abord converti en entier. Il doit être strictement supérieur à zéro.
- Ensuite, `ilObject::_exists($user_id, false, 'usr')` confirme que l'identifiant correspond bien à un objet utilisateur ILIAS.
- Le plugin refuse également l'utilisateur anonyme (`ANONYMOUS_USER_ID`) et vérifie que le compte est actif via `isUserActive()`, qui interroge `usr_data`.
- Une bascule vers soi-même est interdite, ce qui évite les faux positifs et les scénarios inutiles.
- Lors du retour, le plugin relit l'identifiant d'origine mémorisé en session et vérifie encore qu'il existe réellement avant de restaurer la session.
- La recherche elle-même reste prudente : le terme de recherche est échappé côté base de données et le nombre de résultats est limité à 50.

### Schéma simplifié

```text
user_id reçu
   |
   v
(entier > 0 ?)
   |
   v
existe comme utilisateur ILIAS ?
   |
   v
non anonyme ? actif ?
   |
   v
différent de l'utilisateur courant ?
   |
   v
action autorisable
```

### Partie du développement qui agit

| Fichier | Élément | Rôle |
|---|---|---|
| `classes/class.ilSwitchUserUIHookGUI.php` | `handleStart(), handleStop(), isUserActive()` | Applique les contrôles sur l'identifiant cible et sur le compte d'origine. |
| `classes/class.ilSwitchUserSecurity.php` | `getOriginalUserId()` | Relit proprement l'utilisateur d'origine stocké en session. |
| `classes/class.ilSwitchUserSearchPage.php` | `findUsers()` | Encadre la recherche utilisateur en base. |

## 10. Bannière persistante avec bouton de retour sécurisé

### Explication simple

- Quand la bascule est active, une bannière rouge reste visible dans l'interface pour rappeler que l'administrateur n'est plus sur son propre compte.
- Cette bannière contient un bouton de retour clair et sécurisé.
- Le but est double : éviter l'oubli de la bascule et rendre le retour au compte d'origine simple et fiable.

### Comment cela fonctionne au niveau du développement

- Le mécanisme principal est porté par `ilSwitchUserUIHookGUI::getHTML()`. À chaque affichage d'une page, le hook vérifie si une bascule est active.
- Si oui, il injecte une bannière HTML fixe en haut de page via `buildActiveBanner()`. Une garde statique (`$banner_rendered`) évite d'ajouter plusieurs fois la même bannière sur un même rendu.
- Le bouton de retour est un formulaire POST avec `op=stop` et `swus_csrf`, donc il bénéficie des mêmes protections que le démarrage.
- Un petit script JavaScript calcule la hauteur de la bannière et ajoute un `padding-top` au `body` ainsi qu'un `scrollPaddingTop` au document. Cela évite que le contenu soit masqué sous la bannière.
- Quand l'utilisateur ouvre l'écran SwitchUser pendant qu'une bascule est déjà active, `ilSwitchUserSearchPage::render()` affiche aussi un encart de retour sécurisé au lieu de proposer une nouvelle recherche.

### Schéma simplifié

```text
Page ILIAS affichée
        |
        v
getHTML() détecte une bascule active
        |
        v
Injection d'une bannière en haut
        |
        +--> message rappelant le compte d'origine
        |
        +--> bouton POST sécurisé : op=stop + CSRF
```

### Partie du développement qui agit

| Fichier | Élément | Rôle |
|---|---|---|
| `classes/class.ilSwitchUserUIHookGUI.php` | `getHTML(), buildActiveBanner()` | Affiche la bannière globale sur les pages ILIAS. |
| `classes/class.ilSwitchUserSearchPage.php` | `render()` | Affiche un encart de retour si l'écran SwitchUser est rouvert en cours de bascule. |
| `classes/class.ilSwitchUserSecurity.php` | `isImpersonationActive(), getOriginalUserId(), getOriginalUserLogin()` | Fournit l'état de bascule et les informations à afficher. |

## 11. Ajout d'un accès rapide visible dans l'interface (header) pour les administrateurs

### Explication simple

- Le plugin ajoute maintenant un accès rapide directement dans la barre du haut d'ILIAS.
- Cela évite de devoir repasser systématiquement par l'administration des plugins pour ouvrir SwitchUser.
- Pour les administrateurs, l'outil devient plus visible, plus simple à atteindre et plus naturel à utiliser.

### Comment cela fonctionne au niveau du développement

- Au démarrage du plugin, `ilSwitchUserPlugin::init()` enregistre un fournisseur d'élément de MetaBar si le service `globalScreen` est disponible.
- `ilSwitchUserMetaBarProvider::getMetaBarItems()` déclare ensuite une entrée native de barre supérieure avec : une action (`searchUrl()`), une icône SVG personnalisée, un titre et une position.
- La visibilité de cette entrée est dynamique : elle est proposée si l'utilisateur courant est administrateur ou si une bascule est déjà active. Ce deuxième cas est utile pour retrouver rapidement l'écran SwitchUser pendant une session de bascule.
- L'ancien provider de menu principal reste neutralisé pour éviter des problèmes de bootstrap sur des écrans sensibles ; l'accès rapide passe donc par la MetaBar du header.

### Schéma simplifié

```text
Chargement du plugin
        |
        v
init() enregistre le MetaBarProvider
        |
        v
Header ILIAS
        |
        +--> icône SwitchUser visible pour admin
        |
        +--> clic -> ouverture de l'écran SwitchUser
```

### Partie du développement qui agit

| Fichier | Élément | Rôle |
|---|---|---|
| `classes/class.ilSwitchUserPlugin.php` | `init()` | Déclare le provider du header. |
| `classes/class.ilSwitchUserMetaBarProvider.php` | `getMetaBarItems(), isVisibleForCurrentSession()` | Crée l'entrée visible dans la barre du haut. |
| `templates/images/switchuser_switch.svg` | `icône` | Support visuel de l'accès rapide. |
| `classes/class.ilSwitchUserMainBarProvider.php` | `provider neutralisé` | Rappelle que l'accès retenu n'est plus le menu latéral historique. |

## 12. Conclusion

La version 2.0.0 de SwitchUser ne change pas seulement l'ergonomie du plugin. Elle renforce surtout la sécurité autour d'une action très sensible : prendre temporairement l'identité d'un autre compte.

Les changements les plus structurants sont la séparation entre affichage et action, le passage en POST, le contrôle CSRF, la journalisation, la rotation d'identifiant de session et le refus des cibles administrateur.

En parallèle, l'ajout d'une bannière persistante et d'un accès rapide dans le header améliore l'usage quotidien sans affaiblir les contrôles.