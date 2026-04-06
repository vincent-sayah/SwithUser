# SwitchUser - ILIAS 10

Version 2.0.0 corrigée et durcie du plugin SwitchUser.

## Principales corrections

- abandon des actions de bascule en GET au profit d'actions POST ;
- ajout d'un jeton CSRF côté session ;
- journalisation des événements sensibles ;
- régénération d'identifiant de session au début et à la fin de la bascule ;
- refus de basculer vers un compte administrateur ;
- suppression des fichiers de sauvegarde `.bak` ;
- validation stricte des identifiants utilisateurs ;
- bannière persistante avec bouton de retour sécurisé;
- ajout d’un accès rapide visible dans l’interface (header) pour les administrateurs.

## Présentation

**SwitchUser** est un plugin pour **ILIAS 10** permettant à un administrateur autorisé de se connecter temporairement avec l’identité d’un autre utilisateur.

Ce plugin est destiné aux opérations de **support**, de **diagnostic**, de **vérification des droits** et d’**assistance fonctionnelle**. Il permet également de revenir simplement au compte d’origine grâce à un lien de restauration affiché pendant la bascule de compte.

---

## Fonctionnalités

- Recherche d’un utilisateur par login, nom ou adresse e-mail
- Bascule temporaire vers le compte sélectionné
- Utilisation réelle des droits du compte ciblé
- Retour rapide et sécurisé au compte administrateur d’origine
- Interface simple intégrée à l’administration des plugins ILIAS

---

## Cas d’usage

SwitchUser est particulièrement utile pour :

- reproduire un problème rencontré par un utilisateur ;
- vérifier la visibilité d’un cours, d’un groupe ou d’une ressource ;
- contrôler les permissions effectivement appliquées ;
- assister un utilisateur sans demander son mot de passe ;
- valider le comportement d’un rôle ou d’un profil donné.

---

## Public concerné

Ce plugin est destiné exclusivement aux :

- administrateurs ILIAS ;
- équipes support ;
- responsables techniques habilités.

Il ne doit pas être utilisé par des utilisateurs standards.

---

## Prérequis

- **ILIAS 10**
- Plugin installé dans le répertoire des plugins UIHook d’ILIAS
- Compte disposant des autorisations nécessaires pour utiliser le plugin

---

## Installation

1. Copier le dossier du plugin dans le répertoire suivant et donner les droits à l’utilisateur web :

   ```text
   public/Customizing/global/plugins/Services/UIComponent/UserInterfaceHook/SwitchUser
   ```

2. Depuis la racine du projet ILIAS, exécuter la commande suivante :

   ```bash
   composer du
   ```

3. Se connecter à l’interface d’administration ILIAS.

4. Aller dans :

   **Administration > Etendre ILIAS > Plugins**

5. Installer le plugin **SwitchUser**.

6. Activer le plugin.

---

## Accès au plugin

Une fois le plugin activé, l’accès se fait depuis :

**Administration > Etendre ILIAS > Plugins > SwitchUser > Configurer**
ou
**Via l'icone accessible dans le bandeau du haut.**

---

## Configuration

Le plugin ne nécessite pas de configuration.

L’écran de configuration permet principalement de :

- rechercher un utilisateur ;
- afficher les résultats dans un tableau ;
- lancer la bascule sur le compte choisi.

---

## Utilisation

### 1. Rechercher un utilisateur

Dans l’écran de recherche :

1. saisir un login, un nom ou une adresse e-mail dans le champ prévu ;
2. lancer la recherche ;
3. consulter la liste des résultats proposés.

### 2. Se connecter en tant qu’un autre utilisateur

Dans le tableau des résultats :

1. repérer le compte souhaité ;
2. cliquer sur le bouton d’action correspondant ;
3. la session bascule alors sur le compte sélectionné.

À partir de cet instant, vous naviguez dans ILIAS avec les **droits réels** de l’utilisateur ciblé.

### 3. Revenir au compte d’origine

Pendant la bascule de compte, un message est affiché dans l’interface indiquant qu’une bascule est active.

Pour revenir au compte initial :

1. cliquer sur le lien **Revenir au compte d’origine** ;
2. la session administrateur initiale est restaurée automatiquement.

---

## Comportement attendu

Lorsque la bascule de compte est active :

- le compte administrateur d’origine est temporairement suspendu ;
- seules les permissions du compte ciblé s’appliquent ;
- les droits d’administration ne sont pas conservés pendant la bascule ;
- le retour au compte initial reste possible via le lien prévu à cet effet.

---

## Sécurité et bonnes pratiques

L’utilisation de SwitchUser doit être strictement réservée aux personnes autorisées.

Il est recommandé de :

- utiliser le plugin uniquement pour des besoins légitimes de support ou de contrôle ;
- éviter toute action non nécessaire pendant la bascule ;
- revenir au compte d’origine dès la fin de l’intervention ;
- intégrer l’usage du plugin dans les règles internes de sécurité et de traçabilité ;
- informer les utilisateurs si votre politique interne l’exige.

---

## Limitations

- Le plugin repose sur un mécanisme de bascule de session.
- Les actions réalisées pendant la bascule sont effectuées dans le contexte du compte ciblé.
- Si l’utilisateur ciblé ne possède pas un accès ou un droit particulier, l’administrateur ne l’aura pas non plus durant la bascule.
- Le plugin ne remplace pas une politique de gestion des habilitations ni un dispositif d’audit organisationnel.

---

## Dépannage

### Aucun utilisateur n’est trouvé

Vérifier que la recherche porte sur un login, un nom ou une adresse e-mail existante.

### La bascule fonctionne mais l’utilisateur ne voit pas certains contenus

Vérifier les rôles, permissions et affectations du compte ciblé dans ILIAS.

### Le lien de retour n’apparaît pas

Vérifier que la bascule de compte est bien active et que le plugin est correctement installé et activé.

### Le plugin n’apparaît pas dans ILIAS

Vérifier :
- l’emplacement exact du dossier du plugin ;
- le nom du dossier ;
- l’exécution de `composer du` ;
- l’activation du plugin dans l’administration ILIAS.

---

## Maintenance

En cas de mise à jour du plugin :

1. sauvegarder la version actuelle ;
2. remplacer les fichiers du plugin ;
3. exécuter de nouveau :

   ```bash
   composer du
   ```

4. vérifier l’état du plugin dans l’administration ILIAS ;
5. tester la recherche, la bascule et le retour au compte d’origine.

---

## Support

vince.syh@free.fr
