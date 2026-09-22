=== We Game Tournoi ===
Contributors: cparfait
Tags: tournoi, esport, bracket, call of duty, competition
Requires at least: 5.6
Tested up to: 7.1
Requires PHP: 7.0
Stable tag: 2.4.3
License: GPLv2 or later

Gestion et affichage de tournois e-sport : de 2 à 64 équipes, élimination directe, double élimination ou poules, planning horaire, inscriptions et affichage public en direct.

== Description ==

Plugin développé à l'origine pour **We Game 2026 — Tournoi Call of Duty** (16 équipes, 8 postes de jeu). Il gère aujourd'hui plusieurs tournois par site, de 2 à 64 équipes, en élimination directe, double élimination ou phase de poules suivie d'un tableau final.

Fonctionnalités :

* Tableau généré selon l'algorithme classique de tête de série (pour 16 équipes, le placement reste celui du dossier d'origine : M1 : 1 vs 16, M2 : 8 vs 9, etc.), avec exemptions automatiques quand l'effectif n'est pas une puissance de deux.
* Double élimination (repêchage, grande finale et seconde grande finale optionnelle) et phase de poules (classements automatiques, qualifiés croisés).
* Qualification automatique du vainqueur au tour suivant, avec invalidation des tours suivants si un résultat est corrigé.
* Nombre de manches réglable pour chaque tour (BO1, BO3, BO5), saisie manche par manche.
* Gestion des équipes : inscription publique facultative, validation par l'organisation, attribution des positions, tirage au sort.
* Planning horaire calculé, feuille de scores imprimable, classement général, checklist avant ouverture, personnel nécessaire.
* Affichage public à onglets, rafraîchissement automatique pendant la soirée, API REST pour un affichage sur écran.

== Installation ==

1. Copier le dossier `wegame-tournoi` dans `wp-content/plugins/` (ou téléverser le fichier ZIP via Extensions > Ajouter).
2. Activer l'extension : les tables et un premier tournoi (avec sa page) sont créés automatiquement.
3. Menu **Tournoi > Réglages** : nom, date, lieu, format, nombre d'équipes, couleur, inscriptions.
4. Menu **Tournoi > Équipes** : saisir les équipes et leur position, ou lancer le tirage au sort.
5. La page du tournoi est créée automatiquement ; vous pouvez aussi placer le shortcode `[wegame_tournoi]` dans n'importe quelle page.

== Shortcodes ==

Tous les codes courts (sauf `[wegame_tournois]`) acceptent l'attribut `tournoi="slug"` (alias : `tournament="slug"`, un identifiant numérique est également accepté) pour cibler un tournoi précis. Sans attribut, ils affichent le tournoi de la page courante, sinon le tournoi par défaut.

