# SwitchUser - Documentation technique v2.0.0

**Plugin ILIAS :** `swus`  
**Type :** `UserInterfaceHook` plugin  
**Compatibilité déclarée :** ILIAS `10.0` à `10.999`  
**Archive analysée :** `SwitchUser-release_10.zip`  
**Version documentée :** `2.0.0`  
**Objet :** bascule d'identité temporaire d'un utilisateur avec restauration contrôlée du compte d'origine

> Cette documentation décrit le comportement **effectivement observé dans l'archive 2.0.0**. Elle tient compte des nouveaux mécanismes de durcissement SSI introduits dans cette version, ainsi que des éléments encore présents dans le paquet mais non indispensables au flux nominal.

---

## 1. Objet et périmètre

SwitchUser est un plugin d'assistance pour ILIAS 10 permettant à un administrateur autorisé de prendre temporairement l'identité d'un autre utilisateur, puis de revenir au compte d'origine.

La version 2.0.0 fait évoluer la base 1.0.1 sur cinq axes structurants :

1. **abandon des actions sensibles en GET** au profit de **formulaires POST** ;
2. **protection CSRF** via un jeton stocké en session ;
3. **journalisation** des événements sensibles et des refus ;
4. **régénération d'identifiant de session** au début et à la fin de la bascule ;
5. **ajout d'un accès rapide dans le header** via un provider MetaBar.

Le périmètre couvre :

- la déclaration du plugin ;
- le routage `goto` ;
- la recherche d'utilisateurs ;
- le démarrage et l'arrêt de la bascule ;
- l'intégration dans l'interface ILIAS ;
- les contrôles de sécurité réellement implémentés ;
- les composants hérités encore embarqués dans l'archive.

---

## 2. Synthèse de la version 2.0.0

### 2.1 Évolutions majeures

| Sujet | v1.0.1 | v2.0.0 |
|---|---|---|
| Route d'action | `goto.php?target=swus&user_id=<id>` | `goto.php?target=swus_action` en `POST` avec `op=start/stop` |
| Recherche | ouverture par `swus_open`, action de bascule ensuite en GET | ouverture par `swus_open`, recherche et action toutes deux sécurisées en POST |
| CSRF | absent | jeton de session `switch_user_csrf_token` exigé |
| Audit | messages écran uniquement | journalisation des événements sensibles |
| Session | réécriture des identifiants de session | réécriture + `session_regenerate_id(true)` au départ et au retour |
| Cibles interdites | anonyme, invalides, auto-ciblage | mêmes contrôles + refus explicite des comptes administrateurs |
| Intégration UI | accès config + flux `goto` | accès config + flux `goto` + icône header MetaBar |

### 2.2 Résumé technique

La 2.0.0 introduit un **service transversal `ilSwitchUserSecurity`** qui centralise :

- la gestion du jeton CSRF ;
- les contrôles d'autorisation ;
- la détection d'une bascule active ;
- les URL normalisées `swus_open` et `swus_action` ;
- l'audit applicatif ;
- les libellés de secours bilingues.

Le **contrôleur `ilSwitchUserUIHookGUI`** reste le point névralgique à l'exécution. Il pilote le routage `goto`, les validations préalables, la mutation de la session et l'injection du bandeau de retour.

---

## 3. Vue d'ensemble de l'architecture

### 3.1 Composants principaux

- **Déclaration plugin - `plugin.php`**  
  Déclare l'ID `swus`, la version `2.0.0` et la compatibilité ILIAS 10. Il s'agit de la couche de packaging.

- **Classe plugin - `classes/class.ilSwitchUserPlugin.php`**  
  Déclare le nom du plugin, la classe de configuration et les constantes de routage. Enregistre le provider MetaBar si `globalScreen` est disponible.

- **Routeur / UI hook - `classes/class.ilSwitchUserUIHookGUI.php`**  
  Gère `swus_open`, `swus_action`, le démarrage / arrêt de la bascule et le bandeau actif. C'est le composant central.

- **Sécurité transverse - `classes/class.ilSwitchUserSecurity.php`**  
  Centralise CSRF, audit, sessions, URLs, vérification admin et textes de secours. C'est le nouveau pivot de la v2.0.0.

- **Recherche / rendu HTML - `classes/class.ilSwitchUserSearchPage.php`**  
  Rend l'écran de recherche et le tableau des résultats. Recherche SQL directe sur `usr_data`.

