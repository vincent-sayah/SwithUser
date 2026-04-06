# Changements de sécurité appliqués

1. Recherche d'utilisateur conservée mais action de bascule déplacée vers un formulaire POST.
2. Contrôle CSRF appliqué à la recherche depuis la page dédiée et aux actions start/stop.
3. Journalisation des événements de sécurité : ouverture refusée, recherche, démarrage, arrêt, erreurs CSRF, méthode invalide.
4. Refus explicite des cibles administrateur et des comptes anonymes / inactifs.
5. Régénération d'identifiant de session lors du changement d'identité et lors du retour.
6. Nettoyage des métadonnées de session du plugin après retour.
7. Suppression du fichier de sauvegarde de code source `.bak`.
