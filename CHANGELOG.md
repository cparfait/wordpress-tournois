# Journal des versions

Toutes les versions notables de Brackethive.
Le format suit celui du fichier `readme.txt` de l'extension.

## 2.6.0
* L'extension est renommée **Brackethive Tournament Manager**. L'ancien nom reprenait une marque déposée, ce que le répertoire officiel n'autorise pas.
* Tout ce que l'extension déclare ou enregistre porte désormais le préfixe `brackethive_` : classes, constantes, options, tables, métadonnées, codes courts et identifiants de scripts. Le règlement exige au moins quatre caractères ; `wgt_` n'en avait que trois.
* Les sites existants ne perdent rien : tables, réglages, tournois et pages d'inscription sont renommés automatiquement au premier chargement suivant la mise à jour, et les anciens codes courts `[wegame_*]` continuent de fonctionner.
* Correction : l'écran de réglages provoquait une erreur fatale dans le paquet distribué par le répertoire officiel, d'où le module de mise à jour auto-hébergée est retiré.
* Les styles et scripts de l'écran d'aperçu passent par l'API d'enregistrement de WordPress.
* Les catalogues de traduction compilés ne sont plus inclus dans le paquet destiné au répertoire : les traductions y sont fournies par translate.wordpress.org.


## 2.4.3
* Correction : les QR codes n'apparaissaient pas. Ils sont désormais calculés par le serveur et affichés en SVG dans la page, sans dépendre d'un script : ils restent visibles même avec une extension de cache qui regroupe ou diffère les fichiers JavaScript.
* Les QR codes se téléchargent en PNG (environ 600 pixels de côté) ou en SVG vectoriel.


## 2.4.2
* QR codes téléchargeables (PNG et SVG) pour la page du tournoi et la page d'inscription, générés dans le navigateur sans service extérieur.
* Bouton « Créer une page d'inscription dédiée » : une page WordPress publiée ne contenant que le formulaire simplifié, avec son propre lien et son QR code. Également proposé à la fin de l'assistant de création.
* Bouton « Copier » à côté de chaque adresse.
* La section Codes courts explique comment créer une page, y coller le code court et la publier.
* La page d'inscription créée par l'extension est supprimée avec son tournoi.


## 2.4.1
* Assistant : option « Pas de page dédiée » (codes courts sur vos propres pages) ; tableau de bord : bloc « Liens publics » avec l’adresse de la page du tournoi, du formulaire d’inscription et la liste des pages du site utilisant les codes courts.
* Écran Équipes : le formulaire d’ajout est au-dessus de la liste, champs en colonnes.
* Titre de page : sur les pages de tournoi, le titre rendu par le thème est vidé quel que soit le thème (en plus du masquage CSS), sans toucher au titre de l’onglet du navigateur.
* Régénération forcée des permaliens à la mise à jour.