- **Configuration plugin - `classes/class.ilSwitchUserConfigGUI.php`**  
  Point d'entrée via l'écran "Configurer" du plugin. Utilise aussi POST + CSRF.

- **Icône header - `classes/class.ilSwitchUserMetaBarProvider.php`**  
  Ajoute un accès rapide dans la MetaBar. Visible pour administrateur ou session de bascule active.

- **Contrôleur hérité - `classes/class.ilSwitchUserAdminGUI.php`**  
  Contrôleur alternatif réutilisant `SearchPage`. Encore embarqué mais non indispensable au flux nominal.

- **Provider menu principal - `classes/class.ilSwitchUserMainBarProvider.php`**  
  Stub neutralisé conservé pour compatibilité. Retourne des tableaux vides.

- **Traductions - `lang/ilias_fr.lang`, `lang/ilias_en.lang`**  
  Fournissent les libellés plugin. `Security` possède aussi un catalogue de secours intégré.

- **Ressource graphique - `templates/images/switchuser_switch.svg`**  
  Icône du header utilisée par le provider MetaBar.

### 3.2 Arborescence du paquet analysé

```text
SwitchUser-release_10/
|-- Doc/
|   `-- SSI_changes.md
|-- README.md
|-- plugin.php
|-- switchuser_icon.svg
|-- classes/
|   |-- class.ilSwitchUserAdminGUI.php
|   |-- class.ilSwitchUserConfigGUI.php
|   |-- class.ilSwitchUserMainBarProvider.php
|   |-- class.ilSwitchUserMetaBarProvider.php
|   |-- class.ilSwitchUserPlugin.php
|   |-- class.ilSwitchUserSearchPage.php
|   |-- class.ilSwitchUserSecurity.php
|   |-- class.ilSwitchUserUIHookGUI.php
|   `-- class.ilSwitchUserUIHookGUI.php.bak
|-- lang/
|   |-- ilias_en.lang
|   `-- ilias_fr.lang
`-- templates/
    `-- images/
        `-- switchuser_switch.svg
```

### 3.3 Dépendances fonctionnelles

Le flux nominal s'appuie sur la chaîne suivante :

`Plugin -> UIHook -> Security -> SearchPage -> session ILIAS -> redirection dashboard`

L'icône du header est indépendante du contrôleur de configuration et dépend uniquement de :

- la disponibilité de `globalScreen` ;
- l'enregistrement du `MetaBarProvider` par `ilSwitchUserPlugin::init()`.

---

## 4. Points d'entrée et routage

### 4.1 Cibles reconnues par `gotoHook`

Le hook ne reconnaît plus l'ancienne cible `swus`. Les cibles actives sont :

| Cible | URL type | Méthode attendue | Rôle |
|---|---|---|---|
| `swus_open` | `goto.php?target=swus_open` | `GET` pour ouvrir, `POST` pour rechercher | Ouvre l'interface SwitchUser et traite la recherche |
| `swus_action` | `goto.php?target=swus_action` | `POST` uniquement | Exécute `start` ou `stop` |
| Configuration plugin | `Administration > Étendre ILIAS > Plugins > SwitchUser > Configurer` | `GET` puis `POST` interne `ilCtrl` | Point d'entrée alternatif |
| Icône header | entrée MetaBar | ouvre `swus_open` | Accès rapide pour administrateur ou bascule active |

### 4.2 Contrat de la route d'action

La route `swus_action` attend :

- `op=start` avec `user_id` pour démarrer une bascule ;
- `op=stop` pour restaurer le compte d'origine ;
- `swus_csrf` dans tous les cas.

Toute requête non `POST`, sans jeton valide ou avec `op` inconnu est rejetée avec message utilisateur et entrée d'audit.

### 4.3 Séquence nominale de démarrage

1. L'administrateur ouvre SwitchUser depuis la configuration du plugin ou l'icône du header.
2. Une recherche utilisateur est lancée en `POST`.
3. `ilSwitchUserSearchPage` interroge `usr_data` et affiche jusqu'à 50 résultats.
4. Le bouton d'action d'une ligne envoie un formulaire `POST` vers `swus_action` avec :
   - `op=start`
   - `user_id`
   - `swus_csrf`
5. `handleStart()` vérifie l'état courant, l'autorisation, l'existence et l'éligibilité de la cible.
6. Le plugin mémorise l'utilisateur d'origine, remplace `AccountId` et `_authsession_user_id`, régénère l'ID de session, invalide le jeton CSRF courant et redirige vers le tableau de bord.
7. Pendant la navigation, `getHTML()` injecte un bandeau fixe contenant un bouton de retour sécurisé.

