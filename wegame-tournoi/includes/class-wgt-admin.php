<?php
/**
 * Interface d'administration.
 *
 * Les écrans de gestion travaillent toujours sur un tournoi, sélectionné par
 * la barre en haut de page et mémorisé pour l'utilisateur.
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Admin {

	/**
	 * Capacité requise.
	 *
	 * @return string
	 */
	public static function cap() {
		return apply_filters( 'wgt_admin_capability', 'manage_options' );
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_action( 'admin_post_wgt_save_team', array( __CLASS__, 'handle_save_team' ) );
		add_action( 'admin_post_wgt_delete_team', array( __CLASS__, 'handle_delete_team' ) );
		add_action( 'admin_post_wgt_save_result', array( __CLASS__, 'handle_save_result' ) );
		add_action( 'admin_post_wgt_tools', array( __CLASS__, 'handle_tools' ) );
		add_action( 'admin_post_wgt_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_wgt_save_tournament', array( __CLASS__, 'handle_save_tournament' ) );
		add_action( 'admin_post_wgt_create_tournament', array( __CLASS__, 'handle_create_tournament' ) );
		add_action( 'admin_post_wgt_duplicate_tournament', array( __CLASS__, 'handle_duplicate_tournament' ) );
		add_action( 'admin_post_wgt_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_wgt_import', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_wgt_check_update', array( __CLASS__, 'handle_check_update' ) );
		add_action( 'admin_post_wgt_test_mail', array( __CLASS__, 'handle_test_mail' ) );
		add_action( 'admin_post_wgt_create_registration_page', array( __CLASS__, 'handle_create_registration_page' ) );
		add_action( 'admin_post_wgt_preview_frame', array( __CLASS__, 'handle_preview_frame' ) );
	}

	/* ---------------------------------------------------------------------
	 * Menus
	 * ------------------------------------------------------------------ */

	/**
	 * Menus.
	 */
	public static function menu() {
		$cap = self::cap();

		add_menu_page(
			__( 'Tournois', 'wegame-tournoi' ),
			__( 'Tournois', 'wegame-tournoi' ),
			$cap,
			'wgt-dashboard',
			array( __CLASS__, 'page_dashboard' ),
			'dashicons-games',
			26
		);

		add_submenu_page( 'wgt-dashboard', __( 'Tableau de bord', 'wegame-tournoi' ), __( 'Tableau de bord', 'wegame-tournoi' ), $cap, 'wgt-dashboard', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Nouveau tournoi', 'wegame-tournoi' ), __( 'Nouveau tournoi', 'wegame-tournoi' ), $cap, 'wgt-new', array( __CLASS__, 'page_new' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Tous les tournois', 'wegame-tournoi' ), __( 'Tous les tournois', 'wegame-tournoi' ), $cap, 'edit.php?post_type=' . WGT_Tournament::POST_TYPE );
		add_submenu_page( 'wgt-dashboard', __( 'Équipes', 'wegame-tournoi' ), __( 'Équipes', 'wegame-tournoi' ), $cap, 'wgt-teams', array( __CLASS__, 'page_teams' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Matchs & scores', 'wegame-tournoi' ), __( 'Matchs & scores', 'wegame-tournoi' ), $cap, 'wgt-matches', array( __CLASS__, 'page_matches' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Planning', 'wegame-tournoi' ), __( 'Planning', 'wegame-tournoi' ), $cap, 'wgt-planning', array( __CLASS__, 'page_planning' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Réglages du tournoi', 'wegame-tournoi' ), __( 'Réglages du tournoi', 'wegame-tournoi' ), $cap, 'wgt-tournament', array( __CLASS__, 'page_tournament' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Aperçu public', 'wegame-tournoi' ), __( 'Aperçu public', 'wegame-tournoi' ), $cap, 'wgt-preview', array( __CLASS__, 'page_preview' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Réglages de l’extension', 'wegame-tournoi' ), __( 'Extension', 'wegame-tournoi' ), $cap, 'wgt-settings', array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * Styles et scripts d'administration.
	 *
	 * @param string $hook Page courante.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'wgt-' ) ) {
			return;
		}

		wp_enqueue_style( 'wgt-admin', WGT_URL . 'assets/css/wgt-admin.css', array(), WGT_VERSION );
		wp_enqueue_script( 'wgt-admin', WGT_URL . 'assets/js/wgt-admin.js', array(), WGT_VERSION, true );
		wp_localize_script(
			'wgt-admin',
			'WGT_ADMIN',
			array(
				'i18n' => array(
					'copied' => __( 'Copié', 'wegame-tournoi' ),
					'copy'   => __( 'Copier', 'wegame-tournoi' ),
				),
			)
		);

		if ( false !== strpos( $hook, 'wgt-preview' ) ) {
			wp_enqueue_style( 'wgt-public', WGT_URL . 'assets/css/wgt-public.css', array(), WGT_VERSION );
		}
	}

	/* ---------------------------------------------------------------------
	 * Utilitaires
	 * ------------------------------------------------------------------ */

	/**
	 * Vérifie droits + nonce.
	 *
	 * @param string $action Action du nonce.
	 */
	protected static function guard( $action ) {
		if ( ! current_user_can( self::cap() ) ) {
			wp_die( esc_html__( 'Accès refusé.', 'wegame-tournoi' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Tournoi visé par une requête POST.
	 *
	 * @return int
	 */
	protected static function posted_tournament() {
		$tid = isset( $_POST['tournament_id'] ) ? (int) $_POST['tournament_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing
		return WGT_Tournament::exists( $tid ) ? $tid : 0;
	}

	/**
	 * Redirection avec message.
	 *
	 * @param string $page    Slug de page.
	 * @param string $type    updated|error.
	 * @param string $message Message.
	 * @param int    $tid     Tournoi.
	 * @param array  $extra   Paramètres additionnels.
	 */
	protected static function back( $page, $type, $message, $tid = 0, $extra = array() ) {
		$args = array(
			'page'     => $page,
			'wgt_type' => $type,
			'wgt_msg'  => rawurlencode( $message ),
		);

		if ( $tid ) {
			$args['tournament'] = (int) $tid;
		}

		wp_safe_redirect( add_query_arg( array_merge( $args, $extra ), admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Affiche le message de retour.
	 */
	protected static function notice() {
		if ( empty( $_GET['wgt_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		$type = isset( $_GET['wgt_type'] ) && 'error' === $_GET['wgt_type'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$msg  = sanitize_text_field( rawurldecode( wp_unslash( $_GET['wgt_msg'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Barre de sélection du tournoi, en haut des écrans de gestion.
	 *
	 * @param int    $tid  Tournoi courant.
	 * @param string $page Slug de la page courante.
	 */
	protected static function switcher( $tid, $page ) {
		$all = WGT_Tournament::all();

		echo '<div class="wgt-switcher">';
		echo '<span class="wgt-switcher__label">' . esc_html__( 'Tournoi', 'wegame-tournoi' ) . '</span>';

		if ( empty( $all ) ) {
			echo '<em>' . esc_html__( 'aucun tournoi', 'wegame-tournoi' ) . '</em>';
		} else {
			echo '<select onchange="if(this.value)location.href=this.value;">';
			foreach ( $all as $post ) {
				$url = add_query_arg(
					array( 'page' => $page, 'tournament' => (int) $post->ID ),
					admin_url( 'admin.php' )
				);
				$label = get_the_title( $post );
				if ( 'publish' !== $post->post_status ) {
					$label .= ' (' . $post->post_status . ')';
				}
				echo '<option value="' . esc_url( $url ) . '"' . selected( (int) $post->ID, $tid, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
		}

		if ( $tid ) {
			echo ' <a class="button button-small" href="' . esc_url( get_edit_post_link( $tid ) ) . '">' . esc_html__( 'Modifier la page', 'wegame-tournoi' ) . '</a>';
			echo ' <a class="button button-small" href="' . esc_url( get_permalink( $tid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'Voir', 'wegame-tournoi' ) . '</a>';
			// Renvoie vers la zone sensible des réglages du tournoi, où la
			// suppression demande une confirmation par saisie.
			$danger = add_query_arg( array( 'page' => 'wgt-tournament', 'tournament' => (int) $tid ), admin_url( 'admin.php' ) ) . '#wgt-danger';
			echo ' <a class="button button-small wgt-switcher__delete" href="' . esc_url( $danger ) . '">' . esc_html__( 'Supprimer', 'wegame-tournoi' ) . '</a>';
		}

		echo ' <a class="button button-small" href="' . esc_url( add_query_arg( 'page', 'wgt-new', admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Créer un tournoi', 'wegame-tournoi' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Écran affiché quand aucun tournoi n'existe.
	 *
	 * @param string $title Titre de la page.
	 */
	protected static function no_tournament( $title ) {
		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		self::notice();
		echo '<div class="notice notice-info"><p>' . esc_html__( 'Aucun tournoi n’existe pour le moment. L’assistant ci-dessous vous guide en quatre étapes.', 'wegame-tournoi' ) . '</p></div>';
		self::wizard();
		echo '</div>';
	}

	/**
	 * Formulaire de création d'un tournoi.
	 */
	protected static function create_form() {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-box" style="max-width:640px">';
		wp_nonce_field( 'wgt_create_tournament' );
		echo '<input type="hidden" name="action" value="wgt_create_tournament" />';
		echo '<h2>' . esc_html__( 'Nouveau tournoi', 'wegame-tournoi' ) . '</h2>';
		echo '<p><label>' . esc_html__( 'Nom', 'wegame-tournoi' ) . ' *<br /><input type="text" class="regular-text" name="title" required placeholder="' . esc_attr__( 'We Game 2027 — Call of Duty', 'wegame-tournoi' ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Jeu', 'wegame-tournoi' ) . '<br /><input type="text" class="regular-text" name="game_name" /></label></p>';
		echo '<p><label>' . esc_html__( 'Date', 'wegame-tournoi' ) . '<br /><input type="date" name="event_date" /></label></p>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Créer', 'wegame-tournoi' ) . '</button></p>';
		echo '</form>';
	}

	/* ---------------------------------------------------------------------
	 * Traitements — tournois
	 * ------------------------------------------------------------------ */

	/**
	 * Création d'un tournoi.
	 */
	public static function handle_create_tournament() {
		self::guard( 'wgt_create_tournament' );

		$title = isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$settings = array();
		if ( isset( $_POST['game_name'] ) ) {
			$settings['game_name'] = sanitize_text_field( wp_unslash( $_POST['game_name'] ) );
		}
		if ( isset( $_POST['event_date'] ) ) {
			$settings['event_date'] = sanitize_text_field( wp_unslash( $_POST['event_date'] ) );
		}

		$choice = isset( $_POST['post_status'] ) ? sanitize_key( $_POST['post_status'] ) : 'publish';
		if ( ! in_array( $choice, array( 'publish', 'draft', 'none' ), true ) ) {
			$choice = 'publish';
		}
		// « Pas de page dédiée » : le tournoi est publié (ses codes courts
		// fonctionnent) mais sa page répond 404 aux visiteurs.
		$status = 'draft' === $choice ? 'draft' : 'publish';

		$id = WGT_Tournament::create( $title, $settings, $status );

		if ( is_wp_error( $id ) ) {
			self::back( 'wgt-new', 'error', $id->get_error_message() );
		}

		// L'assistant transmet tous les réglages : même sanitisation que
		// l'écran Réglages du tournoi, puis structure du tableau.
		if ( ! empty( $_POST['wgt_wizard'] ) ) {
			$input                       = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			$input['public_page']        = 'none' === $choice ? 0 : 1;
			$input['wgt_checkbox_scope'] = ( isset( $input['wgt_checkbox_scope'] ) ? $input['wgt_checkbox_scope'] . ',' : '' ) . 'public_page';
			WGT_Tournament::save_settings( $id, $input );
			WGT_Data::ensure_bracket( $id );
			WGT_Data::recalculate( $id );

			if ( ! empty( $_POST['create_registration_page'] ) ) {
				WGT_Tournament::create_registration_page( $id, 'none' === $choice ? 'publish' : $status );
			}
			if ( 'draft' === $choice ) {
				$msg = __( 'Tournoi créé en brouillon. Prochaine étape : ajouter les équipes, puis publier la page.', 'wegame-tournoi' );
			} elseif ( 'none' === $choice ) {
				$msg = __( 'Tournoi créé sans page dédiée. Placez les codes courts sur vos pages, puis ajoutez les équipes.', 'wegame-tournoi' );
			} else {
				$msg = __( 'Tournoi créé et page publiée. Prochaine étape : ajouter les équipes.', 'wegame-tournoi' );
			}
			self::back( 'wgt-dashboard', 'updated', $msg, $id );
		}

		self::back( 'wgt-dashboard', 'updated', __( 'Tournoi créé.', 'wegame-tournoi' ), $id );
	}

	/**
	 * Écran « Nouveau tournoi » : assistant de création en quatre étapes.
	 */
	public static function page_new() {
		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Nouveau tournoi', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::wizard();
		echo '</div>';
	}

	/**
	 * Assistant de création.
	 *
	 * Un seul formulaire, découpé en étapes par le script d'administration ;
	 * sans JavaScript, toutes les étapes s'affichent à la suite.
	 */
	protected static function wizard() {
		$d = WGT_Tournament::defaults();

		$formats = array(
			'single' => array(
				__( 'Élimination directe', 'wegame-tournoi' ),
				__( 'Le plus simple et le plus rapide : une défaite et l’équipe est éliminée. Idéal pour une soirée.', 'wegame-tournoi' ),
				__( '16 équipes : 15 matchs', 'wegame-tournoi' ),
			),
			'double' => array(
				__( 'Double élimination', 'wegame-tournoi' ),
				__( 'Une équipe n’est éliminée qu’après deux défaites, grâce à un tableau de repêchage. Plus long, plus juste.', 'wegame-tournoi' ),
				__( '16 équipes : 30 matchs', 'wegame-tournoi' ),
			),
			'groups' => array(
				__( 'Poules puis phase finale', 'wegame-tournoi' ),
				__( 'Chaque équipe joue plusieurs matchs dans sa poule, puis les meilleures s’affrontent en tableau. Le plus long.', 'wegame-tournoi' ),
				__( '16 équipes en 4 poules : 24 + 7 matchs', 'wegame-tournoi' ),
			),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-wizard" data-wgt-wizard>';
		wp_nonce_field( 'wgt_create_tournament' );
		echo '<input type="hidden" name="action" value="wgt_create_tournament" />';
		echo '<input type="hidden" name="wgt_wizard" value="1" />';
		echo '<input type="hidden" name="wgt_checkbox_scope" value="third_place,bracket_reset,registration_open,show_rules,auto_content" />';
		echo '<input type="hidden" name="auto_content" value="1" />';
		echo '<input type="hidden" name="show_rules" value="1" />';

		// Fil d'Ariane.
		$steps = array(
			__( 'Le tournoi', 'wegame-tournoi' ),
			__( 'Le format', 'wegame-tournoi' ),
			__( 'Matchs et horaires', 'wegame-tournoi' ),
			__( 'Inscriptions et création', 'wegame-tournoi' ),
		);
		echo '<ol class="wgt-wizard__steps">';
		foreach ( $steps as $i => $label ) {
			echo '<li class="wgt-wizard__step' . ( 0 === $i ? ' is-current' : '' ) . '" data-wgt-step-link="' . (int) ( $i + 1 ) . '"><span class="wgt-wizard__num">' . (int) ( $i + 1 ) . '</span> ' . esc_html( $label ) . '</li>';
		}
		echo '</ol>';

		/* ---------------- Étape 1 : identité ---------------- */
		echo '<section class="wgt-wizard__panel" data-wgt-step="1">';
		echo '<h2>' . esc_html__( '1. Le tournoi', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Ces informations apparaissent en tête de la page publique. Seul le nom est obligatoire ; tout se modifie ensuite dans Réglages du tournoi.', 'wegame-tournoi' ) . '</p>';
		echo '<div class="wgt-wizard__grid">';
		echo '<p class="wgt-wizard__full"><label for="wgt-w-title">' . esc_html__( 'Nom du tournoi', 'wegame-tournoi' ) . ' *</label><input type="text" id="wgt-w-title" class="regular-text" name="title" required placeholder="' . esc_attr__( 'We Game 2027 — Call of Duty', 'wegame-tournoi' ) . '" /><span class="description">' . esc_html__( 'Il devient aussi le titre et l’adresse de la page du tournoi.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-game">' . esc_html__( 'Jeu', 'wegame-tournoi' ) . '</label><input type="text" id="wgt-w-game" class="regular-text" name="game_name" placeholder="' . esc_attr__( 'Call of Duty', 'wegame-tournoi' ) . '" /></p>';
		echo '<p><label for="wgt-w-venue">' . esc_html__( 'Lieu', 'wegame-tournoi' ) . '</label><input type="text" id="wgt-w-venue" class="regular-text" name="venue" placeholder="' . esc_attr__( 'Salle des fêtes', 'wegame-tournoi' ) . '" /></p>';
		echo '<p><label for="wgt-w-date">' . esc_html__( 'Date', 'wegame-tournoi' ) . '</label><input type="date" id="wgt-w-date" name="event_date" /></p>';
		echo '<p><label for="wgt-w-start">' . esc_html__( 'Heure du premier match', 'wegame-tournoi' ) . '</label><input type="time" id="wgt-w-start" name="start_time" value="' . esc_attr( $d['start_time'] ) . '" /></p>';
		echo '<p><label for="wgt-w-subtitle">' . esc_html__( 'Sous-titre (facultatif)', 'wegame-tournoi' ) . '</label><input type="text" id="wgt-w-subtitle" class="regular-text" name="subtitle" placeholder="' . esc_attr__( 'Tournoi amateur ouvert à tous', 'wegame-tournoi' ) . '" /></p>';
		echo '<p><label for="wgt-w-color">' . esc_html__( 'Couleur d’accent', 'wegame-tournoi' ) . '</label><input type="color" id="wgt-w-color" name="accent_color" value="' . esc_attr( $d['accent_color'] ) . '" /></p>';
		echo '</div>';
		echo '</section>';

		/* ---------------- Étape 2 : format ---------------- */
		echo '<section class="wgt-wizard__panel" data-wgt-step="2" hidden>';
		echo '<h2>' . esc_html__( '2. Le format', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Choisissez comment les équipes s’affrontent. Le tableau des matchs est généré automatiquement d’après ce choix.', 'wegame-tournoi' ) . '</p>';
		echo '<div class="wgt-wizard__choices">';
		foreach ( $formats as $key => $info ) {
			echo '<label class="wgt-choice' . ( 'single' === $key ? ' is-selected' : '' ) . '">';
			echo '<input type="radio" name="format" value="' . esc_attr( $key ) . '"' . checked( 'single', $key, false ) . ' />';
			echo '<span class="wgt-choice__title">' . esc_html( $info[0] ) . '</span>';
			echo '<span class="wgt-choice__desc">' . esc_html( $info[1] ) . '</span>';
			echo '<span class="wgt-choice__meta">' . esc_html( $info[2] ) . '</span>';
			echo '</label>';
		}
		echo '</div>';

		echo '<div class="wgt-wizard__grid">';
		echo '<p><label for="wgt-w-count">' . esc_html__( 'Nombre d’équipes', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-count" min="2" max="64" name="team_count" value="' . (int) $d['team_count'] . '" class="small-text" /><span class="description">' . esc_html__( 'De 2 à 64. Si ce n’est pas une puissance de deux (8, 16, 32…), les mieux placées sont exemptées du premier tour.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-players">' . esc_html__( 'Joueurs par équipe', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-players" min="1" name="players_per_team" value="' . (int) $d['players_per_team'] . '" class="small-text" /></p>';

		echo '<p class="wgt-if-groups"><label for="wgt-w-groups">' . esc_html__( 'Nombre de poules', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-groups" min="2" max="8" name="group_count" value="' . (int) $d['group_count'] . '" class="small-text" /><span class="description">' . esc_html__( 'Les équipes sont réparties en serpentin selon leur position. Vous pourrez aussi composer les poules à la main.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p class="wgt-if-groups"><label for="wgt-w-qual">' . esc_html__( 'Qualifiés par poule', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-qual" min="1" max="4" name="qualifiers_per_group" value="' . (int) $d['qualifiers_per_group'] . '" class="small-text" /><span class="description">' . esc_html__( 'Les qualifiés sont croisés entre poules pour la phase finale.', 'wegame-tournoi' ) . '</span></p>';
		echo '<input type="hidden" name="group_mode" value="auto" />';

		echo '<p class="wgt-wizard__full wgt-if-not-double"><label><input type="checkbox" name="third_place" value="1" /> ' . esc_html__( 'Jouer un match pour la 3e place entre les perdants des demi-finales', 'wegame-tournoi' ) . '</label></p>';
		echo '<p class="wgt-wizard__full wgt-if-double"><label><input type="checkbox" name="bracket_reset" value="1" /> ' . esc_html__( 'Seconde grande finale si l’équipe venue du repêchage gagne la première (règle stricte)', 'wegame-tournoi' ) . '</label><span class="description">' . esc_html__( 'En double élimination, le perdant de la finale du repêchage est automatiquement 3e.', 'wegame-tournoi' ) . '</span></p>';
		echo '</div>';
		echo '</section>';

		/* ---------------- Étape 3 : matchs et horaires ---------------- */
		echo '<section class="wgt-wizard__panel" data-wgt-step="3" hidden>';
		echo '<h2>' . esc_html__( '3. Matchs et horaires', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Le planning est calculé d’après ces valeurs : chaque match reçoit une heure et des postes. Vous pourrez ajuster chaque horaire à la main ensuite.', 'wegame-tournoi' ) . '</p>';

		echo '<h3>' . esc_html__( 'Nombre de manches par tour', 'wegame-tournoi' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'BO1 : une manche. BO3 : la première équipe à deux manches gagnées. BO5 : à trois. Un BO3 dure environ deux fois plus longtemps qu’un BO1.', 'wegame-tournoi' ) . '</p>';
		$bo_rounds = array(
			'bo_group' => array( __( 'Matchs de poule', 'wegame-tournoi' ), 'wgt-if-groups' ),
			'bo_r64'   => array( __( 'Premiers tours (32es, 16es)', 'wegame-tournoi' ), '' ),
			'bo_r16'   => array( __( 'Huitièmes', 'wegame-tournoi' ), '' ),
			'bo_qf'    => array( __( 'Quarts', 'wegame-tournoi' ), '' ),
			'bo_sf'    => array( __( 'Demi-finales', 'wegame-tournoi' ), '' ),
			'bo_final' => array( __( 'Finale', 'wegame-tournoi' ), '' ),
			'bo_lb'    => array( __( 'Repêchage', 'wegame-tournoi' ), 'wgt-if-double' ),
		);
		echo '<div class="wgt-wizard__bo">';
		foreach ( $bo_rounds as $key => $info ) {
			echo '<label class="' . esc_attr( $info[1] ) . '">' . esc_html( $info[0] ) . ' <select name="' . esc_attr( $key ) . '"' . ( 'bo_r64' === $key ? ' data-wgt-mirror="bo_r32"' : '' ) . '>';
			foreach ( array( 1, 3, 5 ) as $bo ) {
				echo '<option value="' . (int) $bo . '"' . selected( (int) $d[ $key ], $bo, false ) . '>BO' . (int) $bo . '</option>';
			}
			echo '</select></label>';
		}
		echo '<input type="hidden" name="bo_r32" value="' . (int) $d['bo_r32'] . '" />';
		echo '</div>';

		echo '<h3>' . esc_html__( 'Postes et durées', 'wegame-tournoi' ) . '</h3>';
		echo '<div class="wgt-wizard__grid">';
		echo '<p><label for="wgt-w-stations">' . esc_html__( 'Postes de jeu disponibles', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-stations" min="1" name="stations" value="' . (int) $d['stations'] . '" class="small-text" /><span class="description">' . esc_html__( 'Nombre total de PC ou consoles.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-parallel">' . esc_html__( 'Matchs joués en même temps', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-parallel" min="1" name="matches_parallel" value="' . (int) $d['matches_parallel'] . '" class="small-text" /><span class="description">' . esc_html__( 'Avec 8 postes et des équipes de 4, deux matchs peuvent se jouer simultanément.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-duration">' . esc_html__( 'Durée d’une manche (minutes)', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-duration" min="5" name="match_duration" value="' . (int) $d['match_duration'] . '" class="small-text" /><span class="description">' . esc_html__( 'Installation et rotation des équipes comprises.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-warmup">' . esc_html__( 'Accueil avant le premier match (minutes)', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-warmup" min="0" name="warmup_minutes" value="' . (int) $d['warmup_minutes'] . '" class="small-text" /></p>';
		echo '<p><label for="wgt-w-break">' . esc_html__( 'Pause entre les tours (minutes)', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-break" min="0" name="break_minutes" value="' . (int) $d['break_minutes'] . '" class="small-text" /></p>';
		echo '<p><label for="wgt-w-end">' . esc_html__( 'Fin prévisionnelle', 'wegame-tournoi' ) . '</label><input type="time" id="wgt-w-end" name="end_time" value="' . esc_attr( $d['end_time'] ) . '" /><span class="description">' . esc_html__( 'Indicatif, affiché sur la page publique.', 'wegame-tournoi' ) . '</span></p>';
		echo '</div>';
		echo '</section>';

		/* ---------------- Étape 4 : inscriptions et création ---------------- */
		echo '<section class="wgt-wizard__panel" data-wgt-step="4" hidden>';
		echo '<h2>' . esc_html__( '4. Inscriptions et création', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Deux façons de constituer les équipes : les saisir vous-même dans l’écran Équipes, ou ouvrir un formulaire public que les joueurs remplissent (les demandes arrivent en attente de validation).', 'wegame-tournoi' ) . '</p>';
		echo '<div class="wgt-wizard__grid">';
		echo '<p class="wgt-wizard__full"><label><input type="checkbox" name="registration_open" value="1" /> ' . esc_html__( 'Ouvrir le formulaire d’inscription public sur la page du tournoi', 'wegame-tournoi' ) . '</label></p>';
		echo '<p><label for="wgt-w-max">' . esc_html__( 'Nombre maximum d’inscriptions', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-max" min="0" name="registration_max" value="' . (int) $d['registration_max'] . '" class="small-text" /><span class="description">' . esc_html__( '0 = illimité. Une équipe refusée libère sa place.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-notify">' . esc_html__( 'E-mail averti à chaque inscription', 'wegame-tournoi' ) . '</label><input type="email" id="wgt-w-notify" class="regular-text" name="notify_email" value="' . esc_attr( get_option( 'admin_email' ) ) . '" /></p>';
		echo '<p class="wgt-wizard__full"><label for="wgt-w-msg">' . esc_html__( 'Message affiché après l’inscription', 'wegame-tournoi' ) . '</label><textarea id="wgt-w-msg" name="registration_msg" rows="2" class="large-text">' . esc_textarea( $d['registration_msg'] ) . '</textarea></p>';
		echo '</div>';

		echo '<h3>' . esc_html__( 'Publication', 'wegame-tournoi' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'La création génère une page dédiée au tournoi (listée dans « Tournois › Tous les tournois », pas dans « Pages »), avec ses onglets Tableau, Planning, Résultats, Équipes et, si vous l’ouvrez, Inscription. Tant que la page n’est pas publiée, seuls les administrateurs la voient. Son adresse s’affiche ensuite sur le tableau de bord.', 'wegame-tournoi' ) . '</p>';
		echo '<div class="wgt-wizard__choices">';
		echo '<label class="wgt-choice is-selected"><input type="radio" name="post_status" value="publish" checked="checked" />';
		echo '<span class="wgt-choice__title">' . esc_html__( 'Publier la page tout de suite', 'wegame-tournoi' ) . '</span>';
		echo '<span class="wgt-choice__desc">' . esc_html__( 'La page est en ligne dès maintenant : les joueurs peuvent la consulter et s’inscrire si le formulaire est ouvert.', 'wegame-tournoi' ) . '</span></label>';
		echo '<label class="wgt-choice"><input type="radio" name="post_status" value="draft" />';
		echo '<span class="wgt-choice__title">' . esc_html__( 'Garder en brouillon', 'wegame-tournoi' ) . '</span>';
		echo '<span class="wgt-choice__desc">' . esc_html__( 'Vous préparez les équipes et le contenu tranquillement, puis vous publiez la page depuis « Tous les tournois » ou « Modifier la page ».', 'wegame-tournoi' ) . '</span></label>';
		echo '<label class="wgt-choice"><input type="radio" name="post_status" value="none" />';
		echo '<span class="wgt-choice__title">' . esc_html__( 'Pas de page dédiée', 'wegame-tournoi' ) . '</span>';
		echo '<span class="wgt-choice__desc">' . esc_html__( 'Aucune page n’est visible pour ce tournoi. Vous placez vous-même le tableau, le formulaire ou le planning sur vos propres pages avec les codes courts.', 'wegame-tournoi' ) . '</span></label>';
		echo '</div>';

		echo '<p class="wgt-wizard__full"><label><input type="checkbox" name="create_registration_page" value="1" /> ' . esc_html__( 'Créer aussi une page d’inscription dédiée', 'wegame-tournoi' ) . '</label><span class="description">' . esc_html__( 'Une page WordPress ne contenant que le formulaire simplifié (Team, Pseudo, Mail, Téléphone) : idéale pour un QR code ou une affiche. Son adresse et son QR code apparaîtront sur le tableau de bord.', 'wegame-tournoi' ) . '</span></p>';

		echo '<div class="wgt-wizard__after">';
		echo '<h3>' . esc_html__( 'Et après la création ?', 'wegame-tournoi' ) . '</h3>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'Liens : dès la création, le tableau de bord affiche l’adresse de la page du tournoi et celle du formulaire d’inscription, prêtes à copier ou à transformer en QR code.', 'wegame-tournoi' ) . '</li>';
		echo '<li>' . esc_html__( 'Page du tournoi : elle est créée automatiquement. Elle n’apparaît pas dans le menu « Pages » de WordPress mais dans « Tournois › Tous les tournois » (bouton « Voir » de la barre du tournoi). Pour la publier ou la dépublier plus tard : « Modifier la page », puis le bouton Publier. L’option « Pas de page dédiée » se change dans Réglages du tournoi.', 'wegame-tournoi' ) . '</li>';
		echo '<li>' . esc_html__( 'Page d’inscription : si vous ouvrez les inscriptions, le formulaire est déjà dans l’onglet Inscription de la page du tournoi. Vous pouvez aussi créer une page WordPress séparée, plus simple à partager, en y collant le code court ci-dessous, puis la publier.', 'wegame-tournoi' ) . '</li>';
		echo '<li>' . esc_html__( 'Partage : un QR code ou un lien vers l’une de ces pages suffit aux joueurs pour s’inscrire puis suivre le tournoi en direct.', 'wegame-tournoi' ) . '</li>';
		echo '<li>' . esc_html__( 'Équipes : saisissez-les dans l’écran Équipes ou validez les inscriptions reçues, puis attribuez les positions. Le tableau de bord vous rappelle ces étapes.', 'wegame-tournoi' ) . '</li>';
		echo '</ol>';
		echo '<p><code>[wegame_inscription simple="yes"]</code> <span class="description">' . esc_html__( 'formulaire réduit (Team, Pseudo, Mail, Téléphone)', 'wegame-tournoi' ) . '</span><br /><code>[wegame_inscription]</code> <span class="description">' . esc_html__( 'formulaire complet avec la liste des joueurs', 'wegame-tournoi' ) . '</span></p>';
		echo '</div>';

		echo '<h3>' . esc_html__( 'Récapitulatif', 'wegame-tournoi' ) . '</h3>';
		echo '<dl class="wgt-wizard__summary" data-wgt-summary>';
		$summary = array(
			'title'    => __( 'Tournoi', 'wegame-tournoi' ),
			'format'   => __( 'Format', 'wegame-tournoi' ),
			'teams'    => __( 'Équipes', 'wegame-tournoi' ),
			'matches'  => __( 'Matchs à jouer', 'wegame-tournoi' ),
			'when'     => __( 'Quand', 'wegame-tournoi' ),
			'end'      => __( 'Fin estimée', 'wegame-tournoi' ),
			'signup'   => __( 'Inscriptions', 'wegame-tournoi' ),
			'status'   => __( 'Page du tournoi', 'wegame-tournoi' ),
		);
		foreach ( $summary as $key => $label ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd data-wgt-sum="' . esc_attr( $key ) . '">—</dd>';
		}
		echo '</dl>';
		echo '<p class="description">' . esc_html__( 'Tout reste modifiable après la création. Le tableau des matchs est créé immédiatement ; il se remplit à mesure que les équipes reçoivent une position.', 'wegame-tournoi' ) . '</p>';
		echo '</section>';

		// Navigation.
		echo '<div class="wgt-wizard__nav">';
		echo '<button type="button" class="button" data-wgt-prev>' . esc_html__( '← Précédent', 'wegame-tournoi' ) . '</button>';
		echo '<button type="button" class="button button-primary" data-wgt-next>' . esc_html__( 'Suivant →', 'wegame-tournoi' ) . '</button>';
		echo '<button type="submit" class="button button-primary button-hero" data-wgt-submit>' . esc_html__( 'Créer le tournoi', 'wegame-tournoi' ) . '</button>';
		echo '</div>';

		// Libellés pour le récapitulatif calculé par le script.
		echo '<script type="application/json" data-wgt-wizard-i18n>' . wp_json_encode(
			array(
				'formats'  => array(
					'single' => $formats['single'][0],
					'double' => $formats['double'][0],
					'groups' => $formats['groups'][0],
				),
				'teams'    => __( '%d équipes', 'wegame-tournoi' ),
				'groups'   => __( '%1$d poules, %2$d qualifiés par poule', 'wegame-tournoi' ),
				'matches'  => __( 'environ %d', 'wegame-tournoi' ),
				'open'     => __( 'Formulaire public ouvert', 'wegame-tournoi' ),
				'closed'   => __( 'Saisie par l’organisation', 'wegame-tournoi' ),
				'publish'  => __( 'Publiée immédiatement', 'wegame-tournoi' ),
				'draft'    => __( 'Brouillon, à publier plus tard', 'wegame-tournoi' ),
				'none'     => __( 'Aucune page dédiée (codes courts)', 'wegame-tournoi' ),
				'at'       => __( 'à', 'wegame-tournoi' ),
				'required' => __( 'Le nom du tournoi est obligatoire.', 'wegame-tournoi' ),
			)
		) . '</script>';

		echo '</form>';
	}

	/**
	 * Zone sensible d'un tournoi : réinitialisation et suppression, avec
	 * confirmation par saisie.
	 *
	 * @param int $tid Tournoi.
	 */
	protected static function danger_zone( $tid ) {
		echo '<h2 id="wgt-danger">' . esc_html__( 'Zone sensible : réinitialiser ou supprimer le tournoi', 'wegame-tournoi' ) . '</h2>';
		echo '<div class="wgt-danger">';
		echo '<p><strong>' . esc_html__( 'Ces actions sont irréversibles et ne concernent que le tournoi sélectionné.', 'wegame-tournoi' ) . '</strong></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p><label for="wgt-confirm">' . esc_html__( 'Pour vider le tournoi (équipes + résultats), saisissez REINITIALISER :', 'wegame-tournoi' ) . '</label><br />';
		echo '<input type="text" id="wgt-confirm" name="confirm" value="" autocomplete="off" placeholder="REINITIALISER" /> ';
		echo '<button class="button button-link-delete" name="tool" value="reset_all" onclick="return confirm(\'' . esc_js( __( 'Supprimer définitivement toutes les équipes et tous les résultats de ce tournoi ?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Réinitialiser', 'wegame-tournoi' ) . '</button></p>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p><label for="wgt-confirm-del">' . esc_html__( 'Pour supprimer le tournoi et sa page, saisissez SUPPRIMER :', 'wegame-tournoi' ) . '</label><br />';
		echo '<input type="text" id="wgt-confirm-del" name="confirm" value="" autocomplete="off" placeholder="SUPPRIMER" /> ';
		echo '<button class="button button-link-delete" name="tool" value="delete_tournament" onclick="return confirm(\'' . esc_js( __( 'Supprimer définitivement ce tournoi, sa page et toutes ses données ?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Supprimer le tournoi', 'wegame-tournoi' ) . '</button></p>';
		echo '</form>';
		echo '</div>';

	}

	/**
	 * Liens publics du tournoi : page, inscription, codes courts.
	 *
	 * @param int   $tid Tournoi.
	 * @param array $s   Réglages.
	 */
	protected static function public_links( $tid, $s ) {
		$has_page = WGT_Tournament::has_public_page( $tid );
		$status   = get_post_status( $tid );
		$url      = get_permalink( $tid );
		$slug     = get_post_field( 'post_name', $tid );
		$reg_page = WGT_Tournament::registration_page( $tid );

		echo '<div class="wgt-links"><h2>' . esc_html__( 'Liens publics', 'wegame-tournoi' ) . '</h2>';

		if ( $has_page && 'publish' !== $status ) {
			echo '<p class="wgt-links__warn">' . esc_html__( 'La page du tournoi est en brouillon : son adresse ne fonctionnera pour les visiteurs qu’après publication.', 'wegame-tournoi' ) . '</p>';
		}

		if ( $has_page ) {
			self::link_row(
				__( 'Page du tournoi', 'wegame-tournoi' ),
				__( 'tableau, planning, résultats, équipes', 'wegame-tournoi' ),
				$url,
				'tournoi'
			);
		}

		// Page d'inscription : page WordPress dédiée si elle existe, sinon
		// l'onglet Inscription de la page du tournoi.
		if ( $reg_page ) {
			$reg_url    = get_permalink( $reg_page );
			$reg_status = get_post_status( $reg_page );
			self::link_row(
				__( 'Page d’inscription', 'wegame-tournoi' ),
				'publish' === $reg_status
					? __( 'à partager aux joueurs', 'wegame-tournoi' )
					: __( 'non publiée : publiez-la pour qu’elle soit accessible', 'wegame-tournoi' ),
				$reg_url,
				'inscription',
				get_edit_post_link( $reg_page )
			);
		} elseif ( $has_page && ! empty( $s['registration_open'] ) ) {
			self::link_row(
				__( 'Formulaire d’inscription', 'wegame-tournoi' ),
				__( 'onglet Inscription de la page du tournoi', 'wegame-tournoi' ),
				$url . '#wgt-registration',
				'inscription'
			);
		}

		if ( ! $reg_page ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
			wp_nonce_field( 'wgt_create_registration_page' );
			echo '<input type="hidden" name="action" value="wgt_create_registration_page" />';
			echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
			echo '<button class="button">' . esc_html__( 'Créer une page d’inscription dédiée', 'wegame-tournoi' ) . '</button> ';
			echo '<span class="description">' . esc_html__( 'Une page WordPress ne contenant que le formulaire simplifié : plus courte à partager et à imprimer en QR code.', 'wegame-tournoi' ) . '</span>';
			echo '</form>';
		}

		if ( empty( $s['registration_open'] ) ) {
			echo '<p class="description">' . esc_html__( 'Les inscriptions sont fermées : le formulaire affichera « inscriptions fermées » tant que vous ne les ouvrez pas dans Réglages du tournoi.', 'wegame-tournoi' ) . '</p>';
		}

		if ( ! $has_page ) {
			echo '<p class="description">' . esc_html__( 'Ce tournoi n’a pas de page dédiée. Collez ces codes courts dans vos propres pages, puis publiez-les :', 'wegame-tournoi' ) . '</p>';
			echo '<p><code>[wegame_tournoi tournoi="' . esc_html( $slug ) . '"]</code> ' . esc_html__( 'page complète à onglets', 'wegame-tournoi' ) . '<br />';
			echo '<code>[wegame_inscription simple="yes" tournoi="' . esc_html( $slug ) . '"]</code> ' . esc_html__( 'formulaire d’inscription simplifié', 'wegame-tournoi' ) . '<br />';
			echo '<code>[wegame_tableau tournoi="' . esc_html( $slug ) . '"]</code> ' . esc_html__( 'tableau seul (écran de régie)', 'wegame-tournoi' ) . '</p>';
		}

		$pages = self::pages_using_shortcodes( $tid );
		if ( $pages ) {
			echo '<p><strong>' . esc_html__( 'Pages du site utilisant les codes courts', 'wegame-tournoi' ) . '</strong><br />';
			foreach ( $pages as $page ) {
				echo '<a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $page ) ) . '</a>';
				if ( 'publish' !== $page->post_status ) {
					echo ' <em>(' . esc_html__( 'non publiée', 'wegame-tournoi' ) . ')</em>';
				}
				echo ' — <a href="' . esc_url( get_edit_post_link( $page->ID ) ) . '">' . esc_html__( 'modifier', 'wegame-tournoi' ) . '</a><br />';
			}
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Une ligne de lien public : adresse copiable, boutons et QR code.
	 *
	 * @param string $label    Intitulé.
	 * @param string $hint     Précision affichée à côté.
	 * @param string $url      Adresse.
	 * @param string $filename Base du nom de fichier du QR code.
	 * @param string $edit_url Lien de modification éventuel.
	 */
	protected static function link_row( $label, $hint, $url, $filename, $edit_url = '' ) {
		$slug = sanitize_title( get_bloginfo( 'name' ) . '-' . $filename );

		echo '<div class="wgt-link">';
		echo '<p class="wgt-link__head"><strong>' . esc_html( $label ) . '</strong> — ' . esc_html( $hint ) . '</p>';
		echo '<p class="wgt-link__row">';
		echo '<input type="text" class="regular-text code" readonly value="' . esc_attr( $url ) . '" onfocus="this.select();" /> ';
		echo '<button type="button" class="button button-small" data-wgt-copy>' . esc_html__( 'Copier', 'wegame-tournoi' ) . '</button> ';
		echo '<a class="button button-small" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Ouvrir', 'wegame-tournoi' ) . '</a>';
		if ( $edit_url ) {
			echo ' <a class="button button-small" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Modifier', 'wegame-tournoi' ) . '</a>';
		}
		echo '</p>';
		/*
		 * QR code calculé côté serveur : il s'affiche même si les scripts de
		 * l'administration sont regroupés ou différés par une extension de
		 * cache, et aucune adresse n'est envoyée à un service extérieur.
		 */
		$svg = WGT_QR::svg_data_uri( $url );
		if ( '' !== $svg ) {
			$png = WGT_QR::png_data_uri( $url, 16 );

			echo '<div class="wgt-qr">';
			echo '<img class="wgt-qr__img" src="' . esc_attr( $svg ) . '" width="120" height="120" alt="' . esc_attr(
				sprintf(
					/* translators: %s: intitulé du lien */
					__( 'QR code vers %s', 'wegame-tournoi' ),
					$label
				)
			) . '" />';
			echo '<span class="wgt-qr__actions"><span class="wgt-qr__label">' . esc_html__( 'Télécharger le QR code', 'wegame-tournoi' ) . '</span>';
			if ( '' !== $png ) {
				echo '<a class="button button-small" href="' . esc_attr( $png ) . '" download="' . esc_attr( $slug ) . '.png">' . esc_html__( 'PNG', 'wegame-tournoi' ) . '</a>';
			}
			echo '<a class="button button-small" href="' . esc_attr( $svg ) . '" download="' . esc_attr( $slug ) . '.svg">' . esc_html__( 'SVG', 'wegame-tournoi' ) . '</a>';
			echo '</span></div>';
		}
		echo '</div>';
	}

	/**
	 * Pages et articles du site contenant un code court de l'extension.
	 *
	 * @param int $tid Tournoi (les codes courts sans attribut visent le tournoi par défaut : ils sont listés aussi).
	 * @return array Objets WP_Post.
	 */
	protected static function pages_using_shortcodes( $tid ) {
		global $wpdb;

		$rows = $wpdb->get_results(
			"SELECT ID, post_title, post_status, post_type, post_name FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status IN ('publish','draft','pending','private') AND post_content LIKE '%[wegame_%' ORDER BY post_title ASC LIMIT 50" // phpcs:ignore WordPress.DB
		);

		$slug = get_post_field( 'post_name', $tid );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$content = get_post_field( 'post_content', $row->ID );
			// Codes courts visant explicitement un autre tournoi : ignorés.
			if ( preg_match_all( '/\[wegame_[a-z_]+[^\]]*\b(?:tournoi|tournament)="([^"]*)"/', $content, $m ) ) {
				$targets = array_unique( $m[1] );
				if ( ! in_array( $slug, $targets, true ) && ! in_array( (string) $tid, $targets, true ) && count( $targets ) === substr_count( $content, '[wegame_' ) ) {
					continue;
				}
			}
			$out[] = get_post( $row->ID );
		}

		return $out;
	}

	/**
	 * Panneau « Prochaines étapes » du tableau de bord, tant que le tournoi
	 * n'est pas prêt à démarrer.
	 *
	 * @param int   $tid   Tournoi.
	 * @param array $stats Statistiques.
	 * @param array $s     Réglages.
	 */
	protected static function next_steps( $tid, $stats, $s ) {
		if ( $stats['matches_done'] > 0 ) {
			return;
		}

		$teams_url   = add_query_arg( array( 'page' => 'wgt-teams', 'tournament' => $tid ), admin_url( 'admin.php' ) );
		$matches_url = add_query_arg( array( 'page' => 'wgt-matches', 'tournament' => $tid ), admin_url( 'admin.php' ) );
		$public_url  = get_permalink( $tid );

		$done_teams = $stats['teams_active'] >= $stats['teams_max'];
		$done_seed  = $stats['teams_seeded'] >= $stats['teams_max'];

		$steps = array();

		if ( 'publish' !== get_post_status( $tid ) && WGT_Tournament::has_public_page( $tid ) ) {
			$steps[] = array(
				false,
				__( 'Publier la page du tournoi', 'wegame-tournoi' ),
				__( 'La page est en brouillon : seuls les administrateurs la voient. Publiez-la quand le contenu est prêt pour que les joueurs puissent la consulter et s’inscrire.', 'wegame-tournoi' ),
				get_edit_post_link( $tid, 'raw' ),
				__( 'Modifier et publier', 'wegame-tournoi' ),
			);
		}

		$steps[] = array(
				$done_teams,
				sprintf(
					/* translators: 1: équipes validées, 2: équipes attendues */
					__( 'Constituer les équipes (%1$d / %2$d validées)', 'wegame-tournoi' ),
					$stats['teams_active'],
					$stats['teams_max']
				),
				! empty( $s['registration_open'] )
					? __( 'Le formulaire public est ouvert : validez les demandes reçues, ou ajoutez des équipes vous-même.', 'wegame-tournoi' )
					: __( 'Ajoutez chaque équipe depuis l’écran Équipes, ou ouvrez les inscriptions publiques dans Réglages du tournoi.', 'wegame-tournoi' ),
				$teams_url,
				__( 'Ouvrir Équipes', 'wegame-tournoi' ),
			);
		$steps[] = array(
				$done_seed,
				__( 'Attribuer les positions', 'wegame-tournoi' ),
				__( 'Chaque équipe validée reçoit un numéro de 1 à N qui la place dans le tableau. Le bouton « Tirage au sort des positions » le fait pour vous.', 'wegame-tournoi' ),
				$teams_url,
				__( 'Placer les équipes', 'wegame-tournoi' ),
			);
		if ( WGT_Tournament::has_public_page( $tid ) ) {
			$steps[] = array(
				false,
				__( 'Vérifier la page publique', 'wegame-tournoi' ),
				__( 'La page du tournoi affiche tableau, planning, résultats et inscriptions. Ses liens sont dans le bloc « Liens publics » ci-dessus.', 'wegame-tournoi' ),
				$public_url,
				__( 'Voir la page', 'wegame-tournoi' ),
			);
		} else {
			$steps[] = array(
				false,
				__( 'Placer les codes courts sur vos pages', 'wegame-tournoi' ),
				__( 'Ce tournoi n’a pas de page dédiée : copiez les codes courts du bloc « Liens publics » dans les pages de votre choix, puis publiez-les.', 'wegame-tournoi' ),
				add_query_arg( 'post_type', 'page', admin_url( 'post-new.php' ) ),
				__( 'Créer une page', 'wegame-tournoi' ),
			);
		}
		$steps[] = array(
				false,
				__( 'Le jour J : saisir les scores', 'wegame-tournoi' ),
				__( 'Dans Matchs & scores, entrez le résultat de chaque match : le vainqueur passe automatiquement au tour suivant et la page publique se met à jour toute seule.', 'wegame-tournoi' ),
				$matches_url,
				__( 'Ouvrir Matchs & scores', 'wegame-tournoi' ),
			);

		echo '<div class="wgt-next"><h2>' . esc_html__( 'Prochaines étapes', 'wegame-tournoi' ) . '</h2><ol class="wgt-next__list">';
		foreach ( $steps as $step ) {
			echo '<li class="wgt-next__item' . ( $step[0] ? ' is-done' : '' ) . '">';
			echo '<strong>' . esc_html( $step[1] ) . '</strong>';
			echo '<span>' . esc_html( $step[2] ) . '</span>';
			echo '<a class="button button-small" href="' . esc_url( $step[3] ) . '">' . esc_html( $step[4] ) . '</a>';
			echo '</li>';
		}
		echo '</ol></div>';
	}

	/**
	 * Duplication d'un tournoi.
	 */
	public static function handle_duplicate_tournament() {
		self::guard( 'wgt_duplicate_tournament' );

		$source = self::posted_tournament();
		$title  = isset( $_POST['title'] ) ? wp_unslash( $_POST['title'] ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$teams  = ! empty( $_POST['with_teams'] );

		$id = WGT_Tournament::duplicate( $source, $title, $teams );

		if ( is_wp_error( $id ) ) {
			self::back( 'wgt-dashboard', 'error', $id->get_error_message(), $source );
		}

		self::back( 'wgt-dashboard', 'updated', __( 'Tournoi dupliqué.', 'wegame-tournoi' ), $id );
	}

	/**
	 * Réglages d'un tournoi.
	 */
	public static function handle_save_tournament() {
		self::guard( 'wgt_save_tournament' );

		$tid = self::posted_tournament();
		if ( ! $tid ) {
			self::back( 'wgt-tournament', 'error', __( 'Tournoi introuvable.', 'wegame-tournoi' ) );
		}

		$before = WGT_Tournament::settings( $tid );

		WGT_Tournament::save_settings( $tid, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		$after = WGT_Tournament::settings( $tid );

		// Le format a changé : la structure du tableau doit suivre.
		$structural = array( 'format', 'team_count', 'group_count', 'qualifiers_per_group', 'group_mode', 'bracket_reset', 'bo_group', 'bo_r64', 'bo_r32', 'bo_r16', 'bo_qf', 'bo_sf', 'bo_final', 'bo_lb', 'third_place' );
		foreach ( $structural as $key ) {
			if ( $before[ $key ] !== $after[ $key ] ) {
				WGT_Data::ensure_bracket( $tid );
				WGT_Data::recalculate( $tid );
				break;
			}
		}

		self::back( 'wgt-tournament', 'updated', __( 'Réglages du tournoi enregistrés.', 'wegame-tournoi' ), $tid );
	}

	/* ---------------------------------------------------------------------
	 * Traitements — équipes, matchs, outils
	 * ------------------------------------------------------------------ */

	/**
	 * Création / édition d'une équipe.
	 */
	public static function handle_save_team() {
		self::guard( 'wgt_save_team' );

		$tid = self::posted_tournament();

		$data = array(
			'tournament_id' => $tid,
			'id'            => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0,
			'name'          => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'tag'           => isset( $_POST['tag'] ) ? wp_unslash( $_POST['tag'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'seed'          => isset( $_POST['seed'] ) ? (int) $_POST['seed'] : 0,
			'group_id'      => isset( $_POST['group_id'] ) ? (int) $_POST['group_id'] : 0,
			'captain'       => isset( $_POST['captain'] ) ? wp_unslash( $_POST['captain'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'email'         => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'phone'         => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'players'       => isset( $_POST['players'] ) ? wp_unslash( $_POST['players'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'notes'         => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			'status'        => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'pending', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		);

		$result = WGT_Data::save_team( $data );

		if ( is_wp_error( $result ) ) {
			$extra = $data['id'] ? array( 'edit' => $data['id'] ) : array();
			self::back( 'wgt-teams', 'error', $result->get_error_message(), $tid, $extra );
		}

		self::back( 'wgt-teams', 'updated', __( 'Équipe enregistrée.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Suppression d'une équipe.
	 */
	public static function handle_delete_team() {
		self::guard( 'wgt_delete_team' );

		$tid = self::posted_tournament();
		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;

		if ( $id ) {
			WGT_Data::delete_team( $id );
		}

		self::back( 'wgt-teams', 'updated', __( 'Équipe supprimée.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Enregistrement d'un résultat.
	 */
	public static function handle_save_result() {
		self::guard( 'wgt_save_result' );

		$tid      = self::posted_tournament();
		$match_id = isset( $_POST['match_id'] ) ? (int) $_POST['match_id'] : 0;

		$games = array();
		if ( isset( $_POST['games'] ) && is_array( $_POST['games'] ) ) {
			foreach ( wp_unslash( $_POST['games'] ) as $game ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
				$games[] = array(
					'score1' => isset( $game['score1'] ) ? sanitize_text_field( $game['score1'] ) : '',
					'score2' => isset( $game['score2'] ) ? sanitize_text_field( $game['score2'] ) : '',
					'map'    => isset( $game['map'] ) ? sanitize_text_field( $game['map'] ) : '',
				);
			}
		}

		$result = WGT_Data::save_result(
			$match_id,
			array(
				'status'     => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'pending',
				'score1'     => isset( $_POST['score1'] ) ? (int) $_POST['score1'] : 0,
				'score2'     => isset( $_POST['score2'] ) ? (int) $_POST['score2'] : 0,
				'referee'    => isset( $_POST['referee'] ) ? sanitize_text_field( wp_unslash( $_POST['referee'] ) ) : '',
				'stations'   => isset( $_POST['stations'] ) ? sanitize_text_field( wp_unslash( $_POST['stations'] ) ) : '',
				'start_time' => isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '',
				'end_time'   => isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '',
				'games'      => $games,
			)
		);

		if ( is_wp_error( $result ) ) {
			self::back( 'wgt-matches', 'error', $result->get_error_message(), $tid );
		}

		self::back( 'wgt-matches', 'updated', __( 'Résultat enregistré.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Outils.
	 */
	public static function handle_tools() {
		self::guard( 'wgt_tools' );

		$tid  = self::posted_tournament();
		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : '';

		if ( ! $tid ) {
			self::back( 'wgt-dashboard', 'error', __( 'Aucun tournoi sélectionné.', 'wegame-tournoi' ) );
		}

		if ( 'autoseed' === $tool ) {
			$placed = WGT_Data::autoseed( $tid, true );
			self::back(
				'wgt-teams',
				'updated',
				sprintf(
					/* translators: %d: nombre d'équipes */
					__( '%d équipe(s) placée(s) aléatoirement dans le tableau.', 'wegame-tournoi' ),
					$placed
				),
				$tid
			);
		}

		if ( 'reset_results' === $tool ) {
			WGT_Data::reset_results( $tid );
			self::back( 'wgt-matches', 'updated', __( 'Tous les résultats ont été remis à zéro.', 'wegame-tournoi' ), $tid );
		}

		if ( 'rebuild' === $tool ) {
			WGT_Data::ensure_bracket( $tid );
			WGT_Data::recalculate( $tid );
			self::back( 'wgt-dashboard', 'updated', __( 'Tableau régénéré.', 'wegame-tournoi' ), $tid );
		}

		if ( 'reschedule' === $tool ) {
			WGT_Data::rebuild_schedule( $tid );
			self::back( 'wgt-planning', 'updated', __( 'Planning recalculé d’après les réglages du tournoi.', 'wegame-tournoi' ), $tid );
		}

		if ( 'validate_all' === $tool ) {
			global $wpdb;
			$wpdb->query(
				$wpdb->prepare(
					'UPDATE ' . wgt_table( 'teams' ) . " SET status = 'active' WHERE tournament_id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL
					$tid
				)
			);
			WGT_Data::recalculate( $tid );
			self::back( 'wgt-teams', 'updated', __( 'Toutes les inscriptions en attente ont été validées.', 'wegame-tournoi' ), $tid );
		}

		if ( 'reset_all' === $tool ) {
			$confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
			if ( 'REINITIALISER' !== strtoupper( remove_accents( $confirm ) ) ) {
				self::back( 'wgt-dashboard', 'error', __( 'Réinitialisation annulée : le mot de confirmation ne correspond pas.', 'wegame-tournoi' ), $tid );
			}
			WGT_Data::reset_all( $tid );
			self::back( 'wgt-dashboard', 'updated', __( 'Tournoi réinitialisé : équipes et résultats supprimés, tableau vierge recréé.', 'wegame-tournoi' ), $tid );
		}

		if ( 'delete_tournament' === $tool ) {
			$confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
			if ( 'SUPPRIMER' !== strtoupper( remove_accents( $confirm ) ) ) {
				self::back( 'wgt-dashboard', 'error', __( 'Suppression annulée : le mot de confirmation ne correspond pas.', 'wegame-tournoi' ), $tid );
			}
			WGT_Tournament::delete( $tid );
			self::back( 'wgt-dashboard', 'updated', __( 'Tournoi supprimé.', 'wegame-tournoi' ) );
		}

		self::back( 'wgt-dashboard', 'error', __( 'Action inconnue.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Réglages généraux.
	 */
	public static function handle_save_settings() {
		self::guard( 'wgt_save_settings' );

		WGT_Settings::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput

		if ( isset( $_POST['default_tournament'] ) ) {
			$default = (int) $_POST['default_tournament'];
			update_option( 'wgt_default_tournament', WGT_Tournament::exists( $default ) ? $default : 0 );
		}
		self::back( 'wgt-settings', 'updated', __( 'Réglages enregistrés.', 'wegame-tournoi' ) );
	}

	/* ---------------------------------------------------------------------
	 * Traitements — sauvegarde, mises à jour, aperçu
	 * ------------------------------------------------------------------ */

	/**
	 * Téléchargement de la sauvegarde JSON.
	 */
	public static function handle_export() {
		self::guard( 'wgt_export' );

		$tid = self::posted_tournament();
		if ( ! $tid ) {
			self::back( 'wgt-dashboard', 'error', __( 'Aucun tournoi sélectionné.', 'wegame-tournoi' ) );
		}

		$json = wp_json_encode( WGT_IO::build_export( $tid ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . WGT_IO::filename( $tid ) . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput
		exit;
	}

	/**
	 * Restauration depuis un fichier JSON.
	 */
	public static function handle_import() {
		self::guard( 'wgt_import' );

		$tid = self::posted_tournament();
		if ( ! $tid ) {
			self::back( 'wgt-dashboard', 'error', __( 'Aucun tournoi sélectionné.', 'wegame-tournoi' ) );
		}

		if ( empty( $_FILES['backup']['tmp_name'] ) || ! is_uploaded_file( $_FILES['backup']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
			self::back( 'wgt-dashboard', 'error', __( 'Aucun fichier reçu.', 'wegame-tournoi' ), $tid );
		}

		if ( isset( $_FILES['backup']['error'] ) && UPLOAD_ERR_OK !== (int) $_FILES['backup']['error'] ) {
			self::back( 'wgt-dashboard', 'error', __( 'Le téléversement du fichier a échoué.', 'wegame-tournoi' ), $tid );
		}

		$size = isset( $_FILES['backup']['size'] ) ? (int) $_FILES['backup']['size'] : 0;
		if ( $size <= 0 || $size > 5 * MB_IN_BYTES ) {
			self::back( 'wgt-dashboard', 'error', __( 'Fichier vide ou trop volumineux (5 Mo maximum).', 'wegame-tournoi' ), $tid );
		}

		$path = sanitize_text_field( $_FILES['backup']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
		$json = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions

		if ( false === $json ) {
			self::back( 'wgt-dashboard', 'error', __( 'Impossible de lire le fichier.', 'wegame-tournoi' ), $tid );
		}

		$result = WGT_IO::import( $json, $tid, ! empty( $_POST['with_settings'] ) );

		if ( is_wp_error( $result ) ) {
			self::back( 'wgt-dashboard', 'error', $result->get_error_message(), $tid );
		}

		self::back( 'wgt-dashboard', 'updated', __( 'Sauvegarde restaurée.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Vérification manuelle des mises à jour.
	 */
	/**
	 * Crée la page d'inscription du tournoi courant.
	 */
	public static function handle_create_registration_page() {
		self::guard( 'wgt_create_registration_page' );

		$tid = isset( $_POST['tournament_id'] ) ? (int) $_POST['tournament_id'] : 0;
		if ( ! $tid ) {
			self::back( 'wgt-dashboard', 'error', __( 'Tournoi introuvable.', 'wegame-tournoi' ) );
		}

		$page_id = WGT_Tournament::create_registration_page( $tid );
		if ( is_wp_error( $page_id ) ) {
			self::back( 'wgt-dashboard', 'error', $page_id->get_error_message(), $tid );
		}

		self::back( 'wgt-dashboard', 'updated', __( 'Page d’inscription créée et publiée.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * E-mail de test : vérifie que le site sait envoyer des messages.
	 */
	public static function handle_test_mail() {
		self::guard( 'wgt_test_mail' );

		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : '';
		if ( ! $to || ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$error = '';
		$catch = function ( $wp_error ) use ( &$error ) {
			$error = $wp_error->get_error_message();
		};
		add_action( 'wp_mail_failed', $catch );

		$sent = wp_mail(
			$to,
			sprintf(
				/* translators: %s: nom du site */
				__( '[%s] E-mail de test We Game Tournoi', 'wegame-tournoi' ),
				get_bloginfo( 'name' )
			),
			__( 'Si vous recevez ce message, le site peut envoyer les notifications d’inscription.', 'wegame-tournoi' ) . "\n" . home_url( '/' )
		);

		remove_action( 'wp_mail_failed', $catch );

		if ( $sent ) {
			self::back(
				'wgt-settings',
				'updated',
				sprintf(
					/* translators: %s: adresse e-mail */
					__( 'E-mail de test remis au serveur pour %s. Vérifiez la boîte de réception (et les indésirables) ; s’il n’arrive pas, l’hébergement ne relaie pas les e-mails : installez une extension SMTP.', 'wegame-tournoi' ),
					$to
				)
			);
		}

		self::back(
			'wgt-settings',
			'error',
			sprintf(
				/* translators: %s: message d'erreur */
				__( 'Le serveur n’a pas pu envoyer l’e-mail : %s. Configurez l’envoi (extension SMTP) avant d’ouvrir les inscriptions.', 'wegame-tournoi' ),
				$error ? $error : __( 'aucune fonction mail disponible', 'wegame-tournoi' )
			)
		);
	}

	public static function handle_check_update() {
		self::guard( 'wgt_check_update' );

		WGT_Updater::force_check();
		$manifest = WGT_Updater::manifest( true );

		if ( ! $manifest ) {
			self::back( 'wgt-settings', 'error', __( 'Aucun manifeste valide n’a pu être récupéré. Vérifiez l’URL (le paquet doit être servi en HTTPS).', 'wegame-tournoi' ) );
		}

		if ( version_compare( $manifest['version'], WGT_VERSION, '>' ) ) {
			self::back(
				'wgt-settings',
				'updated',
				sprintf(
					/* translators: %s: numéro de version */
					__( 'Version %s disponible. Rendez-vous sur la page Extensions pour l’installer.', 'wegame-tournoi' ),
					$manifest['version']
				)
			);
		}

		self::back( 'wgt-settings', 'updated', __( 'L’extension est à jour.', 'wegame-tournoi' ) );
	}

	/**
	 * Contenu de l'iframe d'aperçu.
	 */
	public static function handle_preview_frame() {
		self::guard( 'wgt_preview_frame' );

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'full';
		if ( ! in_array( $view, WGT_Render::views(), true ) ) {
			$view = 'full';
		}

		$tid = isset( $_GET['tournament'] ) ? (int) $_GET['tournament'] : 0;
		if ( ! WGT_Tournament::exists( $tid ) ) {
			$tid = WGT_Tournament::current_public();
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );

		$css = WGT_URL . 'assets/css/wgt-public.css?ver=' . rawurlencode( WGT_VERSION );
		$js  = WGT_URL . 'assets/js/wgt-public.js?ver=' . rawurlencode( WGT_VERSION );

		echo '<!DOCTYPE html><html ' . get_language_attributes() . '><head><meta charset="utf-8" />';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
		echo '<title>' . esc_html__( 'Aperçu', 'wegame-tournoi' ) . '</title>';
		echo '<link rel="stylesheet" href="' . esc_url( $css ) . '" />';
		echo '<style>html,body{margin:0;padding:0;background:transparent}</style>';
		echo '</head><body>';

		echo WGT_Render::view( $view, array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput

		echo '<script>var WGT_CFG={endpoint:"",interval:0,i18n:{}};</script>';
		echo '<script src="' . esc_url( $js ) . '"></script>';
		echo '<script>
(function(){
	function send(){
		parent.postMessage({ wgtPreviewHeight: document.documentElement.scrollHeight }, window.location.origin);
	}
	window.addEventListener("load", send);
	window.addEventListener("resize", send);
	document.addEventListener("click", function(){ setTimeout(send, 60); });
	setInterval(send, 1000);
})();
</script>';

		echo '</body></html>';
		exit;
	}

	/* ---------------------------------------------------------------------
	 * Pages
	 * ------------------------------------------------------------------ */

	/**
	 * Tableau de bord.
	 */
	public static function page_dashboard() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Tournois', 'wegame-tournoi' ) );
			return;
		}

		$settings = WGT_Tournament::settings( $tid );
		$stats    = WGT_Data::get_stats( $tid );
		$teams    = WGT_Data::get_teams_map( $tid );
		$rounds   = WGT_Data::get_matches_by_round( $tid );
		$labels   = WGT_Bracket::round_order( $settings );

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Tableau de bord', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-dashboard' );

		echo '<div class="wgt-cards">';
		$cards = array(
			array( __( 'Équipes inscrites', 'wegame-tournoi' ), $stats['teams_total'] ),
			array( __( 'Équipes validées', 'wegame-tournoi' ), $stats['teams_active'] ),
			array( __( 'Positions attribuées', 'wegame-tournoi' ), $stats['teams_seeded'] . ' / ' . $stats['teams_max'] ),
			array( __( 'Matchs joués', 'wegame-tournoi' ), $stats['matches_done'] . ' / ' . $stats['matches_total'] ),
		);
		foreach ( $cards as $card ) {
			echo '<div class="wgt-card"><span class="wgt-card__value">' . esc_html( $card[1] ) . '</span><span class="wgt-card__label">' . esc_html( $card[0] ) . '</span></div>';
		}
		echo '</div>';

		if ( $stats['champion_id'] ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'Vainqueur :', 'wegame-tournoi' ) . '</strong> ' . esc_html( WGT_Data::team_name( $stats['champion_id'], $teams ) );
			if ( $stats['third_id'] ) {
				echo ' — ' . esc_html__( '3e place :', 'wegame-tournoi' ) . ' ' . esc_html( WGT_Data::team_name( $stats['third_id'], $teams ) );
			}
			echo '</p></div>';
		}

		if ( $stats['teams_seeded'] < $stats['teams_max'] ) {
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %d: nombre d'équipes attendu */
					__( 'Le tableau n’est pas complet : attribuez une position (1 à %d) à chaque équipe validée depuis l’écran Équipes.', 'wegame-tournoi' ),
					$stats['teams_max']
				)
			) . '</p></div>';
		}

		if ( 'groups' === $settings['format'] && 'manual' === $settings['group_mode'] ) {
			$layout   = WGT_Bracket::group_layout( $settings );
			$expected = (int) ceil( $layout['count'] / max( 1, $layout['groups'] ) );
			$uneven   = array();
			$tiny     = array();
			for ( $g = 1; $g <= $layout['groups']; $g++ ) {
				$size = count( WGT_Data::group_members( $tid, $g ) );
				if ( $size < 2 ) {
					$tiny[] = WGT_Data::group_name( $g ) . ' (' . $size . ')';
				} elseif ( $size !== $expected ) {
					$uneven[] = WGT_Data::group_name( $g ) . ' (' . $size . ')';
				}
			}
			if ( $tiny ) {
				echo '<div class="notice notice-error"><p>' . esc_html(
					sprintf(
						/* translators: %s: liste des poules */
						__( 'Poules incomplètes : %s. Une poule doit compter au moins 2 équipes validées pour que ses matchs existent ; attribuez une poule à chaque équipe depuis l’écran Équipes.', 'wegame-tournoi' ),
						implode( ', ', $tiny )
					)
				) . '</p></div>';
			}
			if ( $uneven ) {
				echo '<div class="notice notice-warning"><p>' . esc_html(
					sprintf(
						/* translators: 1: liste des poules, 2: effectif attendu */
						__( 'Poules déséquilibrées : %1$s (effectif de référence : %2$d). Chaque poule joue ses propres matchs, mais une poule plus grande joue davantage de rencontres ; le nombre de qualifiés est limité par la plus petite poule.', 'wegame-tournoi' ),
						implode( ', ', $uneven ),
						$expected
					)
				) . '</p></div>';
			}
		}

		self::public_links( $tid, $settings );
		self::next_steps( $tid, $stats, $settings );

		// Aperçu du tableau.
		echo '<h2>' . esc_html__( 'Aperçu du tableau', 'wegame-tournoi' ) . '</h2>';
		echo '<div class="wgt-admin-bracket">';
		foreach ( $labels as $round => $round_label ) {
			if ( empty( $rounds[ $round ] ) ) {
				continue;
			}
			echo '<div class="wgt-admin-round"><h3>' . esc_html( $round_label ) . '</h3>';
			foreach ( $rounds[ $round ] as $match ) {
				$n1 = (int) $match['team1_id'] ? WGT_Data::team_name( (int) $match['team1_id'], $teams ) : WGT_Data::source_label( $match['src1'] );
				$n2 = (int) $match['team2_id'] ? WGT_Data::team_name( (int) $match['team2_id'], $teams ) : WGT_Data::source_label( $match['src2'] );
				echo '<div class="wgt-admin-match wgt-admin-match--' . esc_attr( $match['status'] ) . '">';
				echo '<strong>' . esc_html( $match['code'] ) . '</strong> ';
				echo esc_html( $n1 ) . ' <em>vs</em> ' . esc_html( $n2 );
				if ( 'done' === $match['status'] ) {
					echo ' <span class="wgt-admin-score">' . (int) $match['score1'] . ' - ' . (int) $match['score2'] . '</span>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		// Outils.
		echo '<h2>' . esc_html__( 'Outils', 'wegame-tournoi' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button" name="tool" value="rebuild">' . esc_html__( 'Régénérer le tableau', 'wegame-tournoi' ) . '</button> ';
		echo '<button class="button" name="tool" value="autoseed">' . esc_html__( 'Tirage au sort des positions', 'wegame-tournoi' ) . '</button> ';
		echo '<button class="button button-link-delete" name="tool" value="reset_results" onclick="return confirm(\'' . esc_js( __( 'Remettre tous les résultats à zéro ? Les équipes sont conservées.', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Remise à zéro des résultats', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		// Sauvegarde.
		echo '<h2>' . esc_html__( 'Sauvegarde', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'La sauvegarde ne concerne que le tournoi sélectionné.', 'wegame-tournoi' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_export' );
		echo '<input type="hidden" name="action" value="wgt_export" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button button-primary">' . esc_html__( 'Télécharger une sauvegarde (JSON)', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-box" style="max-width:720px">';
		wp_nonce_field( 'wgt_import' );
		echo '<input type="hidden" name="action" value="wgt_import" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p><label for="wgt-backup"><strong>' . esc_html__( 'Restaurer une sauvegarde dans ce tournoi', 'wegame-tournoi' ) . '</strong></label><br />';
		echo '<input type="file" id="wgt-backup" name="backup" accept=".json,application/json" required /></p>';
		echo '<p><label><input type="checkbox" name="with_settings" value="1" /> ' . esc_html__( 'Restaurer aussi les réglages du tournoi', 'wegame-tournoi' ) . '</label></p>';
		echo '<p><button class="button" onclick="return confirm(\'' . esc_js( __( 'Remplacer les données de ce tournoi par le contenu du fichier ?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Restaurer', 'wegame-tournoi' ) . '</button></p>';
		echo '</form>';

		// Duplication.
		echo '<h2>' . esc_html__( 'Dupliquer ce tournoi', 'wegame-tournoi' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-box" style="max-width:640px">';
		wp_nonce_field( 'wgt_duplicate_tournament' );
		echo '<input type="hidden" name="action" value="wgt_duplicate_tournament" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p class="description">' . esc_html__( 'Reprend les réglages et le format, sans les résultats. Idéal pour préparer l’édition suivante.', 'wegame-tournoi' ) . '</p>';
		echo '<p><label>' . esc_html__( 'Nom du nouveau tournoi', 'wegame-tournoi' ) . ' *<br /><input type="text" class="regular-text" name="title" required /></label></p>';
		echo '<p><label><input type="checkbox" name="with_teams" value="1" /> ' . esc_html__( 'Reprendre aussi les équipes (en attente de validation, sans position)', 'wegame-tournoi' ) . '</label></p>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Dupliquer', 'wegame-tournoi' ) . '</button></p>';
		echo '</form>';

		// Shortcodes.
		echo '<h2>' . esc_html__( 'Codes courts', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'La page du tournoi affiche déjà tout automatiquement : ces codes courts ne servent que si vous voulez le même contenu ailleurs sur le site.', 'wegame-tournoi' ) . '</p>';
		echo '<p class="description"><strong>' . esc_html__( 'Comment s’en servir :', 'wegame-tournoi' ) . '</strong> ' . esc_html__( 'créez une page WordPress (Pages › Ajouter), collez-y le code court voulu, puis cliquez sur Publier. Le contenu du tournoi s’affichera sur cette page, et se mettra à jour tout seul pendant la soirée.', 'wegame-tournoi' ) . '</p>';
		echo '<table class="widefat striped wgt-shortcodes"><thead><tr><th>' . esc_html__( 'Code court', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Affichage', 'wegame-tournoi' ) . '</th></tr></thead><tbody>';
		$slug         = get_post_field( 'post_name', $tid );
		$descriptions = array(
			'wegame_tournois'     => __( 'Liste de tous les tournois du site', 'wegame-tournoi' ),
			'wegame_tournoi'      => __( 'Page complète à onglets', 'wegame-tournoi' ),
			'wegame_tableau'      => __( 'Tableau à élimination directe', 'wegame-tournoi' ),
			'wegame_planning'     => __( 'Planning horaire', 'wegame-tournoi' ),
			'wegame_resultats'    => __( 'Feuille de résultats', 'wegame-tournoi' ),
			'wegame_classement'   => __( 'Classement général', 'wegame-tournoi' ),
			'wegame_poules'       => __( 'Classements des poules', 'wegame-tournoi' ),
			'wegame_equipes'      => __( 'Équipes engagées', 'wegame-tournoi' ),
			'wegame_reglement'    => __( 'Règlement', 'wegame-tournoi' ),
			'wegame_organisation' => __( 'Personnel nécessaire', 'wegame-tournoi' ),
			'wegame_checklist'    => __( 'Checklist avant ouverture', 'wegame-tournoi' ),
			'wegame_inscription'  => __( 'Formulaire d’inscription (simple="yes" : Team, Pseudo, Mail, Téléphone)', 'wegame-tournoi' ),
		);
		foreach ( $descriptions as $tag => $desc ) {
			$example = 'wegame_tournois' === $tag ? '[' . $tag . ']' : '[' . $tag . ' tournoi="' . $slug . '"]';
			echo '<tr><td><code>' . esc_html( $example ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Sans l’attribut « tournoi », le code court affiche le tournoi par défaut du site. Une page contenant un code court reste invisible aux visiteurs tant qu’elle n’est pas publiée.', 'wegame-tournoi' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( add_query_arg( 'post_type', 'page', admin_url( 'post-new.php' ) ) ) . '">' . esc_html__( 'Créer une page maintenant', 'wegame-tournoi' ) . '</a></p>';

		echo '</div>';
	}

	/**
	 * Gestion des équipes.
	 */
	public static function page_teams() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Équipes', 'wegame-tournoi' ) );
			return;
		}

		$max     = (int) WGT_Tournament::get( $tid, 'team_count' );
		$max     = $max > 0 ? $max : 16;
		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$editing = $edit_id ? WGT_Data::get_team( $edit_id ) : null;

		if ( $editing && (int) $editing['tournament_id'] !== $tid ) {
			$editing = null;
		}

		$teams = WGT_Data::get_teams( array( 'tournament_id' => $tid ) );

		$status_labels = array(
			'pending'  => __( 'En attente', 'wegame-tournoi' ),
			'active'   => __( 'Validée', 'wegame-tournoi' ),
			'rejected' => __( 'Refusée', 'wegame-tournoi' ),
		);

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Équipes', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-teams' );

		// Formulaire.
		echo '<div class="wgt-teams-form" id="wgt-team-form">';
		echo '<h2>' . ( $editing ? esc_html__( 'Modifier l’équipe', 'wegame-tournoi' ) : esc_html__( 'Ajouter une équipe', 'wegame-tournoi' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-box wgt-team-form">';
		wp_nonce_field( 'wgt_save_team' );
		echo '<input type="hidden" name="action" value="wgt_save_team" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<input type="hidden" name="id" value="' . ( $editing ? (int) $editing['id'] : 0 ) . '" />';

		$val = function ( $key ) use ( $editing ) {
			return $editing && isset( $editing[ $key ] ) ? $editing[ $key ] : '';
		};

		echo '<p><label>' . esc_html__( 'Nom de l’équipe', 'wegame-tournoi' ) . ' *<br /><input type="text" class="regular-text" name="name" required value="' . esc_attr( $val( 'name' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Tag', 'wegame-tournoi' ) . '<br /><input type="text" class="regular-text" name="tag" value="' . esc_attr( $val( 'tag' ) ) . '" /></label></p>';

		echo '<p><label>' . esc_html(
			sprintf(
				/* translators: %d: nombre d'équipes */
				__( 'Position dans le tableau (1 à %d)', 'wegame-tournoi' ),
				$max
			)
		) . '<br /><select name="seed">';
		$current_seed = $editing ? (int) $editing['seed'] : 0;
		echo '<option value="0"' . selected( $current_seed, 0, false ) . '>' . esc_html__( '— non placée —', 'wegame-tournoi' ) . '</option>';
		for ( $i = 1; $i <= $max; $i++ ) {
			echo '<option value="' . (int) $i . '"' . selected( $current_seed, $i, false ) . '>' . esc_html( $i ) . '</option>';
		}
		echo '</select></label></p>';

		if ( 'groups' === WGT_Tournament::get( $tid, 'format' ) && 'manual' === WGT_Tournament::get( $tid, 'group_mode' ) ) {
			$group_count = (int) WGT_Tournament::get( $tid, 'group_count' );
			echo '<p><label>' . esc_html__( 'Poule', 'wegame-tournoi' ) . '<br /><select name="group_id">';
			echo '<option value="0"' . selected( (int) $val( 'group_id' ), 0, false ) . '>' . esc_html__( '— non attribuée —', 'wegame-tournoi' ) . '</option>';
			for ( $g = 1; $g <= $group_count; $g++ ) {
				echo '<option value="' . (int) $g . '"' . selected( (int) $val( 'group_id' ), $g, false ) . '>' . esc_html( WGT_Data::group_name( $g ) ) . '</option>';
			}
			echo '</select></label></p>';
		}

		echo '<p><label>' . esc_html__( 'Capitaine', 'wegame-tournoi' ) . '<br /><input type="text" class="regular-text" name="captain" value="' . esc_attr( $val( 'captain' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'E-mail', 'wegame-tournoi' ) . '<br /><input type="email" class="regular-text" name="email" value="' . esc_attr( $val( 'email' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Téléphone', 'wegame-tournoi' ) . '<br /><input type="text" class="regular-text" name="phone" value="' . esc_attr( $val( 'phone' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Joueurs (un par ligne)', 'wegame-tournoi' ) . '<br /><textarea name="players" rows="5" class="large-text">' . esc_textarea( $val( 'players' ) ) . '</textarea></label></p>';
		echo '<p><label>' . esc_html__( 'Notes internes', 'wegame-tournoi' ) . '<br /><textarea name="notes" rows="3" class="large-text">' . esc_textarea( $val( 'notes' ) ) . '</textarea></label></p>';

		echo '<p><label>' . esc_html__( 'Statut', 'wegame-tournoi' ) . '<br /><select name="status">';
		$current_status = $editing ? $editing['status'] : 'active';
		foreach ( $status_labels as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current_status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><br /><span class="description">' . esc_html__( 'Seules les équipes validées apparaissent dans le tableau.', 'wegame-tournoi' ) . '</span></label></p>';

		echo '<p><button class="button button-primary">' . esc_html__( 'Enregistrer', 'wegame-tournoi' ) . '</button> ';
		if ( $editing ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'wgt-teams', 'tournament' => $tid ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Annuler', 'wegame-tournoi' ) . '</a>';
		}
		echo '</p>';
		echo '</form>';
		echo '</div>';


		// Liste.
		echo '<div class="wgt-teams-list">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button" name="tool" value="validate_all">' . esc_html__( 'Valider les inscriptions en attente', 'wegame-tournoi' ) . '</button> ';
		echo '<button class="button" name="tool" value="autoseed">' . esc_html__( 'Tirage au sort des positions libres', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Pos.', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Équipe', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Capitaine', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Contact', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Statut', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wegame-tournoi' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $teams ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'Aucune équipe pour ce tournoi.', 'wegame-tournoi' ) . '</td></tr>';
		}

		foreach ( $teams as $team ) {
			echo '<tr>';
			echo '<td>' . ( (int) $team['seed'] ? esc_html( (int) $team['seed'] ) : '—' ) . '</td>';
			echo '<td><strong>' . esc_html( $team['name'] ) . '</strong>' . ( '' !== $team['tag'] ? ' <span class="wgt-tag">[' . esc_html( $team['tag'] ) . ']</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $team['captain'] ) . '</td>';
			echo '<td>' . esc_html( trim( $team['email'] . ' ' . $team['phone'] ) ) . '</td>';
			echo '<td><span class="wgt-status wgt-status--' . esc_attr( $team['status'] ) . '">' . esc_html( isset( $status_labels[ $team['status'] ] ) ? $status_labels[ $team['status'] ] : $team['status'] ) . '</span></td>';
			echo '<td>';
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'wgt-teams', 'tournament' => $tid, 'edit' => (int) $team['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Modifier', 'wegame-tournoi' ) . '</a> ';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'wgt_delete_team' );
			echo '<input type="hidden" name="action" value="wgt_delete_team" />';
			echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
			echo '<input type="hidden" name="id" value="' . (int) $team['id'] . '" />';
			echo '<button class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Supprimer cette équipe ?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Supprimer', 'wegame-tournoi' ) . '</button>';
			echo '</form>';
			echo '</td></tr>';
		}
		echo '</tbody></table>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Saisie des scores.
	 */
	public static function page_matches() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Matchs & scores', 'wegame-tournoi' ) );
			return;
		}

		$settings = WGT_Tournament::settings( $tid );
		$rounds   = WGT_Data::get_matches_by_round( $tid );
		$teams    = WGT_Data::get_teams_map( $tid );
		$labels   = WGT_Bracket::round_order( $settings );
		$games    = WGT_Data::get_all_games( $tid );

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Matchs & scores', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-matches' );
		echo '<p class="description">' . esc_html__( 'Validez un match pour qualifier automatiquement le vainqueur au tour suivant. Modifier un résultat déjà validé réinitialise les matchs suivants concernés.', 'wegame-tournoi' ) . '</p>';

		foreach ( $labels as $round => $round_label ) {
			if ( empty( $rounds[ $round ] ) ) {
				continue;
			}

			echo '<h2>' . esc_html( $round_label ) . '</h2>';

			foreach ( $rounds[ $round ] as $match ) {
				$id     = (int) $match['id'];
				$t1     = (int) $match['team1_id'];
				$t2     = (int) $match['team2_id'];
				$n1     = $t1 ? WGT_Data::team_name( $t1, $teams ) : WGT_Data::source_label( $match['src1'] );
				$n2     = $t2 ? WGT_Data::team_name( $t2, $teams ) : WGT_Data::source_label( $match['src2'] );
				$bo     = (int) $match['bo'];
				$multi  = $bo > 1;
				$mg     = isset( $games[ $id ] ) ? $games[ $id ] : array();

				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-match-form wgt-match-form--' . esc_attr( $match['status'] ) . '">';
				wp_nonce_field( 'wgt_save_result' );
				echo '<input type="hidden" name="action" value="wgt_save_result" />';
				echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
				echo '<input type="hidden" name="match_id" value="' . $id . '" />';

				echo '<div class="wgt-match-form__head">';
				echo '<span class="wgt-code">' . esc_html( $match['code'] ) . '</span> ';
				echo '<span class="wgt-vs"><strong>' . esc_html( $n1 ) . '</strong> vs <strong>' . esc_html( $n2 ) . '</strong></span> ';
				echo '<span class="wgt-bo">BO' . $bo . '</span>';
				echo '</div>';

				echo '<div class="wgt-match-form__body">';

				if ( $multi ) {
					echo '<div class="wgt-games">';
					for ( $g = 0; $g < $bo; $g++ ) {
						$s1 = isset( $mg[ $g ] ) ? (int) $mg[ $g ]['score1'] : '';
						$s2 = isset( $mg[ $g ] ) ? (int) $mg[ $g ]['score2'] : '';
						$mp = isset( $mg[ $g ] ) ? $mg[ $g ]['map'] : '';
						echo '<span class="wgt-game">';
						/* translators: %d: numéro de manche */
						echo '<label>' . esc_html( sprintf( __( 'Manche %d', 'wegame-tournoi' ), $g + 1 ) ) . '</label>';
						echo '<input type="number" min="0" name="games[' . $g . '][score1]" value="' . esc_attr( $s1 ) . '" /> - ';
						echo '<input type="number" min="0" name="games[' . $g . '][score2]" value="' . esc_attr( $s2 ) . '" />';
						echo '<input type="text" class="wgt-map" name="games[' . $g . '][map]" value="' . esc_attr( $mp ) . '" placeholder="' . esc_attr__( 'Carte / mode', 'wegame-tournoi' ) . '" />';
						echo '</span>';
					}
					echo '</div>';
					echo '<p class="description">' . esc_html(
						sprintf(
							/* translators: 1: format BO, 2: manches à gagner */
							__( 'Laissez vides les manches non jouées. En BO%1$d, le vainqueur doit en remporter %2$d.', 'wegame-tournoi' ),
							$bo,
							(int) floor( $bo / 2 ) + 1
						)
					) . '</p>';
				} else {
					echo '<span class="wgt-scores">';
					echo '<label>' . esc_html__( 'Score', 'wegame-tournoi' ) . '</label>';
					echo '<input type="number" min="0" name="score1" value="' . esc_attr( (int) $match['score1'] ) . '" /> - ';
					echo '<input type="number" min="0" name="score2" value="' . esc_attr( (int) $match['score2'] ) . '" />';
					echo '</span>';
				}

				echo '<span class="wgt-field-inline"><label>' . esc_html__( 'Postes', 'wegame-tournoi' ) . '</label><input type="text" size="5" name="stations" value="' . esc_attr( $match['stations'] ) . '" /></span>';
				echo '<span class="wgt-field-inline"><label>' . esc_html__( 'Début', 'wegame-tournoi' ) . '</label><input type="text" size="5" name="start_time" value="' . esc_attr( $match['start_time'] ) . '" /></span>';
				echo '<span class="wgt-field-inline"><label>' . esc_html__( 'Arbitre', 'wegame-tournoi' ) . '</label><input type="text" name="referee" value="' . esc_attr( $match['referee'] ) . '" /></span>';

				echo '<span class="wgt-field-inline"><label>' . esc_html__( 'Statut', 'wegame-tournoi' ) . '</label><select name="status">';
				foreach ( array( 'pending', 'live', 'done' ) as $status ) {
					echo '<option value="' . esc_attr( $status ) . '"' . selected( $match['status'], $status, false ) . '>' . esc_html( WGT_Render::status_label( $status ) ) . '</option>';
				}
				echo '</select></span>';

				echo '<button class="button button-primary">' . esc_html__( 'Enregistrer', 'wegame-tournoi' ) . '</button>';
				echo '</div>';
				echo '</form>';
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button button-link-delete" name="tool" value="reset_results" onclick="return confirm(\'' . esc_js( __( 'Remettre tous les résultats à zéro ?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Remise à zéro des résultats', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Planning + feuille de scores imprimable.
	 */
	public static function page_planning() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Planning', 'wegame-tournoi' ) );
			return;
		}

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Planning & feuille de scores', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-planning' );
		echo '<p><button class="button" onclick="window.print();return false;">' . esc_html__( 'Imprimer', 'wegame-tournoi' ) . '</button></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button" name="tool" value="reschedule" onclick="return confirm(\'' . esc_js( __( 'Recalculer tous les horaires et postes d’après les réglages ? Les ajustements manuels seront perdus.', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Recalculer le planning', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '<div class="wgt-print">';
		echo WGT_Render::view( 'planning', array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput

		$matches = WGT_Data::get_matches( $tid );
		$teams   = WGT_Data::get_teams_map( $tid );

		echo '<h2>' . esc_html__( 'Feuille de scores', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Match', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Équipe', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Score', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Équipe', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Score', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Arbitre', 'wegame-tournoi' ) . '</th></tr></thead><tbody>';
		foreach ( $matches as $match ) {
			$n1 = (int) $match['team1_id'] ? WGT_Data::team_name( (int) $match['team1_id'], $teams ) : WGT_Data::source_label( $match['src1'] );
			$n2 = (int) $match['team2_id'] ? WGT_Data::team_name( (int) $match['team2_id'], $teams ) : WGT_Data::source_label( $match['src2'] );
			echo '<tr>';
			echo '<td><strong>' . esc_html( $match['code'] ) . '</strong></td>';
			echo '<td>' . esc_html( $n1 ) . '</td>';
			echo '<td>' . ( 'done' === $match['status'] ? (int) $match['score1'] : '' ) . '</td>';
			echo '<td>' . esc_html( $n2 ) . '</td>';
			echo '<td>' . ( 'done' === $match['status'] ? (int) $match['score2'] : '' ) . '</td>';
			echo '<td>' . esc_html( $match['referee'] ) . '</td>';
			echo '</tr>';
		}
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Checklist avant ouverture', 'wegame-tournoi' ) . '</h2>';
		echo WGT_Render::view( 'checklist', array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '<h2>' . esc_html__( 'Personnel', 'wegame-tournoi' ) . '</h2>';
		echo WGT_Render::view( 'staff', array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Aperçu du rendu public.
	 */
	public static function page_preview() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Aperçu public', 'wegame-tournoi' ) );
			return;
		}

		$views = array(
			'full'         => __( 'Page complète (onglets)', 'wegame-tournoi' ),
			'bracket'      => __( 'Tableau', 'wegame-tournoi' ),
			'planning'     => __( 'Planning', 'wegame-tournoi' ),
			'results'      => __( 'Résultats', 'wegame-tournoi' ),
			'ranking'      => __( 'Classement', 'wegame-tournoi' ),
			'teams'        => __( 'Équipes', 'wegame-tournoi' ),
			'rules'        => __( 'Règlement', 'wegame-tournoi' ),
			'registration' => __( 'Inscription', 'wegame-tournoi' ),
			'list'         => __( 'Liste des tournois', 'wegame-tournoi' ),
		);

		$widths = array(
			'desktop' => array( __( 'Bureau', 'wegame-tournoi' ), '100%' ),
			'tablet'  => array( __( 'Tablette', 'wegame-tournoi' ), '768px' ),
			'mobile'  => array( __( 'Mobile', 'wegame-tournoi' ), '390px' ),
		);

		$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'full'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$width = isset( $_GET['w'] ) ? sanitize_key( wp_unslash( $_GET['w'] ) ) : 'desktop'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( ! isset( $views[ $view ] ) ) {
			$view = 'full';
		}
		if ( ! isset( $widths[ $width ] ) ) {
			$width = 'desktop';
		}

		$base = add_query_arg( array( 'page' => 'wgt-preview', 'tournament' => $tid ), admin_url( 'admin.php' ) );

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Aperçu public', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-preview' );
		echo '<p class="description">' . esc_html__( 'Rendu exact de ce que verront les visiteurs. L’habillage du thème (menu, pied de page) n’est pas reproduit ici.', 'wegame-tournoi' ) . '</p>';

		echo '<div class="wgt-preview-bar">';

		echo '<span class="wgt-preview-group"><strong>' . esc_html__( 'Vue', 'wegame-tournoi' ) . '</strong>';
		foreach ( $views as $key => $label ) {
			$url = add_query_arg( array( 'view' => $key, 'w' => $width ), $base );
			echo '<a class="button button-small' . ( $key === $view ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
		}
		echo '</span>';

		echo '<span class="wgt-preview-group"><strong>' . esc_html__( 'Largeur', 'wegame-tournoi' ) . '</strong>';
		foreach ( $widths as $key => $def ) {
			$url = add_query_arg( array( 'view' => $view, 'w' => $key ), $base );
			echo '<a class="button button-small' . ( $key === $width ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $def[0] ) . '</a> ';
		}
		echo '</span>';

		echo '</div>';

		$frame_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'wgt_preview_frame',
					'view'       => $view,
					'tournament' => $tid,
				),
				admin_url( 'admin-post.php' )
			),
			'wgt_preview_frame'
		);

		echo '<div class="wgt-preview-stage">';
		echo '<iframe id="wgt-preview-frame" class="wgt-preview-frame wgt-preview-frame--' . esc_attr( $width ) . '" ';
		echo 'src="' . esc_url( $frame_url ) . '" ';
		echo 'style="width:100%;max-width:' . esc_attr( $widths[ $width ][1] ) . '" ';
		echo 'title="' . esc_attr__( 'Aperçu du rendu public', 'wegame-tournoi' ) . '" loading="lazy"></iframe>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Réglages propres au tournoi.
	 */
	public static function page_tournament() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Réglages du tournoi', 'wegame-tournoi' ) );
			return;
		}

		$s = WGT_Tournament::settings( $tid );

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Réglages du tournoi', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-tournament' );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_save_tournament' );
		echo '<input type="hidden" name="action" value="wgt_save_tournament" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<input type="hidden" name="wgt_checkbox_scope" value="third_place,bracket_reset,show_rules,show_staff,registration_open,auto_content,public_page" />';

		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Nom', 'wegame-tournoi' ) . '</th><td>';
		echo '<strong>' . esc_html( $s['tournament_name'] ) . '</strong> ';
		echo '<a class="button button-small" href="' . esc_url( get_edit_post_link( $tid ) ) . '">' . esc_html__( 'Renommer', 'wegame-tournoi' ) . '</a>';
		echo '<p class="description">' . esc_html__( 'Le nom et l’adresse de la page se modifient depuis la page du tournoi.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		$text_fields = array(
			'game_name' => __( 'Jeu', 'wegame-tournoi' ),
			'subtitle'  => __( 'Sous-titre', 'wegame-tournoi' ),
			'venue'     => __( 'Lieu', 'wegame-tournoi' ),
		);
		foreach ( $text_fields as $key => $label ) {
			echo '<tr><th scope="row"><label for="wgt-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input type="text" class="regular-text" id="wgt-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '" /></td></tr>';
		}

		echo '<tr><th scope="row"><label for="wgt-event_date">' . esc_html__( 'Date', 'wegame-tournoi' ) . '</label></th>';
		echo '<td><input type="date" id="wgt-event_date" name="event_date" value="' . esc_attr( $s['event_date'] ) . '" /></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Horaires', 'wegame-tournoi' ) . '</th><td>';
		echo '<label>' . esc_html__( 'Début', 'wegame-tournoi' ) . ' <input type="time" name="start_time" value="' . esc_attr( $s['start_time'] ) . '" /></label> ';
		echo '<label>' . esc_html__( 'Fin prévisionnelle', 'wegame-tournoi' ) . ' <input type="time" name="end_time" value="' . esc_attr( $s['end_time'] ) . '" /></label>';
		echo '<p class="description">' . esc_html__( 'Modifier l’heure de début décale automatiquement tout le planning.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Postes de jeu', 'wegame-tournoi' ) . '</th>';
		echo '<td><input type="number" min="1" name="stations" value="' . esc_attr( (int) $s['stations'] ) . '" class="small-text" /></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Joueurs par équipe', 'wegame-tournoi' ) . '</th>';
		echo '<td><input type="number" min="1" name="players_per_team" value="' . esc_attr( (int) $s['players_per_team'] ) . '" class="small-text" /></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Couleur d’accent', 'wegame-tournoi' ) . '</th>';
		echo '<td><input type="color" name="accent_color" value="' . esc_attr( $s['accent_color'] ) . '" /></td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Format', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		$formats = array(
			'single' => __( 'Élimination directe', 'wegame-tournoi' ),
			'double' => __( 'Double élimination (avec repêchage)', 'wegame-tournoi' ),
			'groups' => __( 'Poules puis phase finale', 'wegame-tournoi' ),
		);

		echo '<tr><th scope="row">' . esc_html__( 'Déroulement', 'wegame-tournoi' ) . '</th><td>';
		echo '<select name="format">';
		foreach ( $formats as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $s['format'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'En double élimination, une équipe n’est éliminée qu’après deux défaites. En poules, chaque équipe rencontre toutes celles de sa poule avant la phase finale.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Nombre d’équipes', 'wegame-tournoi' ) . '</th><td>';
		echo '<input type="number" min="2" max="64" name="team_count" value="' . esc_attr( (int) $s['team_count'] ) . '" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'De 2 à 64. Si le nombre n’est pas une puissance de deux, les mieux classées sont exemptées du premier tour.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr class="wgt-if-groups"><th scope="row">' . esc_html__( 'Poules', 'wegame-tournoi' ) . '</th><td>';
		echo '<label>' . esc_html__( 'Nombre de poules', 'wegame-tournoi' ) . ' <input type="number" min="2" max="8" name="group_count" value="' . esc_attr( (int) $s['group_count'] ) . '" class="small-text" /></label> ';
		echo '<label>' . esc_html__( 'Qualifiés par poule', 'wegame-tournoi' ) . ' <input type="number" min="1" max="4" name="qualifiers_per_group" value="' . esc_attr( (int) $s['qualifiers_per_group'] ) . '" class="small-text" /></label><br /><br />';
		echo '<label>' . esc_html__( 'Composition', 'wegame-tournoi' ) . ' <select name="group_mode">';
		echo '<option value="auto"' . selected( $s['group_mode'], 'auto', false ) . '>' . esc_html__( 'Automatique (serpentin selon les positions)', 'wegame-tournoi' ) . '</option>';
		echo '<option value="manual"' . selected( $s['group_mode'], 'manual', false ) . '>' . esc_html__( 'Manuelle (poule choisie pour chaque équipe)', 'wegame-tournoi' ) . '</option>';
		echo '</select></label>';
		echo '<p class="description">' . esc_html__( 'En composition manuelle, la poule se choisit sur la fiche de chaque équipe. Les qualifiés sont croisés entre poules dans les deux cas.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		$bo_rounds = array(
			'bo_group' => __( 'Poules', 'wegame-tournoi' ),
			'bo_r64'   => __( '32es', 'wegame-tournoi' ),
			'bo_r32'   => __( 'Seizièmes', 'wegame-tournoi' ),
			'bo_r16'   => __( 'Huitièmes', 'wegame-tournoi' ),
			'bo_qf'    => __( 'Quarts', 'wegame-tournoi' ),
			'bo_sf'    => __( 'Demies', 'wegame-tournoi' ),
			'bo_final' => __( 'Finale', 'wegame-tournoi' ),
			'bo_lb'    => __( 'Repêchage', 'wegame-tournoi' ),
		);

		echo '<tr><th scope="row">' . esc_html__( 'Nombre de manches', 'wegame-tournoi' ) . '</th><td>';
		foreach ( $bo_rounds as $key => $label ) {
			echo '<label style="display:inline-block;margin:0 18px 8px 0">' . esc_html( $label ) . ' <select name="' . esc_attr( $key ) . '">';
			foreach ( array( 1, 3, 5 ) as $bo ) {
				echo '<option value="' . (int) $bo . '"' . selected( (int) $s[ $key ], $bo, false ) . '>BO' . (int) $bo . '</option>';
			}
			echo '</select></label>';
		}
		echo '<p class="description">' . esc_html__( 'Seuls les tours réellement présents dans le format choisi sont utilisés.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Match pour la 3e place', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="third_place" value="1"' . checked( $s['third_place'], 1, false ) . ' /> ' . esc_html__( 'Opposer les deux perdants des demi-finales', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Sans objet en double élimination : le perdant de la finale du repêchage est 3e.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Seconde grande finale', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="bracket_reset" value="1"' . checked( $s['bracket_reset'], 1, false ) . ' /> ' . esc_html__( 'Rejouer la finale si l’équipe issue du repêchage l’emporte (« bracket reset »)', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Règle stricte de la double élimination : l’équipe venue du repêchage a déjà une défaite, elle doit donc en infliger deux. Le match n’est joué que si nécessaire.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Planning', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Calcul des horaires', 'wegame-tournoi' ) . '</th><td>';
		echo '<label>' . esc_html__( 'Durée d’un match (min)', 'wegame-tournoi' ) . ' <input type="number" min="5" name="match_duration" value="' . esc_attr( (int) $s['match_duration'] ) . '" class="small-text" /></label> ';
		echo '<label>' . esc_html__( 'Matchs simultanés', 'wegame-tournoi' ) . ' <input type="number" min="1" name="matches_parallel" value="' . esc_attr( (int) $s['matches_parallel'] ) . '" class="small-text" /></label><br /><br />';
		echo '<label>' . esc_html__( 'Accueil avant le 1er match (min)', 'wegame-tournoi' ) . ' <input type="number" min="0" name="warmup_minutes" value="' . esc_attr( (int) $s['warmup_minutes'] ) . '" class="small-text" /></label> ';
		echo '<label>' . esc_html__( 'Pause entre les tours (min)', 'wegame-tournoi' ) . ' <input type="number" min="0" name="break_minutes" value="' . esc_attr( (int) $s['break_minutes'] ) . '" class="small-text" /></label>';
		echo '<p class="description">' . esc_html__( 'Un BO3 compte pour deux créneaux, un BO5 pour trois. Les horaires déjà en place ne sont pas écrasés : utilisez « Recalculer le planning » ci-dessous pour les régénérer.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Affichage', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Onglets', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="show_rules" value="1"' . checked( $s['show_rules'], 1, false ) . ' /> ' . esc_html__( 'Règlement', 'wegame-tournoi' ) . '</label><br />';
		echo '<label><input type="checkbox" name="show_staff" value="1"' . checked( $s['show_staff'], 1, false ) . ' /> ' . esc_html__( 'Organisation (personnel)', 'wegame-tournoi' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Page du tournoi', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="public_page" value="1"' . checked( $s['public_page'], 1, false ) . ' /> ' . esc_html__( 'Page publique dédiée', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Décochée, la page du tournoi répond « introuvable » aux visiteurs ; le tournoi ne s’affiche que par les codes courts placés sur vos propres pages.', 'wegame-tournoi' ) . '</p>';
		echo '<label><input type="checkbox" name="auto_content" value="1"' . checked( $s['auto_content'], 1, false ) . ' /> ' . esc_html__( 'Afficher automatiquement le tournoi sur sa page', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Décochez si vous préférez composer la page vous-même avec les codes courts.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Inscriptions', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="form-table"><tbody><tr><th scope="row">' . esc_html__( 'Formulaire public', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="registration_open" value="1"' . checked( $s['registration_open'], 1, false ) . ' /> ' . esc_html__( 'Ouvrir les inscriptions pour ce tournoi', 'wegame-tournoi' ) . '</label><br /><br />';
		echo '<label>' . esc_html__( 'Nombre maximum d’équipes', 'wegame-tournoi' ) . ' <input type="number" min="0" name="registration_max" value="' . esc_attr( (int) $s['registration_max'] ) . '" class="small-text" /></label><br /><br />';
		echo '<label>' . esc_html__( 'E-mail de notification', 'wegame-tournoi' ) . ' <input type="email" class="regular-text" name="notify_email" value="' . esc_attr( $s['notify_email'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" /></label>';
		echo '<p class="description">' . esc_html__( 'Prévenu à chaque inscription. Vide : adresse d’administration du site. Le capitaine reçoit de son côté un accusé de réception.', 'wegame-tournoi' ) . '</p><br />';
		echo '<label>' . esc_html__( 'Message de confirmation', 'wegame-tournoi' ) . '<br /><textarea name="registration_msg" rows="3" class="large-text">' . esc_textarea( $s['registration_msg'] ) . '</textarea></label>';
		echo '</td></tr></tbody></table>';

		submit_button();
		echo '</form>';

		self::danger_zone( $tid );

		echo '</div>';
	}

	/**
	 * Réglages généraux du site.
	 */
	public static function page_settings() {
		$s   = WGT_Settings::all();
		$all = WGT_Tournament::all( array( 'post_status' => 'publish' ) );

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Réglages de l’extension', 'wegame-tournoi' ) . '</h1>';
		self::notice();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_save_settings' );
		echo '<input type="hidden" name="action" value="wgt_save_settings" />';
		echo '<input type="hidden" name="wgt_checkbox_scope" value="hide_page_title,delete_data_on_uninstall" />';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Tournoi par défaut', 'wegame-tournoi' ) . '</th><td>';
		if ( empty( $all ) ) {
			echo '<em>' . esc_html__( 'Aucun tournoi publié.', 'wegame-tournoi' ) . '</em>';
		} else {
			$default = (int) get_option( 'wgt_default_tournament' );
			echo '<select name="default_tournament">';
			echo '<option value="0">' . esc_html__( '— le plus récent —', 'wegame-tournoi' ) . '</option>';
			foreach ( $all as $post ) {
				echo '<option value="' . (int) $post->ID . '"' . selected( $default, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Utilisé par les codes courts sans attribut « tournoi ».', 'wegame-tournoi' ) . '</p>';
		}
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Rafraîchissement auto', 'wegame-tournoi' ) . '</th>';
		echo '<td><input type="number" min="0" name="refresh_interval" value="' . esc_attr( (int) $s['refresh_interval'] ) . '" class="small-text" /> ' . esc_html__( 'secondes (0 pour désactiver)', 'wegame-tournoi' ) . '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Titre de la page', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="hide_page_title" value="1"' . checked( $s['hide_page_title'], 1, false ) . ' /> ' . esc_html__( 'Masquer le bandeau de titre du thème sur les pages de tournoi', 'wegame-tournoi' ) . '</label>';
		echo '<p><label>' . esc_html__( 'Sélecteurs CSS supplémentaires', 'wegame-tournoi' ) . '<br />';
		echo '<input type="text" class="large-text code" name="hide_title_selector" value="' . esc_attr( $s['hide_title_selector'] ) . '" placeholder=".mon-theme-titre, #header-page" /></label></p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="wgt-update_manifest_url">' . esc_html__( 'URL du manifeste de mise à jour', 'wegame-tournoi' ) . '</label></th><td>';
		echo '<input type="url" class="large-text code" id="wgt-update_manifest_url" name="update_manifest_url" value="' . esc_attr( $s['update_manifest_url'] ) . '" placeholder="https://exemple.fr/maj/wegame-tournoi.json" />';
		echo '<p class="description">' . esc_html__( 'Laissez vide pour désactiver les mises à jour automatiques.', 'wegame-tournoi' ) . '</p>';

		$manifest = WGT_Updater::manifest();
		if ( $manifest ) {
			if ( version_compare( $manifest['version'], WGT_VERSION, '>' ) ) {
				echo '<p><strong>' . esc_html(
					sprintf(
						/* translators: 1: version disponible, 2: version installée */
						__( 'Version %1$s disponible (vous utilisez la %2$s).', 'wegame-tournoi' ),
						$manifest['version'],
						WGT_VERSION
					)
				) . '</strong></p>';
			} else {
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %s: version installée */
						__( 'À jour (version %s).', 'wegame-tournoi' ),
						WGT_VERSION
					)
				) . '</p>';
			}
		}
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Données à la désinstallation', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="delete_data_on_uninstall" value="1"' . checked( $s['delete_data_on_uninstall'], 1, false ) . ' /> ' . esc_html__( 'Tout supprimer si l’extension est désinstallée', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Décoché (recommandé), les tournois et leurs données sont conservés.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button();
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_check_update' );
		echo '<input type="hidden" name="action" value="wgt_check_update" />';
		echo '<button class="button">' . esc_html__( 'Vérifier les mises à jour maintenant', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '<h2>' . esc_html__( 'E-mails', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Chaque inscription publique envoie un accusé de réception au capitaine et une alerte à l’adresse de notification du tournoi (à défaut, l’adresse d’administration du site). L’envoi dépend de l’hébergement : testez-le ici.', 'wegame-tournoi' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_test_mail' );
		echo '<input type="hidden" name="action" value="wgt_test_mail" />';
		echo '<label>' . esc_html__( 'Envoyer un e-mail de test à', 'wegame-tournoi' ) . ' <input type="email" name="to" class="regular-text" value="' . esc_attr( get_option( 'admin_email' ) ) . '" /></label> ';
		echo '<button class="button">' . esc_html__( 'Envoyer', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '</div>';
	}
}