* `[wegame_tournoi]` — page complète à onglets (recommandé). Accepte `fit="width"` (voir ci-dessous).
* `[wegame_tournois]` — liste de tous les tournois publiés du site, avec lien vers chacun
* `[wegame_tableau]` — tableau du tournoi (`header="no"` pour masquer l'en-tête). Le tableau s'adapte seul à l'écran, en largeur et en hauteur ; `fit="width"` limite l'ajustement à la largeur si vous préférez un défilement vertical.
* `[wegame_poules]` — classements de la phase de poules
* `[wegame_classement]` — classement général du tournoi, de la première à la dernière place
* `[wegame_planning]` — planning horaire
* `[wegame_resultats]` — feuille de résultats
* `[wegame_equipes]` — équipes engagées (`players="yes"` pour afficher les joueurs)
* `[wegame_reglement]` — règlement
* `[wegame_organisation]` — personnel nécessaire
* `[wegame_checklist]` — checklist avant ouverture
* `[wegame_inscription]` — formulaire d'inscription d'équipe (`simple="yes"` pour un formulaire réduit à Team, Pseudo, Mail et Téléphone)

Exemple : `[wegame_tableau tournoi="we-game-2026" header="no" fit="width"]`

== API REST ==

* `GET /wp-json/wegame/v1/state` — état complet du tournoi en JSON (affichage sur écran, régie).
* `GET /wp-json/wegame/v1/render?view=bracket` — HTML d'une vue (utilisé par le rafraîchissement automatique).

== Mises à jour automatiques ==

Renseignez dans Tournoi > Réglages > Maintenance l'URL d'un fichier JSON décrivant la dernière version (voir `wegame-tournoi-manifest.json`). WordPress proposera alors les mises à jour dans la page Extensions. Le manifeste lui-même et le paquet référencé par `download_url` doivent être servis en HTTPS : l'extension refuse tout autre schéma, puisqu'il s'agit de code PHP installé automatiquement. Le manifeste peut en outre indiquer une clé `sha256` (empreinte hexadécimale du fichier ZIP) : lorsqu'elle est présente, le paquet téléchargé est vérifié avant installation et rejeté s'il ne correspond pas.

== QR codes et page d'inscription ==

Tournoi > Tableau de bord > Liens publics : l'adresse de la page du tournoi et celle de la page d'inscription, chacune avec un bouton Copier et un QR code téléchargeable en PNG (haute définition, pour une affiche) ou en SVG (vectoriel, pour l'impression). Les QR codes sont calculés par le serveur : aucune adresse n'est envoyée à un service extérieur, et ils s'affichent sans JavaScript.

Le bouton « Créer une page d'inscription dédiée » ajoute une page WordPress publiée contenant le seul formulaire simplifié. Elle est supprimée avec le tournoi.

== Sauvegarde ==

Tournoi > Tableau de bord > Sauvegarde : export JSON complet (équipes, matchs, scores, manches) et restauration. À faire avant toute manipulation délicate.

Par défaut, désinstaller l'extension ne supprime PAS les données : les tables sont conservées. Une option dans Réglages > Maintenance permet l'inverse si vous voulez un nettoyage complet.

== Changelog ==

= 2.4.3 =
* Correction : les QR codes n'apparaissaient pas. Ils sont désormais calculés par le serveur et affichés en SVG dans la page, sans dépendre d'un script : ils restent visibles même avec une extension de cache qui regroupe ou diffère les fichiers JavaScript.
* Les QR codes se téléchargent en PNG (environ 600 pixels de côté) ou en SVG vectoriel.


= 2.4.2 =
* QR codes téléchargeables (PNG et SVG) pour la page du tournoi et la page d'inscription, générés dans le navigateur sans service extérieur.
* Bouton « Créer une page d'inscription dédiée » : une page WordPress publiée ne contenant que le formulaire simplifié, avec son propre lien et son QR code. Également proposé à la fin de l'assistant de création.
* Bouton « Copier » à côté de chaque adresse.
* La section Codes courts explique comment créer une page, y coller le code court et la publier.
* La page d'inscription créée par l'extension est supprimée avec son tournoi.


= 2.4.1 =
* Assistant : option « Pas de page dédiée » (codes courts sur vos propres pages) ; tableau de bord : bloc « Liens publics » avec l’adresse de la page du tournoi, du formulaire d’inscription et la liste des pages du site utilisant les codes courts.
* Écran Équipes : le formulaire d’ajout est au-dessus de la liste, champs en colonnes.
* Titre de page : sur les pages de tournoi, le titre rendu par le thème est vidé quel que soit le thème (en plus du masquage CSS), sans toucher au titre de l’onglet du navigateur.
* Régénération forcée des permaliens à la mise à jour.


= 2.4.0 =
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
* Documentation : codes courts [wegame_tournois], [wegame_poules] et [wegame_classement] et attribut `tournoi="slug"` documentés.
* Correction : en BO3/BO5, les manches en trop et les manches à égalité sont refusées ; une saisie refusée ne supprime plus les manches déjà enregistrées.
* Correction : la petite finale est attribuée sans match quand une demi-finale était une exemption.
* Correction : les exemptions ne comptent plus dans le nombre de matchs ni dans le planning.
* Correction : au classement général, les équipes éliminées en poule sont départagées par leur place en poule.
* Correction : la suppression définitive d'un tournoi depuis la corbeille WordPress nettoie aussi ses équipes et ses matchs ; la désinstallation avec purge inclut les tournois à la corbeille.
* Le règlement affiche le nombre de joueurs configuré au lieu de « 4 » en dur.
* Nouveau : `[wegame_inscription simple="yes"]`, formulaire réduit à Team, Pseudo, Mail et Téléphone.
* Nouvel assistant « Nouveau tournoi » en quatre étapes (identité, format expliqué, matchs et horaires, inscriptions) avec récapitulatif et estimation de la durée.
* Tableau de bord : panneau « Prochaines étapes » qui guide jusqu’au jour J (équipes, positions, page publique, scores).
* Menu réorganisé pour plusieurs tournois : Tableau de bord, Nouveau tournoi, Tous les tournois, Équipes, Matchs, Planning, Réglages du tournoi, Aperçu, Extension. La zone sensible (réinitialiser, supprimer) est dans Réglages du tournoi, accessible par le bouton « Supprimer » de la barre du tournoi.
* Le bandeau de titre du thème est masqué par défaut sur les pages de tournoi.
* Assistant : choix « publier tout de suite » ou « garder en brouillon », et explications sur les pages du tournoi et d’inscription ; le tableau de bord rappelle de publier une page en brouillon.
* E-mails : accusé de réception au capitaine, alerte à l’organisation même sans adresse configurée (repli sur l’adresse d’administration), bouton « E-mail de test » dans Extension.


= 2.3.1 =
* Correction : avec 2 poules et 2 qualifiés, le premier tour de la phase finale pouvait opposer deux équipes de la même poule. Le croisement est désormais vérifié et corrigé pour toutes les combinaisons de poules et de qualifiés.


= 2.3.0 =
* Jusqu'à 64 équipes (au lieu de 32), avec un tour de trente-deuxièmes et son propre réglage de manches.
* Composition manuelle des poules : chaque équipe se voit attribuer sa poule sur sa fiche. Le mode automatique en serpentin reste le comportement par défaut. Un avertissement signale les poules déséquilibrées.
* Seconde grande finale (« bracket reset ») optionnelle en double élimination : si l'équipe issue du repêchage remporte la grande finale, une seconde est jouée, puisqu'elle avait déjà une défaite. Le match n'apparaît que s'il a lieu et n'est pas compté sinon.


= 2.2.1 =
* CLASSEMENT GÉNÉRAL du tournoi, de la 1re à la dernière place, quel que soit le format. Podium lu sur les matchs décisifs, autres équipes départagées par la profondeur atteinte, rangs partagés signalés.
* Nouvel onglet Classement et code court [wegame_classement].
* Les codes courts [wegame_poules] et [wegame_classement] sont documentés dans l'administration.
* La désinstallation supprime aussi les tournois et leurs pages lorsque l'option de purge est activée.


= 2.2.0 =
* FORMATS VARIABLES. De 2 à 32 équipes. Les appariements suivent l'algorithme classique de tête de série ; pour 16 équipes, le tableau reste identique au dossier d'origine.
* Exemptions automatiques quand l'effectif n'est pas une puissance de deux : les mieux classées passent le premier tour sans jouer.
* DOUBLE ÉLIMINATION : tableau principal, repêchage à tours alternés et grande finale. Une équipe n'est éliminée qu'après deux défaites.
* PHASE DE POULES : répartition en serpentin, matchs toutes rondes, classements automatiques (victoires, différence, score, confrontation directe) et qualifiés croisés entre poules.
* Nombre de manches réglable pour chaque tour, y compris les poules et le repêchage.
* Planning calculé : durée d'un match, matchs simultanés, temps d'accueil et pauses entre les tours. Les horaires ajustés à la main ne sont plus écrasés ; un bouton « Recalculer le planning » les régénère.
* Nouveau code court [wegame_poules] pour les classements de poules.
* Correction : sur écran large, le tableau réduit pour tenir en hauteur laissait de grandes marges vides.


= 2.0.0 =
* MULTI-TOURNOIS. Chaque tournoi est désormais un contenu WordPress à part entière, avec sa page et son URL propres, créées automatiquement.
* Sélecteur de tournoi en haut de chaque écran de gestion ; équipes, matchs, résultats et réglages sont propres à chaque tournoi.
* Duplication d'un tournoi (réglages et format, sans les résultats) pour préparer l'édition suivante.
* Match pour la 3e place, activable tournoi par tournoi.
* Nombre de manches réglable par tour (BO1, BO3, BO5).
* L'heure de début décale automatiquement tout le planning.
* Nouveau code court [wegame_tournois] : liste de tous les tournois du site.
* Tous les codes courts acceptent un attribut tournoi="slug" ; sans attribut, ils affichent le tournoi par défaut.
* Migration automatique : les données existantes deviennent le premier tournoi, sans ressaisie.


= 1.0.10 =
* Correction : après l'envoi du formulaire d'inscription en vue à onglets, le visiteur retombait sur l'onglet Tableau et ne voyait jamais le message de confirmation.
* Une équipe refusée libère désormais sa place dans le quota d'inscriptions.

= 1.0.9 =
* Le tableau tient à l'écran sans aucun réglage : densification automatique (masquage des métadonnées secondaires) avant toute réduction, puis mise à l'échelle seulement si nécessaire.
* Correction du calcul de la place disponible en vue à onglets, qui laissait un résidu de défilement.

= 1.0.8 =
* Le tableau s'ajuste désormais aussi en hauteur : il tient à l'écran sans défilement vertical (comportement par défaut, `fit="width"` pour revenir à l'ajustement en largeur seule).
* Cartes de match compactées (en-tête sur une ligne, interlignes resserrés) : environ 20 % de hauteur gagnée.
* Le tableau mis à l'échelle est centré au lieu d'être aligné à gauche.

= 1.0.7 =
* Les équipes éliminées apparaissent barrées, dans le tableau comme dans la feuille de résultats.
* Suppression des boutons d'affichage sur la page publique : l'ajustement est automatique et silencieux.

= 1.0.6 =
* Le tableau tient désormais dans la largeur disponible, sans défilement horizontal : mise à l'échelle automatique.
* Sous 640 px, les tours s'empilent verticalement plutôt que de réduire le texte jusqu'à l'illisible.
* Attribut `fit="screen"` sur [wegame_tableau] pour que le tableau tienne aussi en hauteur (affichage sur écran de régie).

= 1.0.5 =
* Nouvelle option « Masquer le bandeau de titre du thème » sur les pages affichant le tournoi (Réglages). Couvre notamment le thème Salient, avec champ de sélecteurs supplémentaires.

= 1.0.4 =
* Nouvelle page « Aperçu public » dans l'administration : rendu réel, choix de la vue et de la largeur (bureau / tablette / mobile), rendu en iframe pour des media queries fidèles.
* Détection des pages du site utilisant les codes courts, avec liens directs.
* Correction : les tableaux Planning et Résultats reprenaient le fond blanc du thème.
* Correction : le badge « En cours » était rogné par sa découpe.

= 1.0.3 =
* Mises à jour automatiques depuis un manifeste auto-hébergé (HTTPS obligatoire pour le paquet).
* Sauvegarde et restauration JSON des équipes, matchs, scores et manches.
* Les données ne sont plus effacées à la désinstallation, sauf option explicite.
* Direction artistique « tactique » : HUD sombre, angles coupés, typographie condensée.
* Limitation de débit (3 envois par heure et par IP) sur le formulaire d'inscription public.

= 1.0.2 =
* Fiches d'équipe dépliables : clic sur une équipe pour voir ses joueurs.
* Bouton de réinitialisation totale du tournoi (équipes + résultats), avec confirmation par saisie.
* Correction des couleurs : les styles du thème n'écrasent plus ceux du plugin (texte illisible dans les tableaux).

= 1.0.1 =
* Correction de l'archive d'installation (séparateurs de chemin non conformes).

= 1.0.0 =
* Version initiale.