### 4.4 Séquence de restauration

1. Le bouton **Revenir au compte d'origine** envoie `op=stop` en `POST` vers `swus_action`.
2. `handleStop()` vérifie qu'une bascule est active et que l'utilisateur source existe toujours.
3. Le plugin restaure `AccountId` et `_authsession_user_id`.
4. Les métadonnées de bascule sont supprimées de la session.
5. L'identifiant de session est régénéré.
6. Le jeton CSRF est supprimé puis régénéré à la prochaine vue.
7. L'utilisateur est redirigé vers le tableau de bord avec un message de succès.

### 4.5 Cas particulier : session de bascule déjà active

Quand une bascule est active :

- la page `swus_open` reste accessible ;
- l'interface affichée masque la recherche ;
- seul le panneau de retour sécurisé est rendu.

Cela permet de retrouver un point de restauration même si l'utilisateur courant n'est plus administrateur au sens RBAC.

---

## 5. Gestion de session et sécurité

### 5.1 Clés de session manipulées

- **`AccountId`** - *session ILIAS*  
  Identité métier active. Remplacée par la cible au start puis par la source au stop.

- **`_authsession_user_id`** - *session ILIAS*  
  Identité authentifiée effective. Synchronisée avec `AccountId`.

- **`switch_user_original_user_id`** - *plugin*  
  Mémorise le compte source. Créée au start, supprimée au stop ou lors d'un nettoyage forcé.

- **`switch_user_original_user_login`** - *plugin*  
  Sert à l'affichage du compte source. Créée au start, supprimée au stop.

- **`switch_user_started_at`** - *plugin*  
  Horodatage de début de bascule. Créée au start, supprimée au stop.

- **`switch_user_csrf_token`** - *plugin*  
  Jeton anti-CSRF. Créé à la demande puis supprimé après start/stop.

### 5.2 Contrôles de sécurité implémentés

| Contrôle | Implémentation | Effet |
|---|---|---|
| POST obligatoire sur l'action | `handleAction()` refuse toute méthode différente de `POST` | supprime la surface d'attaque liée aux actions de bascule en GET |
| CSRF requis | `validateCsrfToken()` avec comparaison `hash_equals()` | protège la recherche sécurisée et les opérations start/stop |
| Réservation aux administrateurs | `isAdministrativeUser()` avec `ilUtil::checkAdmin()` puis fallback RBAC | limite la recherche et le démarrage aux comptes habilités |
| Refus des comptes admin comme cible | `isAdministrativeUserId()` sur les rôles assignés | évite l'usurpation d'un autre compte privilégié |
| Refus des comptes anonymes / inactifs | contrôle `ANONYMOUS_USER_ID` + requête SQL `active=1` | empêche les bascules illégitimes |
| Interdiction de s'auto-cibler | comparaison avec l'utilisateur courant | évite les faux positifs et usages inutiles |
| Interdiction de chaîner les bascules | test `isImpersonationActive()` avant start | une seule bascule active à la fois |
| Régénération de session | `session_regenerate_id(true)` au start et au stop | réduit les risques de fixation de session |
| Nettoyage local de secours | `forceLocalCleanup()` si restauration impossible | supprime les métadonnées plugin incohérentes |
| Messages d'interface | `setOnScreenMessage()` | retour fonctionnel immédiat côté UI |

### 5.3 Journalisation applicative

La méthode `ilSwitchUserSecurity::audit()` construit une charge JSON préfixée par `[SwitchUser]` et y ajoute notamment :

- `event`
- `actor_user_id`
- `actor_login`
- `original_user_id`
- `remote_ip`
- `user_agent` tronqué à 180 caractères
- les éventuels paramètres de contexte

La trace est envoyée :

1. vers `ilLoggerFactory::getLogger('root')->info(...)` si disponible ;
2. sinon vers `error_log()`.

Événements observés dans le code :

- `open_denied_not_admin`
- `search_denied_bad_csrf`
- `search_performed`
- `action_denied_wrong_method`
- `action_denied_bad_csrf`
- `action_denied_unknown_op`
- `start_denied_already_active`
- `start_denied_not_admin`
- `start_denied_user_not_found`
- `start_denied_inactive_or_anonymous`
- `start_denied_same_user`
- `start_denied_admin_target`
- `start_success`
- `stop_denied_not_active`
- `stop_denied_original_user_missing`
- `stop_success`