## 2.4.0
* Correction : en double élimination, le repêchage restait vide dès que l'effectif n'était pas une puissance de deux.
* Correction : les poules de tailles inégales généraient des matchs à une seule équipe et bloquaient la qualification.
* Correction : l'option « seconde grande finale » n'était pas enregistrée.
* Correction : la correction d'un résultat n'invalidait pas toujours le match suivant.
* Correction : croisement à deux poules, les premiers de poule ne se rencontrent plus avant la finale.
* Correction : le classement général tient compte de la seconde grande finale.
* Correction : planning du repêchage et de la petite finale replacés dans l'ordre de jeu.
* Correction : pages de tournoi en erreur 404 juste après l'activation.
* Sécurité : les tournois en brouillon ou privés ne sont plus visibles publiquement.
* Sécurité : le manifeste de mise à jour doit être servi en HTTPS ; empreinte SHA-256 optionnelle du paquet.
* Sauvegarde : restauration transactionnelle, identifiants remappés, poules manuelles incluses.
* Inscription : détection de l'adresse IP derrière un proxy, consentement vérifié côté serveur.
* Affichage : l'en-tête n'apparaît plus en double après le rafraîchissement automatique ; `fit="width"` fonctionne ; impression lisible.
* Affichage : heure de dernière mise à jour et état « hors ligne » sous les vues rafraîchies automatiquement ; espacement des tentatives après plusieurs échecs.
* Documentation : codes courts [brackethive_tournois], [brackethive_poules] et [brackethive_classement] et attribut `tournoi="slug"` documentés.
* Correction : en BO3/BO5, les manches en trop et les manches à égalité sont refusées ; une saisie refusée ne supprime plus les manches déjà enregistrées.
* Correction : la petite finale est attribuée sans match quand une demi-finale était une exemption.
* Correction : les exemptions ne comptent plus dans le nombre de matchs ni dans le planning.
* Correction : au classement général, les équipes éliminées en poule sont départagées par leur place en poule.
* Correction : la suppression définitive d'un tournoi depuis la corbeille WordPress nettoie aussi ses équipes et ses matchs ; la désinstallation avec purge inclut les tournois à la corbeille.
* Le règlement affiche le nombre de joueurs configuré au lieu de « 4 » en dur.
* Nouveau : `[brackethive_inscription simple="yes"]`, formulaire réduit à Team, Pseudo, Mail et Téléphone.
* Nouvel assistant « Nouveau tournoi » en quatre étapes (identité, format expliqué, matchs et horaires, inscriptions) avec récapitulatif et estimation de la durée.
* Tableau de bord : panneau « Prochaines étapes » qui guide jusqu’au jour J (équipes, positions, page publique, scores).
* Menu réorganisé pour plusieurs tournois : Tableau de bord, Nouveau tournoi, Tous les tournois, Équipes, Matchs, Planning, Réglages du tournoi, Aperçu, Extension. La zone sensible (réinitialiser, supprimer) est dans Réglages du tournoi, accessible par le bouton « Supprimer » de la barre du tournoi.
* Le bandeau de titre du thème est masqué par défaut sur les pages de tournoi.
* Assistant : choix « publier tout de suite » ou « garder en brouillon », et explications sur les pages du tournoi et d’inscription ; le tableau de bord rappelle de publier une page en brouillon.
* E-mails : accusé de réception au capitaine, alerte à l’organisation même sans adresse configurée (repli sur l’adresse d’administration), bouton « E-mail de test » dans Extension.


## 2.3.1
* Correction : avec 2 poules et 2 qualifiés, le premier tour de la phase finale pouvait opposer deux équipes de la même poule. Le croisement est désormais vérifié et corrigé pour toutes les combinaisons de poules et de qualifiés.


## 2.3.0
* Jusqu'à 64 équipes (au lieu de 32), avec un tour de trente-deuxièmes et son propre réglage de manches.
* Composition manuelle des poules : chaque équipe se voit attribuer sa poule sur sa fiche. Le mode automatique en serpentin reste le comportement par défaut. Un avertissement signale les poules déséquilibrées.
* Seconde grande finale (« bracket reset ») optionnelle en double élimination : si l'équipe issue du repêchage remporte la grande finale, une seconde est jouée, puisqu'elle avait déjà une défaite. Le match n'apparaît que s'il a lieu et n'est pas compté sinon.


## 2.2.1
* CLASSEMENT GÉNÉRAL du tournoi, de la 1re à la dernière place, quel que soit le format. Podium lu sur les matchs décisifs, autres équipes départagées par la profondeur atteinte, rangs partagés signalés.
* Nouvel onglet Classement et code court [brackethive_classement].
* Les codes courts [brackethive_poules] et [brackethive_classement] sont documentés dans l'administration.
* La désinstallation supprime aussi les tournois et leurs pages lorsque l'option de purge est activée.


## 2.2.0
* FORMATS VARIABLES. De 2 à 32 équipes. Les appariements suivent l'algorithme classique de tête de série ; pour 16 équipes, le tableau reste identique au dossier d'origine.
* Exemptions automatiques quand l'effectif n'est pas une puissance de deux : les mieux classées passent le premier tour sans jouer.
* DOUBLE ÉLIMINATION : tableau principal, repêchage à tours alternés et grande finale. Une équipe n'est éliminée qu'après deux défaites.
* PHASE DE POULES : répartition en serpentin, matchs toutes rondes, classements automatiques (victoires, différence, score, confrontation directe) et qualifiés croisés entre poules.
* Nombre de manches réglable pour chaque tour, y compris les poules et le repêchage.
* Planning calculé : durée d'un match, matchs simultanés, temps d'accueil et pauses entre les tours. Les horaires ajustés à la main ne sont plus écrasés ; un bouton « Recalculer le planning » les régénère.
* Nouveau code court [brackethive_poules] pour les classements de poules.
* Correction : sur écran large, le tableau réduit pour tenir en hauteur laissait de grandes marges vides.


