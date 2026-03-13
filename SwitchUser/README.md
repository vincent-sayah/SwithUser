# SwitchUser pour ILIAS 10

Ce package est une version renommée et compatible **ILIAS 10** du plugin de prise de contrôle de session utilisateur.

## Nom et identifiant technique

- **Nom du plugin** : `SwitchUser`
- **Identifiant technique** : `swus`
- **Dossier du plugin** : `SwitchUser`
- **Classe plugin** : `ilSwitchUserPlugin`


## Installation

Placer le dossier et donner les permissions web:

`public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SwitchUser`

Puis exécuter depuis la racine ILIAS :

```bash
composer du
```

Ensuite, dans ILIAS :

`Administration -> Extending ILIAS -> Plugins`

Installer puis activer **SwitchUser**.

## Fonctionnalités

- recherche d’utilisateurs par login, prénom, nom ou e-mail ;
- démarrage d’une impersonation via `goto.php?target=swus&user_id=<ID>` ;
- retour au compte d’origine via un lien sur le  bandeau du haut ;
- restauration de la session d’origine avec les variables de session ILIAS.