**Point notable :** l'audit de recherche journalise la **longueur** du terme (`query_length`) et non le contenu complet, ce qui limite l'exposition de données dans les logs.

### 5.4 Nuance importante sur l'audit

Le flux `goto.php?target=swus_open` journalise explicitement `search_performed`.  
Le flux de recherche lancé depuis la **page de configuration** (`ilCtrl`) applique bien POST + CSRF, mais n'ajoute pas d'événement d'audit spécifique pour la recherche elle-même. Les événements de start/stop, eux, restent audités dans tous les cas car ils passent par `swus_action`.

---

## 6. Interface et intégration ILIAS

### 6.1 Écran de recherche

`ilSwitchUserSearchPage` rend un écran simple avec :

- titre et description ;
- note de sécurité visible ;
- champ de recherche texte ;
- bouton primaire de recherche ;
- tableau des résultats avec un formulaire `POST` par ligne.

Le rendu est volontairement léger et directement HTML, sans composant ILIAS plus élaboré.

### 6.2 Icône dans le header

La nouveauté visible de la 2.0.0 est l'ajout d'un provider MetaBar :

- classe : `ilSwitchUserMetaBarProvider`
- icône : `templates/images/switchuser_switch.svg`
- taille : `large`
- position : `1`

Le lien apparaît dans la barre supérieure si l'une des conditions suivantes est vraie :

- l'utilisateur courant est administrateur ;
- une bascule est active.

Cette approche remplace avantageusement les essais antérieurs de provider de menu principal, laissés dans l'archive sous forme de stub neutralisé.

### 6.3 Bandeau pendant la bascule

Le bandeau actif est injecté par `getHTML()` :

- une seule fois par réponse grâce à la variable statique `$banner_rendered` ;
- sous forme de bloc fixe `position: fixed; top: 0;`;
- avec formulaire `POST` de retour sécurisé ;
- avec script JS ajustant `padding-top` du `body` pour éviter le recouvrement du contenu de page.

Par rapport à la v1.0.1 :

- le bandeau ne repose plus sur un calcul de sélecteurs de header ILIAS ;
- l'offset appliqué vise le **contenu** de page plutôt que le header lui-même ;
- le retour passe désormais par `POST + CSRF`.

### 6.4 Langues et repli

Le plugin possède :

- des fichiers `lang/ilias_fr.lang` et `lang/ilias_en.lang` ;
- un **catalogue de secours intégré** dans `ilSwitchUserSecurity`.

Si les traductions du plugin ne sont pas résolues, le service tente :

1. le langage du `DIC`,
2. des indices de requête / session / navigateur,
3. le catalogue FR/EN embarqué.

---

## 7. Moteur de recherche utilisateur

### 7.1 Critères et limitations

La recherche interroge directement `usr_data` avec les règles suivantes :

- champs : `login`, `firstname`, `lastname`, `email`
- filtre : `active = 1`
- exclusion : `usr_id != ANONYMOUS_USER_ID`
- tri : `ORDER BY login ASC`
- limite pratique : **50 résultats**, appliquée côté boucle PHP et non via `LIMIT` SQL

### 7.2 Logique de requête

```sql
SELECT usr_id, login, firstname, lastname, email, active
FROM usr_data
WHERE active = 1
  AND usr_id != ANONYMOUS_USER_ID
  AND (
        login LIKE %term%
     OR firstname LIKE %term%
     OR lastname LIKE %term%
     OR email LIKE %term%
  )
ORDER BY login ASC
```

Le terme est intégré via l'API base de données ILIAS (`escape()` puis `quote()`), ce qui évite l'injection SQL triviale.

### 7.3 Conséquences techniques

Cette implémentation reste :

- **simple et lisible** ;
- **fortement couplée** au schéma `usr_data` ;
- **sans pagination** ;
- **dupliquée conceptuellement** entre plusieurs points d'entrée qui réutilisent le même renderer.

Le durcissement 2.0.0 ne change pas ce moteur de recherche sur le fond ; il sécurise surtout la manière dont les actions partent ensuite vers la bascule.

---

## 8. Installation, exploitation et mise à jour

### 8.1 Déploiement

1. Copier le plugin dans :

   ```text
   public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SwitchUser
   ```

2. Depuis la racine ILIAS :

   ```bash
   composer du
   ```

3. Dans ILIAS :

   ```text
   Administration > Étendre ILIAS > Plugins
   ```

4. Installer puis activer **SwitchUser**.