## 2.0.0
* MULTI-TOURNOIS. Chaque tournoi est désormais un contenu WordPress à part entière, avec sa page et son URL propres, créées automatiquement.
* Sélecteur de tournoi en haut de chaque écran de gestion ; équipes, matchs, résultats et réglages sont propres à chaque tournoi.
* Duplication d'un tournoi (réglages et format, sans les résultats) pour préparer l'édition suivante.
* Match pour la 3e place, activable tournoi par tournoi.
* Nombre de manches réglable par tour (BO1, BO3, BO5).
* L'heure de début décale automatiquement tout le planning.
* Nouveau code court [brackethive_tournois] : liste de tous les tournois du site.
* Tous les codes courts acceptent un attribut tournoi="slug" ; sans attribut, ils affichent le tournoi par défaut.
* Migration automatique : les données existantes deviennent le premier tournoi, sans ressaisie.


## 1.0.10
* Correction : après l'envoi du formulaire d'inscription en vue à onglets, le visiteur retombait sur l'onglet Tableau et ne voyait jamais le message de confirmation.
* Une équipe refusée libère désormais sa place dans le quota d'inscriptions.

## 1.0.9
* Le tableau tient à l'écran sans aucun réglage : densification automatique (masquage des métadonnées secondaires) avant toute réduction, puis mise à l'échelle seulement si nécessaire.
* Correction du calcul de la place disponible en vue à onglets, qui laissait un résidu de défilement.

## 1.0.8
* Le tableau s'ajuste désormais aussi en hauteur : il tient à l'écran sans défilement vertical (comportement par défaut, `fit="width"` pour revenir à l'ajustement en largeur seule).
* Cartes de match compactées (en-tête sur une ligne, interlignes resserrés) : environ 20 % de hauteur gagnée.
* Le tableau mis à l'échelle est centré au lieu d'être aligné à gauche.

## 1.0.7
* Les équipes éliminées apparaissent barrées, dans le tableau comme dans la feuille de résultats.
* Suppression des boutons d'affichage sur la page publique : l'ajustement est automatique et silencieux.

## 1.0.6
* Le tableau tient désormais dans la largeur disponible, sans défilement horizontal : mise à l'échelle automatique.
* Sous 640 px, les tours s'empilent verticalement plutôt que de réduire le texte jusqu'à l'illisible.
* Attribut `fit="screen"` sur [brackethive_tableau] pour que le tableau tienne aussi en hauteur (affichage sur écran de régie).

## 1.0.5
* Nouvelle option « Masquer le bandeau de titre du thème » sur les pages affichant le tournoi (Réglages). Couvre notamment le thème Salient, avec champ de sélecteurs supplémentaires.

## 1.0.4
* Nouvelle page « Aperçu public » dans l'administration : rendu réel, choix de la vue et de la largeur (bureau / tablette / mobile), rendu en iframe pour des media queries fidèles.
* Détection des pages du site utilisant les codes courts, avec liens directs.
* Correction : les tableaux Planning et Résultats reprenaient le fond blanc du thème.
* Correction : le badge « En cours » était rogné par sa découpe.

## 1.0.3
* Mises à jour automatiques depuis un manifeste auto-hébergé (HTTPS obligatoire pour le paquet).
* Sauvegarde et restauration JSON des équipes, matchs, scores et manches.
* Les données ne sont plus effacées à la désinstallation, sauf option explicite.
* Direction artistique « tactique » : HUD sombre, angles coupés, typographie condensée.
* Limitation de débit (3 envois par heure et par IP) sur le formulaire d'inscription public.

## 1.0.2
* Fiches d'équipe dépliables : clic sur une équipe pour voir ses joueurs.
* Bouton de réinitialisation totale du tournoi (équipes + résultats), avec confirmation par saisie.
* Correction des couleurs : les styles du thème n'écrasent plus ceux du plugin (texte illisible dans les tableaux).

## 1.0.1
* Correction de l'archive d'installation (séparateurs de chemin non conformes).

## 1.0.0
* Version initiale.