### 8.2 Accès opérationnel

Accès possibles :

- **Configuration du plugin**  
  `Administration > Étendre ILIAS > Plugins > SwitchUser > Configurer`
- **Icône header** si `globalScreen` est disponible et si la session est éligible
- **Route directe**  
  `goto.php?target=swus_open`

### 8.3 Mise à jour

Pour remplacer une version existante :

1. sauvegarder l'ancienne version ;
2. remplacer le dossier du plugin ;
3. relancer `composer du` ;
4. vider l'opcache si nécessaire ;
5. vérifier :
   - ouverture de l'écran,
   - recherche,
   - démarrage de bascule,
   - retour au compte d'origine,
   - présence de l'icône header.

---

## 9. Stratégie de test recommandée

### 9.1 Scénarios minimaux

| Scénario | Attendu |
|---|---|
| Ouverture via l'icône header | l'écran SwitchUser s'ouvre sans erreur |
| Recherche valide | le bon utilisateur apparaît dans le tableau |
| Démarrage nominal | redirection dashboard + message de succès |
| Bandeau actif | panneau rouge fixe avec bouton de retour |
| Retour nominal | restauration du compte source + message de succès |
| Requête GET sur `swus_action` | refus + message "POST only" |
| CSRF invalide | refus + audit dédié |
| Cible administrateur | refus explicite |
| Cible inactive ou anonyme | refus explicite |
| Auto-ciblage | refus explicite |
| Session déjà en bascule | deuxième start refusé |

### 9.2 Vérifications SSI complémentaires

- contrôler la présence des traces `[SwitchUser]` dans les logs ILIAS ou PHP ;
- vérifier que l'ID de session change au start puis au stop ;
- vérifier la disparition de `switch_user_original_*` après restauration ;
- tester l'accès `swus_open` pendant la bascule et confirmer que seule l'action de retour est proposée.

---

## 10. Dette technique et points d'attention

### 10.1 Héritage encore présent

- `ilSwitchUserAdminGUI` reste embarqué alors que le flux nominal repose surtout sur `ConfigGUI` et `UIHookGUI`.
- `ilSwitchUserMainBarProvider` est un stub de compatibilité.
- `class.ilSwitchUserUIHookGUI.php.bak` est toujours livré dans l'archive.
- `switchuser_icon.svg` à la racine n'est pas référencé par le code observé.

### 10.2 Recommandations de rationalisation

1. **Retirer du paquet** les artefacts inutilisés (`.bak`, icône non référencée).
2. **Unifier** clairement les deux parcours de recherche (configuration `ilCtrl` et `goto swus_open`) si un seul doit rester de référence.
3. **Paginer** ou limiter côté SQL si le volume d'utilisateurs devient important.
4. **Ajouter** éventuellement un journal d'audit métier plus formalisé si la politique SSI locale l'exige.
5. **Documenter** explicitement le fait que l'icône header dépend de `globalScreen`.

---

## 11. Annexe - Comparatif v1.0.1 vers v2.0.0

| Sujet | v1.0.1 | v2.0.0 |
|---|---|---|
| Route d'action principale | `swus` | `swus_action` |
| Verbe HTTP de bascule | GET | POST |
| Jeton CSRF | non | oui |
| Classe de sécurité dédiée | non | `ilSwitchUserSecurity` |
| Journalisation | non persistante | audit technique vers logger / `error_log` |
| Régénération de session | non | oui |
| Refus des cibles admin | non documenté / absent | oui |
| Icône d'accès rapide | non | oui, via MetaBar |
| Bandeau de retour | lien/retour ancienne logique | bouton POST sécurisé |
| Calcul d'affichage du bandeau | logique plus dépendante du thème | logique plus simple, centrée sur le contenu |
| Éléments résiduels | provider neutralisé, classes héritées | mêmes héritages + `.bak` encore présent |

---

## Conclusion technique

La version **2.0.0** constitue une évolution nette vers un **mode d'impersonation plus sûr**, principalement grâce au passage en **POST**, au **jeton CSRF**, à la **journalisation** et à la **régénération de session**.

Sur le plan d'architecture, le socle reste volontairement compact :

- un routeur `UIHook` central ;
- un service `Security` transverse ;
- un renderer HTML simple ;
- une intégration ILIAS légère via configuration plugin et MetaBar.

La release est donc **fonctionnellement plus mature** que la 1.0.1, tout en conservant quelques éléments de packaging à nettoyer lors d'un prochain cycle.
