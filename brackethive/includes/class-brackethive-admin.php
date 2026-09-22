<?php
/**
 * Interface d'administration.
 *
 * Les écrans de gestion travaillent toujours sur un tournoi, sélectionné par
 * la barre en haut de page et mémorisé pour l'utilisateur.
 *
 * @package Brackethive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Brackethive_Admin {

	/**
	 * Capacité requise.
	 *
	 * @return string
	 */
	public static function cap() {
		return apply_filters( 'brackethive_admin_capability', 'manage_options' );
	}

	/**
	 * Le module de mise à jour auto-hébergée est-il présent ?
	 *
	 * Il est absent du paquet distribué sur WordPress.org, où les mises à
	 * jour sont assurées par le répertoire officiel. Tout ce qui s'y
	 * rapporte dans l'administration est donc conditionné à sa présence.
	 *
	 * @return bool
	 */
	protected static function has_updater() {
		return class_exists( 'Brackethive_Updater' );
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );

		add_action( 'admin_post_brackethive_save_team', array( __CLASS__, 'handle_save_team' ) );
		add_action( 'admin_post_brackethive_delete_team', array( __CLASS__, 'handle_delete_team' ) );
		add_action( 'admin_post_brackethive_save_result', array( __CLASS__, 'handle_save_result' ) );
		add_action( 'admin_post_brackethive_tools', array( __CLASS__, 'handle_tools' ) );
		add_action( 'admin_post_brackethive_save_settings', array( __CLASS__, 'handle_save_settings' ) );
		add_action( 'admin_post_brackethive_save_tournament', array( __CLASS__, 'handle_save_tournament' ) );
		add_action( 'admin_post_brackethive_create_tournament', array( __CLASS__, 'handle_create_tournament' ) );
		add_action( 'admin_post_brackethive_duplicate_tournament', array( __CLASS__, 'handle_duplicate_tournament' ) );
		add_action( 'admin_post_brackethive_export', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_brackethive_import', array( __CLASS__, 'handle_import' ) );
		if ( self::has_updater() ) {
			add_action( 'admin_post_brackethive_check_update', array( __CLASS__, 'handle_check_update' ) );
		}
		add_action( 'admin_post_brackethive_test_mail', array( __CLASS__, 'handle_test_mail' ) );
		add_action( 'admin_post_brackethive_create_registration_page', array( __CLASS__, 'handle_create_registration_page' ) );
		add_action( 'admin_notices', array( __CLASS__, 'welcome_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss_welcome' ) );
		add_action( 'admin_post_brackethive_preview_frame', array( __CLASS__, 'handle_preview_frame' ) );
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
			__( 'Tournaments', 'brackethive' ),
			__( 'Tournaments', 'brackethive' ),
			$cap,
			'brackethive-dashboard',
			array( __CLASS__, 'page_dashboard' ),
			'dashicons-games',
			26
		);

		add_submenu_page( 'brackethive-dashboard', __( 'Dashboard', 'brackethive' ), __( 'Dashboard', 'brackethive' ), $cap, 'brackethive-dashboard', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'brackethive-dashboard', __( 'New tournament', 'brackethive' ), __( 'New tournament', 'brackethive' ), $cap, 'brackethive-new', array( __CLASS__, 'page_new' ) );
		add_submenu_page( 'brackethive-dashboard', __( 'All tournaments', 'brackethive' ), __( 'All tournaments', 'brackethive' ), $cap, 'edit.php?post_type=' . Brackethive_Tournament::POST_TYPE );
		add_submenu_page( 'brackethive-dashboard', __( 'Teams', 'brackethive' ), __( 'Teams', 'brackethive' ), $cap, 'brackethive-teams', array( __CLASS__, 'page_teams' ) );
		add_submenu_page( 'brackethive-dashboard', __( 'Matches & scores', 'brackethive' ), __( 'Matches & scores', 'brackethive' ), $cap, 'brackethive-matches', array( __CLASS__, 'page_matches' ) );
		add_submenu_page( 'brackethive-dashboard', __( 'Schedule', 'brackethive' ), __( 'Schedule', 'brackethive' ), $cap, 'brackethive-planning', array( __CLASS__, 'page_planning' ) );
		add_submenu_page( 'brackethive-dashboard', __( 'Tournament settings', 'brackethive' ), __( 'Tournament settings', 'brackethive' ), $cap, 'brackethive-tournament', array( __CLASS__, 'page_tournament' ) );
		add_submenu_page( 'brackethive-dashboard', __( 'Public preview', 'brackethive' ), __( 'Public preview', 'brackethive' ), $cap, 'brackethive-preview', array( __CLASS__, 'page_preview' ) );
		add_submenu_page( 'brackethive-dashboard', __( 'Plugin settings', 'brackethive' ), __( 'Plugin', 'brackethive' ), $cap, 'brackethive-settings', array( __CLASS__, 'page_settings' ) );
	}

	/**
	 * Styles et scripts d'administration.
	 *
	 * @param string $hook Page courante.
	 */
	public static function assets( $hook ) {
		if ( false === strpos( $hook, 'brackethive-' ) ) {
			return;
		}

		wp_enqueue_style( 'brackethive-admin', BRACKETHIVE_URL . 'assets/css/brackethive-admin.css', array(), BRACKETHIVE_VERSION );
		wp_enqueue_script( 'brackethive-admin', BRACKETHIVE_URL . 'assets/js/brackethive-admin.js', array(), BRACKETHIVE_VERSION, true );
		wp_localize_script(
			'brackethive-admin',
			'BRACKETHIVE_ADMIN',
			array(
				'i18n' => array(
					'copied' => __( 'Copied', 'brackethive' ),
					'copy'   => __( 'Copy', 'brackethive' ),
				),
			)
		);

		if ( false !== strpos( $hook, 'brackethive-preview' ) ) {
			wp_enqueue_style( 'brackethive-public', BRACKETHIVE_URL . 'assets/css/brackethive-public.css', array(), BRACKETHIVE_VERSION );
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
			wp_die( esc_html__( 'Access denied.', 'brackethive' ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Tournoi visé par une requête POST.
	 *
	 * @return int
	 */
	protected static function posted_tournament() {
		$tid = isset( $_POST['tournament_id'] ) ? (int) $_POST['tournament_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() dans le gestionnaire appelant.
		return Brackethive_Tournament::exists( $tid ) ? $tid : 0;
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
			'page'             => $page,
			'brackethive_type' => $type,
			'brackethive_msg'  => rawurlencode( $message ),
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
		if ( empty( $_GET['brackethive_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.
			return;
		}
		$type = isset( $_GET['brackethive_type'] ) && 'error' === $_GET['brackethive_type'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.
		// Message renvoyé par nos propres redirections : assaini avant décodage, puis échappé à l'affichage.
		$raw = sanitize_text_field( wp_unslash( $_GET['brackethive_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple message d'état, aucune action déclenchée.
		$msg = sanitize_text_field( rawurldecode( $raw ) );
		echo '<div class="notice notice-' . esc_attr( $type ) . ' is-dismissible"><p>' . esc_html( $msg ) . '</p></div>';
	}

	/**
	 * Barre de sélection du tournoi, en haut des écrans de gestion.
	 *
	 * @param int    $tid  Tournoi courant.
	 * @param string $page Slug de la page courante.
	 */
	protected static function switcher( $tid, $page ) {
		$all = Brackethive_Tournament::all();

		echo '<div class="brackethive-switcher">';
		echo '<span class="brackethive-switcher__label">' . esc_html__( 'Tournament', 'brackethive' ) . '</span>';

		if ( empty( $all ) ) {
			echo '<em>' . esc_html__( 'no tournament', 'brackethive' ) . '</em>';
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
			echo ' <a class="button button-small" href="' . esc_url( get_edit_post_link( $tid ) ) . '">' . esc_html__( 'Edit page', 'brackethive' ) . '</a>';
			echo ' <a class="button button-small" href="' . esc_url( get_permalink( $tid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'brackethive' ) . '</a>';
			// Renvoie vers la zone sensible des réglages du tournoi, où la
			// suppression demande une confirmation par saisie.
			$danger = add_query_arg( array( 'page' => 'brackethive-tournament', 'tournament' => (int) $tid ), admin_url( 'admin.php' ) ) . '#brackethive-danger';
			echo ' <a class="button button-small brackethive-switcher__delete" href="' . esc_url( $danger ) . '">' . esc_html__( 'Delete', 'brackethive' ) . '</a>';
		}

		echo ' <a class="button button-small" href="' . esc_url( add_query_arg( 'page', 'brackethive-new', admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Create a tournament', 'brackethive' ) . '</a>';
		echo '</div>';
	}

	/**
	 * Écran affiché quand aucun tournoi n'existe.
	 *
	 * @param string $title Titre de la page.
	 */
	protected static function no_tournament( $title ) {
		echo '<div class="wrap brackethive-admin">';
		echo '<h1>' . esc_html( $title ) . '</h1>';
		self::notice();
		echo '<div class="notice notice-info"><p>' . esc_html__( 'No tournament exists yet. The wizard below guides you through four steps.', 'brackethive' ) . '</p></div>';
		self::wizard();
		echo '</div>';
	}

	/**
	 * Formulaire de création d'un tournoi.
	 */
	protected static function create_form() {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-box" style="max-width:640px">';
		wp_nonce_field( 'brackethive_create_tournament' );
		echo '<input type="hidden" name="action" value="brackethive_create_tournament" />';
		echo '<h2>' . esc_html__( 'New tournament', 'brackethive' ) . '</h2>';
		echo '<p><label>' . esc_html__( 'Name', 'brackethive' ) . ' *<br /><input type="text" class="regular-text" name="title" required placeholder="' . esc_attr__( 'Spring Esports Cup 2027', 'brackethive' ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Game', 'brackethive' ) . '<br /><input type="text" class="regular-text" name="game_name" /></label></p>';
		echo '<p><label>' . esc_html__( 'Date', 'brackethive' ) . '<br /><input type="date" name="event_date" /></label></p>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Create', 'brackethive' ) . '</button></p>';
		echo '</form>';
	}

	/* ---------------------------------------------------------------------
	 * Traitements — tournois
	 * ------------------------------------------------------------------ */

	/**
	 * Création d'un tournoi.
	 */
	public static function handle_create_tournament() {
		self::guard( 'brackethive_create_tournament' );

		$title = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		$settings = array();
		if ( isset( $_POST['game_name'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			$settings['game_name'] = sanitize_text_field( wp_unslash( $_POST['game_name'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
		}
		if ( isset( $_POST['event_date'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			$settings['event_date'] = sanitize_text_field( wp_unslash( $_POST['event_date'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
		}

		$choice = isset( $_POST['post_status'] ) ? sanitize_key( $_POST['post_status'] ) : 'publish'; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
		if ( ! in_array( $choice, array( 'publish', 'draft', 'none' ), true ) ) {
			$choice = 'publish';
		}
		// « Pas de page dédiée » : le tournoi est publié (ses codes courts
		// fonctionnent) mais sa page répond 404 aux visiteurs.
		$status = 'draft' === $choice ? 'draft' : 'publish';

		$id = Brackethive_Tournament::create( $title, $settings, $status );

		if ( is_wp_error( $id ) ) {
			self::back( 'brackethive-new', 'error', $id->get_error_message() );
		}

		// L'assistant transmet tous les réglages : même sanitisation que
		// l'écran Réglages du tournoi, puis structure du tableau.
		if ( ! empty( $_POST['brackethive_wizard'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			$input                               = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			$input['public_page']                = 'none' === $choice ? 0 : 1;
			$input['brackethive_checkbox_scope'] = ( isset( $input['brackethive_checkbox_scope'] ) ? $input['brackethive_checkbox_scope'] . ',' : '' ) . 'public_page';
			Brackethive_Tournament::save_settings( $id, $input );
			Brackethive_Data::ensure_bracket( $id );
			Brackethive_Data::recalculate( $id );

			if ( ! empty( $_POST['create_registration_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
				Brackethive_Tournament::create_registration_page( $id, 'none' === $choice ? 'publish' : $status );
			}
			if ( 'draft' === $choice ) {
				$msg = __( 'Tournament created as a draft. Next step: add the teams, then publish the page.', 'brackethive' );
			} elseif ( 'none' === $choice ) {
				$msg = __( 'Tournament created without a dedicated page. Place the shortcodes on your own pages, then add the teams.', 'brackethive' );
			} else {
				$msg = __( 'Tournament created and page published. Next step: add the teams.', 'brackethive' );
			}
			self::back( 'brackethive-dashboard', 'updated', $msg, $id );
		}

		self::back( 'brackethive-dashboard', 'updated', __( 'Tournament created.', 'brackethive' ), $id );
	}

	/**
	 * Écran « Nouveau tournoi » : assistant de création en quatre étapes.
	 */
	public static function page_new() {
		echo '<div class="wrap brackethive-admin">';
		echo '<h1>' . esc_html__( 'New tournament', 'brackethive' ) . '</h1>';
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
		$d = Brackethive_Tournament::defaults();

		$formats = array(
			'single' => array(
				__( 'Single elimination', 'brackethive' ),
				__( 'The simplest and fastest format: one loss and the team is out. Ideal for a single evening.', 'brackethive' ),
				__( '16 teams: 15 matches', 'brackethive' ),
			),
			'double' => array(
				__( 'Double elimination', 'brackethive' ),
				__( 'A team is only out after two losses, thanks to a losers bracket. Longer, but fairer.', 'brackethive' ),
				__( '16 teams: 30 matches', 'brackethive' ),
			),
			'groups' => array(
				__( 'Group stage then playoffs', 'brackethive' ),
				__( 'Each team plays several matches in its group, then the best ones face off in a bracket. The longest format.', 'brackethive' ),
				__( '16 teams in 4 groups: 24 + 7 matches', 'brackethive' ),
			),
		);

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-wizard" data-brackethive-wizard>';
		wp_nonce_field( 'brackethive_create_tournament' );
		echo '<input type="hidden" name="action" value="brackethive_create_tournament" />';
		echo '<input type="hidden" name="brackethive_wizard" value="1" />';
		echo '<input type="hidden" name="brackethive_checkbox_scope" value="third_place,bracket_reset,registration_open,show_rules,auto_content" />';
		echo '<input type="hidden" name="auto_content" value="1" />';
		echo '<input type="hidden" name="show_rules" value="1" />';

		// Fil d'Ariane.
		$steps = array(
			__( 'The tournament', 'brackethive' ),
			__( 'The format', 'brackethive' ),
			__( 'Matches and times', 'brackethive' ),
			__( 'Sign-ups and creation', 'brackethive' ),
		);
		echo '<ol class="brackethive-wizard__steps">';
		foreach ( $steps as $i => $label ) {
			echo '<li class="brackethive-wizard__step' . ( 0 === $i ? ' is-current' : '' ) . '" data-brackethive-step-link="' . (int) ( $i + 1 ) . '"><span class="brackethive-wizard__num">' . (int) ( $i + 1 ) . '</span> ' . esc_html( $label ) . '</li>';
		}
		echo '</ol>';

		/* ---------------- Étape 1 : identité ---------------- */
		echo '<section class="brackethive-wizard__panel" data-brackethive-step="1">';
		echo '<h2>' . esc_html__( '1. The tournament', 'brackethive' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'This information appears at the top of the public page. Only the name is required; everything can be changed later in Tournament settings.', 'brackethive' ) . '</p>';
		echo '<div class="brackethive-wizard__grid">';
		echo '<p class="brackethive-wizard__full"><label for="brackethive-w-title">' . esc_html__( 'Tournament name', 'brackethive' ) . ' *</label><input type="text" id="brackethive-w-title" class="regular-text" name="title" required placeholder="' . esc_attr__( 'Spring Esports Cup 2027', 'brackethive' ) . '" /><span class="description">' . esc_html__( 'It also becomes the title and the address of the tournament page.', 'brackethive' ) . '</span></p>';
		echo '<p><label for="brackethive-w-game">' . esc_html__( 'Game', 'brackethive' ) . '</label><input type="text" id="brackethive-w-game" class="regular-text" name="game_name" placeholder="' . esc_attr__( 'Arena League', 'brackethive' ) . '" /></p>';
		echo '<p><label for="brackethive-w-venue">' . esc_html__( 'Venue', 'brackethive' ) . '</label><input type="text" id="brackethive-w-venue" class="regular-text" name="venue" placeholder="' . esc_attr__( 'Community hall', 'brackethive' ) . '" /></p>';
		echo '<p><label for="brackethive-w-date">' . esc_html__( 'Date', 'brackethive' ) . '</label><input type="date" id="brackethive-w-date" name="event_date" /></p>';
		echo '<p><label for="brackethive-w-start">' . esc_html__( 'First match time', 'brackethive' ) . '</label><input type="time" id="brackethive-w-start" name="start_time" value="' . esc_attr( $d['start_time'] ) . '" /></p>';
		echo '<p><label for="brackethive-w-subtitle">' . esc_html__( 'Subtitle (optional)', 'brackethive' ) . '</label><input type="text" id="brackethive-w-subtitle" class="regular-text" name="subtitle" placeholder="' . esc_attr__( 'Amateur tournament open to all', 'brackethive' ) . '" /></p>';
		echo '<p><label for="brackethive-w-color">' . esc_html__( 'Accent color', 'brackethive' ) . '</label><input type="color" id="brackethive-w-color" name="accent_color" value="' . esc_attr( $d['accent_color'] ) . '" /></p>';
		echo '</div>';
		echo '</section>';

		/* ---------------- Étape 2 : format ---------------- */
		echo '<section class="brackethive-wizard__panel" data-brackethive-step="2" hidden>';
		echo '<h2>' . esc_html__( '2. The format', 'brackethive' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Choose how the teams face each other. The match bracket is generated automatically from this choice.', 'brackethive' ) . '</p>';
		echo '<div class="brackethive-wizard__choices">';
		foreach ( $formats as $key => $info ) {
			echo '<label class="brackethive-choice' . ( 'single' === $key ? ' is-selected' : '' ) . '">';
			echo '<input type="radio" name="format" value="' . esc_attr( $key ) . '"' . checked( 'single', $key, false ) . ' />';
			echo '<span class="brackethive-choice__title">' . esc_html( $info[0] ) . '</span>';
			echo '<span class="brackethive-choice__desc">' . esc_html( $info[1] ) . '</span>';
			echo '<span class="brackethive-choice__meta">' . esc_html( $info[2] ) . '</span>';
			echo '</label>';
		}
		echo '</div>';

		echo '<div class="brackethive-wizard__grid">';
		echo '<p><label for="brackethive-w-count">' . esc_html__( 'Number of teams', 'brackethive' ) . '</label><input type="number" id="brackethive-w-count" min="2" max="64" name="team_count" value="' . (int) $d['team_count'] . '" class="small-text" /><span class="description">' . esc_html__( 'From 2 to 64. If it is not a power of two (8, 16, 32…), the top seeds get a bye in the first round.', 'brackethive' ) . '</span></p>';
		echo '<p><label for="brackethive-w-players">' . esc_html__( 'Players per team', 'brackethive' ) . '</label><input type="number" id="brackethive-w-players" min="1" name="players_per_team" value="' . (int) $d['players_per_team'] . '" class="small-text" /></p>';

		echo '<p class="brackethive-if-groups"><label for="brackethive-w-groups">' . esc_html__( 'Number of groups', 'brackethive' ) . '</label><input type="number" id="brackethive-w-groups" min="2" max="8" name="group_count" value="' . (int) $d['group_count'] . '" class="small-text" /><span class="description">' . esc_html__( 'Teams are spread across the groups in snake order according to their seed. You can also build the groups by hand.', 'brackethive' ) . '</span></p>';
		echo '<p class="brackethive-if-groups"><label for="brackethive-w-qual">' . esc_html__( 'Qualifiers per group', 'brackethive' ) . '</label><input type="number" id="brackethive-w-qual" min="1" max="4" name="qualifiers_per_group" value="' . (int) $d['qualifiers_per_group'] . '" class="small-text" /><span class="description">' . esc_html__( 'Qualifiers are cross-matched between groups for the playoffs.', 'brackethive' ) . '</span></p>';
		echo '<input type="hidden" name="group_mode" value="auto" />';

		echo '<p class="brackethive-wizard__full brackethive-if-not-double"><label><input type="checkbox" name="third_place" value="1" /> ' . esc_html__( 'Play a third place match between the semi-final losers', 'brackethive' ) . '</label></p>';
		echo '<p class="brackethive-wizard__full brackethive-if-double"><label><input type="checkbox" name="bracket_reset" value="1" /> ' . esc_html__( 'Bracket reset if the team coming from the losers bracket wins the first grand final (strict rule)', 'brackethive' ) . '</label><span class="description">' . esc_html__( 'In double elimination, the loser of the losers bracket final is automatically 3rd.', 'brackethive' ) . '</span></p>';
		echo '</div>';
		echo '</section>';

		/* ---------------- Étape 3 : matchs et horaires ---------------- */
		echo '<section class="brackethive-wizard__panel" data-brackethive-step="3" hidden>';
		echo '<h2>' . esc_html__( '3. Matches and times', 'brackethive' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The schedule is calculated from these values: each match is given a time and stations. You can then adjust each time by hand.', 'brackethive' ) . '</p>';

		echo '<h3>' . esc_html__( 'Number of games per round', 'brackethive' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'BO1: one game. BO3: the first team to win two games. BO5: the first to three. A BO3 takes about twice as long as a BO1.', 'brackethive' ) . '</p>';
		$bo_rounds = array(
			'bo_group' => array( __( 'Group matches', 'brackethive' ), 'brackethive-if-groups' ),
			'bo_r64'   => array( __( 'Early rounds (round of 64, round of 32)', 'brackethive' ), '' ),
			'bo_r16'   => array( __( 'R16', 'brackethive' ), '' ),
			'bo_qf'    => array( __( 'Quarters', 'brackethive' ), '' ),
			'bo_sf'    => array( __( 'Semi-finals', 'brackethive' ), '' ),
			'bo_final' => array( __( 'Final', 'brackethive' ), '' ),
			'bo_lb'    => array( __( 'Losers bracket', 'brackethive' ), 'brackethive-if-double' ),
		);
		echo '<div class="brackethive-wizard__bo">';
		foreach ( $bo_rounds as $key => $info ) {
			echo '<label class="' . esc_attr( $info[1] ) . '">' . esc_html( $info[0] ) . ' <select name="' . esc_attr( $key ) . '"' . ( 'bo_r64' === $key ? ' data-brackethive-mirror="bo_r32"' : '' ) . '>';
			foreach ( array( 1, 3, 5 ) as $bo ) {
				echo '<option value="' . (int) $bo . '"' . selected( (int) $d[ $key ], $bo, false ) . '>BO' . (int) $bo . '</option>';
			}
			echo '</select></label>';
		}
		echo '<input type="hidden" name="bo_r32" value="' . (int) $d['bo_r32'] . '" />';
		echo '</div>';

		echo '<h3>' . esc_html__( 'Stations and durations', 'brackethive' ) . '</h3>';
		echo '<div class="brackethive-wizard__grid">';
		echo '<p><label for="brackethive-w-stations">' . esc_html__( 'Available gaming stations', 'brackethive' ) . '</label><input type="number" id="brackethive-w-stations" min="1" name="stations" value="' . (int) $d['stations'] . '" class="small-text" /><span class="description">' . esc_html__( 'Total number of PCs or consoles.', 'brackethive' ) . '</span></p>';
		echo '<p><label for="brackethive-w-parallel">' . esc_html__( 'Matches played at the same time', 'brackethive' ) . '</label><input type="number" id="brackethive-w-parallel" min="1" name="matches_parallel" value="' . (int) $d['matches_parallel'] . '" class="small-text" /><span class="description">' . esc_html__( 'With 8 stations and teams of 4, two matches can run simultaneously.', 'brackethive' ) . '</span></p>';
		echo '<p><label for="brackethive-w-duration">' . esc_html__( 'Length of one game (minutes)', 'brackethive' ) . '</label><input type="number" id="brackethive-w-duration" min="5" name="match_duration" value="' . (int) $d['match_duration'] . '" class="small-text" /><span class="description">' . esc_html__( 'Setup and team changeover included.', 'brackethive' ) . '</span></p>';
		echo '<p><label for="brackethive-w-warmup">' . esc_html__( 'Check-in before the first match (minutes)', 'brackethive' ) . '</label><input type="number" id="brackethive-w-warmup" min="0" name="warmup_minutes" value="' . (int) $d['warmup_minutes'] . '" class="small-text" /></p>';
		echo '<p><label for="brackethive-w-break">' . esc_html__( 'Break between rounds (minutes)', 'brackethive' ) . '</label><input type="number" id="brackethive-w-break" min="0" name="break_minutes" value="' . (int) $d['break_minutes'] . '" class="small-text" /></p>';
		echo '<p><label for="brackethive-w-end">' . esc_html__( 'Expected end time', 'brackethive' ) . '</label><input type="time" id="brackethive-w-end" name="end_time" value="' . esc_attr( $d['end_time'] ) . '" /><span class="description">' . esc_html__( 'Approximate, shown on the public page.', 'brackethive' ) . '</span></p>';
		echo '</div>';
		echo '</section>';

		/* ---------------- Étape 4 : inscriptions et création ---------------- */
		echo '<section class="brackethive-wizard__panel" data-brackethive-step="4" hidden>';
		echo '<h2>' . esc_html__( '4. Sign-ups and creation', 'brackethive' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Two ways to build the teams: enter them yourself in the Teams screen, or open a public form for the players to fill in (requests arrive pending approval).', 'brackethive' ) . '</p>';
		echo '<div class="brackethive-wizard__grid">';
		echo '<p class="brackethive-wizard__full"><label><input type="checkbox" name="registration_open" value="1" /> ' . esc_html__( 'Open the public sign-up form on the tournament page', 'brackethive' ) . '</label></p>';
		echo '<p><label for="brackethive-w-max">' . esc_html__( 'Maximum number of sign-ups', 'brackethive' ) . '</label><input type="number" id="brackethive-w-max" min="0" name="registration_max" value="' . (int) $d['registration_max'] . '" class="small-text" /><span class="description">' . esc_html__( '0 = unlimited. A rejected team frees up its slot.', 'brackethive' ) . '</span></p>';
		echo '<p><label for="brackethive-w-notify">' . esc_html__( 'E-mail notified on each sign-up', 'brackethive' ) . '</label><input type="email" id="brackethive-w-notify" class="regular-text" name="notify_email" value="' . esc_attr( get_option( 'admin_email' ) ) . '" /></p>';
		echo '<p class="brackethive-wizard__full"><label for="brackethive-w-msg">' . esc_html__( 'Message shown after sign-up', 'brackethive' ) . '</label><textarea id="brackethive-w-msg" name="registration_msg" rows="2" class="large-text">' . esc_textarea( $d['registration_msg'] ) . '</textarea></p>';
		echo '</div>';

		echo '<h3>' . esc_html__( 'Publishing', 'brackethive' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Creation generates a dedicated tournament page (listed under "Tournaments › All tournaments", not under "Pages"), with its Bracket, Schedule, Results and Teams tabs and, if you open it, Sign-up. As long as the page is not published, only administrators can see it. Its address is then shown on the dashboard.', 'brackethive' ) . '</p>';
		echo '<div class="brackethive-wizard__choices">';
		echo '<label class="brackethive-choice is-selected"><input type="radio" name="post_status" value="publish" checked="checked" />';
		echo '<span class="brackethive-choice__title">' . esc_html__( 'Publish the page right away', 'brackethive' ) . '</span>';
		echo '<span class="brackethive-choice__desc">' . esc_html__( 'The page goes live immediately: players can view it and sign up if the form is open.', 'brackethive' ) . '</span></label>';
		echo '<label class="brackethive-choice"><input type="radio" name="post_status" value="draft" />';
		echo '<span class="brackethive-choice__title">' . esc_html__( 'Keep as a draft', 'brackethive' ) . '</span>';
		echo '<span class="brackethive-choice__desc">' . esc_html__( 'You prepare the teams and the content at your own pace, then publish the page from "All tournaments" or "Edit page".', 'brackethive' ) . '</span></label>';
		echo '<label class="brackethive-choice"><input type="radio" name="post_status" value="none" />';
		echo '<span class="brackethive-choice__title">' . esc_html__( 'No dedicated page', 'brackethive' ) . '</span>';
		echo '<span class="brackethive-choice__desc">' . esc_html__( 'No page is visible for this tournament. You place the bracket, the form or the schedule on your own pages yourself, using the shortcodes.', 'brackethive' ) . '</span></label>';
		echo '</div>';

		echo '<p class="brackethive-wizard__full"><label><input type="checkbox" name="create_registration_page" value="1" /> ' . esc_html__( 'Also create a dedicated sign-up page', 'brackethive' ) . '</label><span class="description">' . esc_html__( 'A WordPress page containing only the simplified form (Team, Nickname, E-mail, Phone): ideal for a QR code or a poster. Its address and its QR code will appear on the dashboard.', 'brackethive' ) . '</span></p>';

		echo '<div class="brackethive-wizard__after">';
		echo '<h3>' . esc_html__( 'What happens after creation?', 'brackethive' ) . '</h3>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'Links: as soon as the tournament is created, the dashboard shows the address of the tournament page and of the sign-up form, ready to copy or to turn into a QR code.', 'brackethive' ) . '</li>';
		echo '<li>' . esc_html__( 'Tournament page: it is created automatically. It does not appear in the WordPress "Pages" menu but under "Tournaments › All tournaments" (the "View" button in the tournament bar). To publish or unpublish it later: "Edit page", then the Publish button. The "No dedicated page" option can be changed in Tournament settings.', 'brackethive' ) . '</li>';
		echo '<li>' . esc_html__( 'Sign-up page: if you open sign-ups, the form is already in the Sign-up tab of the tournament page. You can also create a separate WordPress page, easier to share, by pasting the shortcode below into it, then publish it.', 'brackethive' ) . '</li>';
		echo '<li>' . esc_html__( 'Sharing: a QR code or a link to one of these pages is all players need to sign up and then follow the tournament live.', 'brackethive' ) . '</li>';
		echo '<li>' . esc_html__( 'Teams: enter them in the Teams screen or approve the sign-ups received, then assign the seeds. The dashboard reminds you of these steps.', 'brackethive' ) . '</li>';
		echo '</ol>';
		echo '<p><code>[brackethive_inscription simple="yes"]</code> <span class="description">' . esc_html__( 'short form (Team, Nickname, E-mail, Phone)', 'brackethive' ) . '</span><br /><code>[brackethive_inscription]</code> <span class="description">' . esc_html__( 'full form with the player list', 'brackethive' ) . '</span></p>';
		echo '</div>';

		echo '<h3>' . esc_html__( 'Summary', 'brackethive' ) . '</h3>';
		echo '<dl class="brackethive-wizard__summary" data-brackethive-summary>';
		$summary = array(
			'title'   => __( 'Tournament', 'brackethive' ),
			'format'  => __( 'Format', 'brackethive' ),
			'teams'   => __( 'Teams', 'brackethive' ),
			'matches' => __( 'Matches to play', 'brackethive' ),
			'when'    => __( 'When', 'brackethive' ),
			'end'     => __( 'Estimated end', 'brackethive' ),
			'signup'  => __( 'Sign-ups', 'brackethive' ),
			'status'  => __( 'Tournament page', 'brackethive' ),
		);
		foreach ( $summary as $key => $label ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd data-brackethive-sum="' . esc_attr( $key ) . '">—</dd>';
		}
		echo '</dl>';
		echo '<p class="description">' . esc_html__( 'Everything can still be changed after creation. The match bracket is created immediately; it fills in as the teams are given a seed.', 'brackethive' ) . '</p>';
		echo '</section>';

		// Navigation.
		echo '<div class="brackethive-wizard__nav">';
		echo '<button type="button" class="button" data-brackethive-prev>' . esc_html__( '← Previous', 'brackethive' ) . '</button>';
		echo '<button type="button" class="button button-primary" data-brackethive-next>' . esc_html__( 'Next →', 'brackethive' ) . '</button>';
		echo '<button type="submit" class="button button-primary button-hero" data-brackethive-submit>' . esc_html__( 'Create the tournament', 'brackethive' ) . '</button>';
		echo '</div>';

		// Libellés transmis au script par l'API de WordPress plutôt que par
		// une balise <script> écrite à la main.
		wp_add_inline_script(
			'brackethive-admin',
			'window.BRACKETHIVE_WIZARD_I18N = ' . wp_json_encode(
				array(
					'formats'  => array(
						'single' => $formats['single'][0],
						'double' => $formats['double'][0],
						'groups' => $formats['groups'][0],
					),
					/* translators: %d: nombre d'équipes */
					'teams'    => __( '%d teams', 'brackethive' ),
					/* translators: 1: nombre de poules, 2: nombre de qualifiés par poule */
					'groups'   => __( '%1$d groups, %2$d qualifiers per group', 'brackethive' ),
					/* translators: %d: nombre de matchs estimé */
					'matches'  => __( 'about %d', 'brackethive' ),
					'open'     => __( 'Public form open', 'brackethive' ),
					'closed'   => __( 'Entered by the organizers', 'brackethive' ),
					'publish'  => __( 'Published immediately', 'brackethive' ),
					'draft'    => __( 'Draft, to publish later', 'brackethive' ),
					'none'     => __( 'No dedicated page (shortcodes)', 'brackethive' ),
					'at'       => __( 'to', 'brackethive' ),
					'required' => __( 'The tournament name is required.', 'brackethive' ),
				)
			) . ';'
		);

		echo '</form>';
	}

	/**
	 * Zone sensible d'un tournoi : réinitialisation et suppression, avec
	 * confirmation par saisie.
	 *
	 * @param int $tid Tournoi.
	 */
	protected static function danger_zone( $tid ) {
		echo '<h2 id="brackethive-danger">' . esc_html__( 'Danger zone: reset or delete the tournament', 'brackethive' ) . '</h2>';
		echo '<div class="brackethive-danger">';
		echo '<p><strong>' . esc_html__( 'These actions cannot be undone and only affect the selected tournament.', 'brackethive' ) . '</strong></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'brackethive_tools' );
		echo '<input type="hidden" name="action" value="brackethive_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p><label for="brackethive-confirm">' . esc_html__( 'To empty the tournament (teams + results), type REINITIALISER:', 'brackethive' ) . '</label><br />';
		echo '<input type="text" id="brackethive-confirm" name="confirm" value="" autocomplete="off" placeholder="REINITIALISER" /> ';
		echo '<button class="button button-link-delete" name="tool" value="reset_all" onclick="return confirm(\'' . esc_js( __( 'Permanently delete all teams and all results of this tournament?', 'brackethive' ) ) . '\');">' . esc_html__( 'Reset', 'brackethive' ) . '</button></p>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'brackethive_tools' );
		echo '<input type="hidden" name="action" value="brackethive_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p><label for="brackethive-confirm-del">' . esc_html__( 'To delete the tournament and its page, type SUPPRIMER:', 'brackethive' ) . '</label><br />';
		echo '<input type="text" id="brackethive-confirm-del" name="confirm" value="" autocomplete="off" placeholder="SUPPRIMER" /> ';
		echo '<button class="button button-link-delete" name="tool" value="delete_tournament" onclick="return confirm(\'' . esc_js( __( 'Permanently delete this tournament, its page and all its data?', 'brackethive' ) ) . '\');">' . esc_html__( 'Delete the tournament', 'brackethive' ) . '</button></p>';
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
		$has_page = Brackethive_Tournament::has_public_page( $tid );
		$status   = get_post_status( $tid );
		$url      = get_permalink( $tid );
		$slug     = get_post_field( 'post_name', $tid );
		$reg_page = Brackethive_Tournament::registration_page( $tid );

		echo '<div class="brackethive-links"><h2>' . esc_html__( 'Public links', 'brackethive' ) . '</h2>';

		if ( $has_page && 'publish' !== $status ) {
			echo '<p class="brackethive-links__warn">' . esc_html__( 'The tournament page is a draft: its address will only work for visitors once it is published.', 'brackethive' ) . '</p>';
		}

		if ( $has_page ) {
			self::link_row(
				__( 'Tournament page', 'brackethive' ),
				__( 'bracket, schedule, results, teams', 'brackethive' ),
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
				__( 'Sign-up page', 'brackethive' ),
				'publish' === $reg_status
					? __( 'to share with players', 'brackethive' )
					: __( 'not published: publish it to make it accessible', 'brackethive' ),
				$reg_url,
				'inscription',
				get_edit_post_link( $reg_page )
			);
		} elseif ( $has_page && ! empty( $s['registration_open'] ) ) {
			self::link_row(
				__( 'Sign-up form', 'brackethive' ),
				__( 'Sign-up tab of the tournament page', 'brackethive' ),
				$url . '#brackethive-registration',
				'inscription'
			);
		}

		if ( ! $reg_page ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-inline-form">';
			wp_nonce_field( 'brackethive_create_registration_page' );
			echo '<input type="hidden" name="action" value="brackethive_create_registration_page" />';
			echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
			echo '<button class="button">' . esc_html__( 'Create a dedicated sign-up page', 'brackethive' ) . '</button> ';
			echo '<span class="description">' . esc_html__( 'A WordPress page containing only the simplified form: shorter to share and to print as a QR code.', 'brackethive' ) . '</span>';
			echo '</form>';
		}

		if ( empty( $s['registration_open'] ) ) {
			echo '<p class="description">' . esc_html__( 'Sign-ups are closed: the form will show "sign-ups closed" until you open them in Tournament settings.', 'brackethive' ) . '</p>';
		}

		if ( ! $has_page ) {
			echo '<p class="description">' . esc_html__( 'This tournament has no dedicated page. Paste these shortcodes into your own pages, then publish them:', 'brackethive' ) . '</p>';
			echo '<p><code>[brackethive_tournoi tournoi="' . esc_html( $slug ) . '"]</code> ' . esc_html__( 'full tabbed page', 'brackethive' ) . '<br />';
			echo '<code>[brackethive_inscription simple="yes" tournoi="' . esc_html( $slug ) . '"]</code> ' . esc_html__( 'simplified sign-up form', 'brackethive' ) . '<br />';
			echo '<code>[brackethive_tableau tournoi="' . esc_html( $slug ) . '"]</code> ' . esc_html__( 'bracket only (control room screen)', 'brackethive' ) . '</p>';
		}

		$pages = self::pages_using_shortcodes( $tid );
		if ( $pages ) {
			echo '<p><strong>' . esc_html__( 'Site pages using the shortcodes', 'brackethive' ) . '</strong><br />';
			foreach ( $pages as $page ) {
				echo '<a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $page ) ) . '</a>';
				if ( 'publish' !== $page->post_status ) {
					echo ' <em>(' . esc_html__( 'not published', 'brackethive' ) . ')</em>';
				}
				echo ' — <a href="' . esc_url( get_edit_post_link( $page->ID ) ) . '">' . esc_html__( 'edit', 'brackethive' ) . '</a><br />';
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

		echo '<div class="brackethive-link">';
		echo '<p class="brackethive-link__head"><strong>' . esc_html( $label ) . '</strong> — ' . esc_html( $hint ) . '</p>';
		echo '<p class="brackethive-link__row">';
		echo '<input type="text" class="regular-text code" readonly value="' . esc_attr( $url ) . '" onfocus="this.select();" /> ';
		echo '<button type="button" class="button button-small" data-brackethive-copy>' . esc_html__( 'Copy', 'brackethive' ) . '</button> ';
		echo '<a class="button button-small" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open', 'brackethive' ) . '</a>';
		if ( $edit_url ) {
			echo ' <a class="button button-small" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'brackethive' ) . '</a>';
		}
		echo '</p>';
		/*
		 * QR code calculé côté serveur : il s'affiche même si les scripts de
		 * l'administration sont regroupés ou différés par une extension de
		 * cache, et aucune adresse n'est envoyée à un service extérieur.
		 */
		$svg = Brackethive_QR::svg_data_uri( $url );
		if ( '' !== $svg ) {
			$png = Brackethive_QR::png_data_uri( $url, 16 );

			echo '<div class="brackethive-qr">';
			echo '<img class="brackethive-qr__img" src="' . esc_attr( $svg ) . '" width="120" height="120" alt="' . esc_attr(
				sprintf(
					/* translators: %s: intitulé du lien */
					__( 'QR code to %s', 'brackethive' ),
					$label
				)
			) . '" />';
			echo '<span class="brackethive-qr__actions"><span class="brackethive-qr__label">' . esc_html__( 'Download the QR code', 'brackethive' ) . '</span>';
			if ( '' !== $png ) {
				echo '<a class="button button-small" href="' . esc_attr( $png ) . '" download="' . esc_attr( $slug ) . '.png">' . esc_html__( 'PNG', 'brackethive' ) . '</a>';
			}
			echo '<a class="button button-small" href="' . esc_attr( $svg ) . '" download="' . esc_attr( $slug ) . '.svg">' . esc_html__( 'SVG', 'brackethive' ) . '</a>';
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

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tables propres à l'extension, écriture directe nécessaire ; pas de cache objet pour ces données transactionnelles.
			"SELECT ID, post_title, post_status, post_type, post_name FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status IN ('publish','draft','pending','private') AND post_content LIKE '%[brackethive_%' ORDER BY post_title ASC LIMIT 50" // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Requête littérale sans donnée utilisateur ; seule la propriété $wpdb->posts est interpolée.
		);

		$slug = get_post_field( 'post_name', $tid );
		$out  = array();
		foreach ( (array) $rows as $row ) {
			$content = get_post_field( 'post_content', $row->ID );
			// Codes courts visant explicitement un autre tournoi : ignorés.
			if ( preg_match_all( '/\[brackethive_[a-z_]+[^\]]*\b(?:tournoi|tournament)="([^"]*)"/', $content, $m ) ) {
				$targets = array_unique( $m[1] );
				if ( ! in_array( $slug, $targets, true ) && ! in_array( (string) $tid, $targets, true ) && count( $targets ) === substr_count( $content, '[brackethive_' ) ) {
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

		$teams_url   = add_query_arg( array( 'page' => 'brackethive-teams', 'tournament' => $tid ), admin_url( 'admin.php' ) );
		$matches_url = add_query_arg( array( 'page' => 'brackethive-matches', 'tournament' => $tid ), admin_url( 'admin.php' ) );
		$public_url  = get_permalink( $tid );

		$done_teams = $stats['teams_active'] >= $stats['teams_max'];
		$done_seed  = $stats['teams_seeded'] >= $stats['teams_max'];

		$steps = array();

		if ( 'publish' !== get_post_status( $tid ) && Brackethive_Tournament::has_public_page( $tid ) ) {
			$steps[] = array(
				false,
				__( 'Publish the tournament page', 'brackethive' ),
				__( 'The page is a draft: only administrators can see it. Publish it once the content is ready so players can view it and sign up.', 'brackethive' ),
				get_edit_post_link( $tid, 'raw' ),
				__( 'Edit and publish', 'brackethive' ),
			);
		}

		$steps[] = array(
				$done_teams,
				sprintf(
					/* translators: 1: équipes validées, 2: équipes attendues */
					__( 'Build the teams (%1$d / %2$d approved)', 'brackethive' ),
					$stats['teams_active'],
					$stats['teams_max']
				),
				! empty( $s['registration_open'] )
					? __( 'The public form is open: approve the requests received, or add teams yourself.', 'brackethive' )
					: __( 'Add each team from the Teams screen, or open public sign-ups in Tournament settings.', 'brackethive' ),
				$teams_url,
				__( 'Open Teams', 'brackethive' ),
			);
		$steps[] = array(
				$done_seed,
				__( 'Assign the seeds', 'brackethive' ),
				__( 'Each approved team gets a number from 1 to N that places it in the bracket. The "Random draw for seeds" button does it for you.', 'brackethive' ),
				$teams_url,
				__( 'Place the teams', 'brackethive' ),
			);
		if ( Brackethive_Tournament::has_public_page( $tid ) ) {
			$steps[] = array(
				false,
				__( 'Check the public page', 'brackethive' ),
				__( 'The tournament page shows the bracket, schedule, results and sign-ups. Its links are in the "Public links" box above.', 'brackethive' ),
				$public_url,
				__( 'View the page', 'brackethive' ),
			);
		} else {
			$steps[] = array(
				false,
				__( 'Put the shortcodes on your pages', 'brackethive' ),
				__( 'This tournament has no dedicated page: copy the shortcodes from the "Public links" box into the pages of your choice, then publish them.', 'brackethive' ),
				add_query_arg( 'post_type', 'page', admin_url( 'post-new.php' ) ),
				__( 'Create a page', 'brackethive' ),
			);
		}
		$steps[] = array(
				false,
				__( 'On the day: enter the scores', 'brackethive' ),
				__( 'In Matches & scores, enter the result of each match: the winner automatically moves on to the next round and the public page updates by itself.', 'brackethive' ),
				$matches_url,
				__( 'Open Matches & scores', 'brackethive' ),
			);

		echo '<div class="brackethive-next"><h2>' . esc_html__( 'Next steps', 'brackethive' ) . '</h2><ol class="brackethive-next__list">';
		foreach ( $steps as $step ) {
			echo '<li class="brackethive-next__item' . ( $step[0] ? ' is-done' : '' ) . '">';
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
		self::guard( 'brackethive_duplicate_tournament' );

		$source = self::posted_tournament();
		$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
		$teams  = ! empty( $_POST['with_teams'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		$id = Brackethive_Tournament::duplicate( $source, $title, $teams );

		if ( is_wp_error( $id ) ) {
			self::back( 'brackethive-dashboard', 'error', $id->get_error_message(), $source );
		}

		self::back( 'brackethive-dashboard', 'updated', __( 'Tournament duplicated.', 'brackethive' ), $id );
	}

	/**
	 * Réglages d'un tournoi.
	 */
	public static function handle_save_tournament() {
		self::guard( 'brackethive_save_tournament' );

		$tid = self::posted_tournament();
		if ( ! $tid ) {
			self::back( 'brackethive-tournament', 'error', __( 'Tournament not found.', 'brackethive' ) );
		}

		$before = Brackethive_Tournament::settings( $tid );

		Brackethive_Tournament::save_settings( $tid, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.

		$after = Brackethive_Tournament::settings( $tid );

		// Le format a changé : la structure du tableau doit suivre.
		$structural = array( 'format', 'team_count', 'group_count', 'qualifiers_per_group', 'group_mode', 'bracket_reset', 'bo_group', 'bo_r64', 'bo_r32', 'bo_r16', 'bo_qf', 'bo_sf', 'bo_final', 'bo_lb', 'third_place' );
		foreach ( $structural as $key ) {
			if ( $before[ $key ] !== $after[ $key ] ) {
				Brackethive_Data::ensure_bracket( $tid );
				Brackethive_Data::recalculate( $tid );
				break;
			}
		}

		self::back( 'brackethive-tournament', 'updated', __( 'Tournament settings saved.', 'brackethive' ), $tid );
	}

	/* ---------------------------------------------------------------------
	 * Traitements — équipes, matchs, outils
	 * ------------------------------------------------------------------ */

	/**
	 * Création / édition d'une équipe.
	 */
	public static function handle_save_team() {
		self::guard( 'brackethive_save_team' );

		$tid = self::posted_tournament();

		$data = array(
			'tournament_id' => $tid,
			'id'            => isset( $_POST['id'] ) ? (int) $_POST['id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			'name'          => isset( $_POST['name'] ) ? wp_unslash( $_POST['name'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			'tag'           => isset( $_POST['tag'] ) ? wp_unslash( $_POST['tag'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			'seed'          => isset( $_POST['seed'] ) ? (int) $_POST['seed'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			'group_id'      => isset( $_POST['group_id'] ) ? (int) $_POST['group_id'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			'captain'       => isset( $_POST['captain'] ) ? wp_unslash( $_POST['captain'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			'email'         => isset( $_POST['email'] ) ? wp_unslash( $_POST['email'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			'phone'         => isset( $_POST['phone'] ) ? wp_unslash( $_POST['phone'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			'players'       => isset( $_POST['players'] ) ? wp_unslash( $_POST['players'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			'notes'         => isset( $_POST['notes'] ) ? wp_unslash( $_POST['notes'] ) : '', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			'status'        => isset( $_POST['status'] ) ? wp_unslash( $_POST['status'] ) : 'pending', // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
		);

		$result = Brackethive_Data::save_team( $data );

		if ( is_wp_error( $result ) ) {
			$extra = $data['id'] ? array( 'edit' => $data['id'] ) : array();
			self::back( 'brackethive-teams', 'error', $result->get_error_message(), $tid, $extra );
		}

		self::back( 'brackethive-teams', 'updated', __( 'Team saved.', 'brackethive' ), $tid );
	}

	/**
	 * Suppression d'une équipe.
	 */
	public static function handle_delete_team() {
		self::guard( 'brackethive_delete_team' );

		$tid = self::posted_tournament();
		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		if ( $id ) {
			Brackethive_Data::delete_team( $id );
		}

		self::back( 'brackethive-teams', 'updated', __( 'Team deleted.', 'brackethive' ), $tid );
	}

	/**
	 * Enregistrement d'un résultat.
	 */
	public static function handle_save_result() {
		self::guard( 'brackethive_save_result' );

		$tid      = self::posted_tournament();
		$match_id = isset( $_POST['match_id'] ) ? (int) $_POST['match_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		$games = array();
		if ( isset( $_POST['games'] ) && is_array( $_POST['games'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			foreach ( wp_unslash( $_POST['games'] ) as $game ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
				$games[] = array(
					'score1' => isset( $game['score1'] ) ? sanitize_text_field( $game['score1'] ) : '',
					'score2' => isset( $game['score2'] ) ? sanitize_text_field( $game['score2'] ) : '',
					'map'    => isset( $game['map'] ) ? sanitize_text_field( $game['map'] ) : '',
				);
			}
		}

		$result = Brackethive_Data::save_result(
			$match_id,
			array(
				'status'     => isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : 'pending', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
				'score1'     => isset( $_POST['score1'] ) ? (int) $_POST['score1'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
				'score2'     => isset( $_POST['score2'] ) ? (int) $_POST['score2'] : 0, // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
				'referee'    => isset( $_POST['referee'] ) ? sanitize_text_field( wp_unslash( $_POST['referee'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
				'stations'   => isset( $_POST['stations'] ) ? sanitize_text_field( wp_unslash( $_POST['stations'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
				'start_time' => isset( $_POST['start_time'] ) ? sanitize_text_field( wp_unslash( $_POST['start_time'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
				'end_time'   => isset( $_POST['end_time'] ) ? sanitize_text_field( wp_unslash( $_POST['end_time'] ) ) : '', // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
				'games'      => $games,
			)
		);

		if ( is_wp_error( $result ) ) {
			self::back( 'brackethive-matches', 'error', $result->get_error_message(), $tid );
		}

		self::back( 'brackethive-matches', 'updated', __( 'Result saved.', 'brackethive' ), $tid );
	}

	/**
	 * Outils.
	 */
	public static function handle_tools() {
		self::guard( 'brackethive_tools' );

		$tid  = self::posted_tournament();
		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		if ( ! $tid ) {
			self::back( 'brackethive-dashboard', 'error', __( 'No tournament selected.', 'brackethive' ) );
		}

		if ( 'autoseed' === $tool ) {
			$placed = Brackethive_Data::autoseed( $tid, true );
			self::back(
				'brackethive-teams',
				'updated',
				sprintf(
					/* translators: %d: nombre d'équipes */
					__( '%d team(s) placed randomly in the bracket.', 'brackethive' ),
					$placed
				),
				$tid
			);
		}

		if ( 'reset_results' === $tool ) {
			Brackethive_Data::reset_results( $tid );
			self::back( 'brackethive-matches', 'updated', __( 'All results have been reset to zero.', 'brackethive' ), $tid );
		}

		if ( 'rebuild' === $tool ) {
			Brackethive_Data::ensure_bracket( $tid );
			Brackethive_Data::recalculate( $tid );
			self::back( 'brackethive-dashboard', 'updated', __( 'Bracket regenerated.', 'brackethive' ), $tid );
		}

		if ( 'reschedule' === $tool ) {
			Brackethive_Data::rebuild_schedule( $tid );
			self::back( 'brackethive-planning', 'updated', __( 'Schedule recalculated from the tournament settings.', 'brackethive' ), $tid );
		}

		if ( 'validate_all' === $tool ) {
			global $wpdb;
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables propres à l'extension ; nom de table issu de brackethive_table(), valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
				$wpdb->prepare(
					'UPDATE ' . brackethive_table( 'teams' ) . " SET status = 'active' WHERE tournament_id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nom de table construit par brackethive_table(), sans donnée utilisateur ; les valeurs passent par $wpdb->prepare().
					$tid
				)
			);
			Brackethive_Data::recalculate( $tid );
			self::back( 'brackethive-teams', 'updated', __( 'All pending sign-ups have been approved.', 'brackethive' ), $tid );
		}

		if ( 'reset_all' === $tool ) {
			$confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			if ( 'REINITIALISER' !== strtoupper( remove_accents( $confirm ) ) ) {
				self::back( 'brackethive-dashboard', 'error', __( 'Reset cancelled: the confirmation word does not match.', 'brackethive' ), $tid );
			}
			Brackethive_Data::reset_all( $tid );
			self::back( 'brackethive-dashboard', 'updated', __( 'Tournament reset: teams and results deleted, empty bracket recreated.', 'brackethive' ), $tid );
		}

		if ( 'delete_tournament' === $tool ) {
			$confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			if ( 'SUPPRIMER' !== strtoupper( remove_accents( $confirm ) ) ) {
				self::back( 'brackethive-dashboard', 'error', __( 'Deletion cancelled: the confirmation word does not match.', 'brackethive' ), $tid );
			}
			Brackethive_Tournament::delete( $tid );
			self::back( 'brackethive-dashboard', 'updated', __( 'Tournament deleted.', 'brackethive' ) );
		}

		self::back( 'brackethive-dashboard', 'error', __( 'Unknown action.', 'brackethive' ), $tid );
	}

	/**
	 * Réglages généraux.
	 */
	public static function handle_save_settings() {
		self::guard( 'brackethive_save_settings' );

		Brackethive_Settings::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.

		if ( isset( $_POST['default_tournament'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			$default = (int) $_POST['default_tournament']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			update_option( 'brackethive_default_tournament', Brackethive_Tournament::exists( $default ) ? $default : 0 );
		}
		self::back( 'brackethive-settings', 'updated', __( 'Settings saved.', 'brackethive' ) );
	}

	/* ---------------------------------------------------------------------
	 * Traitements — sauvegarde, mises à jour, aperçu
	 * ------------------------------------------------------------------ */

	/**
	 * Téléchargement de la sauvegarde JSON.
	 */
	public static function handle_export() {
		self::guard( 'brackethive_export' );

		$tid = self::posted_tournament();
		if ( ! $tid ) {
			self::back( 'brackethive-dashboard', 'error', __( 'No tournament selected.', 'brackethive' ) );
		}

		$json = wp_json_encode( Brackethive_IO::build_export( $tid ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . Brackethive_IO::filename( $tid ) . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- Flux JSON produit par wp_json_encode() et servi en téléchargement : tout échappement HTML corromprait le fichier.
		exit;
	}

	/**
	 * Restauration depuis un fichier JSON.
	 */
	public static function handle_import() {
		self::guard( 'brackethive_import' );

		$tid = self::posted_tournament();
		if ( ! $tid ) {
			self::back( 'brackethive-dashboard', 'error', __( 'No tournament selected.', 'brackethive' ) );
		}

		if ( empty( $_FILES['backup']['tmp_name'] ) || ! is_uploaded_file( $_FILES['backup']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			self::back( 'brackethive-dashboard', 'error', __( 'No file received.', 'brackethive' ), $tid );
		}

		if ( isset( $_FILES['backup']['error'] ) && UPLOAD_ERR_OK !== (int) $_FILES['backup']['error'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			self::back( 'brackethive-dashboard', 'error', __( 'The file upload failed.', 'brackethive' ), $tid );
		}

		$size = isset( $_FILES['backup']['size'] ) ? (int) $_FILES['backup']['size'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
		if ( $size <= 0 || $size > 5 * MB_IN_BYTES ) {
			self::back( 'brackethive-dashboard', 'error', __( 'File empty or too large (5 MB maximum).', 'brackethive' ), $tid );
		}

		$path = sanitize_text_field( $_FILES['backup']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
		$json = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Lecture du fichier téléversé temporaire local, hors système de fichiers WordPress.

		if ( false === $json ) {
			self::back( 'brackethive-dashboard', 'error', __( 'The file could not be read.', 'brackethive' ), $tid );
		}

		$result = Brackethive_IO::import( $json, $tid, ! empty( $_POST['with_settings'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		if ( is_wp_error( $result ) ) {
			self::back( 'brackethive-dashboard', 'error', $result->get_error_message(), $tid );
		}

		self::back( 'brackethive-dashboard', 'updated', __( 'Backup restored.', 'brackethive' ), $tid );
	}

	/**
	 * Vérification manuelle des mises à jour.
	 */
	/**
	 * Invitation affichée une seule fois après l'activation : choix de la
	 * langue et création du premier tournoi.
	 */
	public static function welcome_notice() {
		if ( ! get_option( 'brackethive_welcome' ) || ! current_user_can( self::cap() ) ) {
			return;
		}

		$settings = add_query_arg( 'page', 'brackethive-settings', admin_url( 'admin.php' ) );
		$create   = add_query_arg( 'page', 'brackethive-new', admin_url( 'admin.php' ) );
		$dismiss  = wp_nonce_url( add_query_arg( 'brackethive_dismiss', '1', $settings ), 'brackethive_dismiss' );
		$locale   = determine_locale();
		$names    = brackethive_available_locales();
		$current  = isset( $names[ $locale ] ) ? $names[ $locale ] : __( 'English', 'brackethive' );

		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Brackethive is ready.', 'brackethive' ) . '</strong> ';
		echo esc_html(
			sprintf(
				/* translators: %s: language name */
				__( 'The plugin follows your site language (%s). You can pick another one in its settings.', 'brackethive' ),
				$current
			)
		) . '</p><p>';
		echo '<a class="button button-primary" href="' . esc_url( $create ) . '">' . esc_html__( 'Create a tournament', 'brackethive' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $settings ) . '">' . esc_html__( 'Choose the language', 'brackethive' ) . '</a> ';
		echo '<a class="button-link" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'brackethive' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Fermeture de l'invitation.
	 */
	public static function maybe_dismiss_welcome() {
		if ( empty( $_GET['brackethive_dismiss'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce vérifié juste après.
			return;
		}
		if ( ! current_user_can( self::cap() ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'brackethive_dismiss' ) ) {
			delete_option( 'brackethive_welcome' );
		}
	}

	/**
	 * Crée la page d'inscription du tournoi courant.
	 */
	public static function handle_create_registration_page() {
		self::guard( 'brackethive_create_registration_page' );

		$tid = isset( $_POST['tournament_id'] ) ? (int) $_POST['tournament_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
		if ( ! $tid ) {
			self::back( 'brackethive-dashboard', 'error', __( 'Tournament not found.', 'brackethive' ) );
		}

		$page_id = Brackethive_Tournament::create_registration_page( $tid );
		if ( is_wp_error( $page_id ) ) {
			self::back( 'brackethive-dashboard', 'error', $page_id->get_error_message(), $tid );
		}

		self::back( 'brackethive-dashboard', 'updated', __( 'Sign-up page created and published.', 'brackethive' ), $tid );
	}

	/**
	 * E-mail de test : vérifie que le site sait envoyer des messages.
	 */
	public static function handle_test_mail() {
		self::guard( 'brackethive_test_mail' );

		$to = isset( $_POST['to'] ) ? sanitize_email( wp_unslash( $_POST['to'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
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
				__( '[%s] Brackethive test email', 'brackethive' ),
				get_bloginfo( 'name' )
			),
			__( 'If you receive this message, the site can send sign-up notifications.', 'brackethive' ) . "\n" . home_url( '/' )
		);

		remove_action( 'wp_mail_failed', $catch );

		if ( $sent ) {
			self::back(
				'brackethive-settings',
				'updated',
				sprintf(
					/* translators: %s: adresse e-mail */
					__( 'Test email handed to the server for %s. Check the inbox (and the spam folder); if it does not arrive, your hosting does not relay emails: install an SMTP plugin.', 'brackethive' ),
					$to
				)
			);
		}

		self::back(
			'brackethive-settings',
			'error',
			sprintf(
				/* translators: %s: message d'erreur */
				__( 'The server could not send the email: %s. Set up sending (SMTP plugin) before opening sign-ups.', 'brackethive' ),
				$error ? $error : __( 'no mail function available', 'brackethive' )
			)
		);
	}

	public static function handle_check_update() {
		self::guard( 'brackethive_check_update' );

		if ( ! self::has_updater() ) {
			self::back( 'brackethive-settings', 'error', __( 'Updates are handled by WordPress itself in this version of the plugin.', 'brackethive' ) );
		}

		Brackethive_Updater::force_check();
		$manifest = Brackethive_Updater::manifest( true );

		if ( ! $manifest ) {
			self::back( 'brackethive-settings', 'error', __( 'No valid manifest could be retrieved. Check the URL (the package must be served over HTTPS).', 'brackethive' ) );
		}

		if ( version_compare( $manifest['version'], BRACKETHIVE_VERSION, '>' ) ) {
			self::back(
				'brackethive-settings',
				'updated',
				sprintf(
					/* translators: %s: numéro de version */
					__( 'Version %s available. Go to the Plugins page to install it.', 'brackethive' ),
					$manifest['version']
				)
			);
		}

		self::back( 'brackethive-settings', 'updated', __( 'The plugin is up to date.', 'brackethive' ) );
	}

	/**
	 * Contenu de l'iframe d'aperçu.
	 */
	public static function handle_preview_frame() {
		self::guard( 'brackethive_preview_frame' );

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'full'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce vérifié par self::guard() en tête de méthode.
		if ( ! in_array( $view, Brackethive_Render::views(), true ) ) {
			$view = 'full';
		}

		$tid = isset( $_GET['tournament'] ) ? (int) $_GET['tournament'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce vérifié par self::guard() en tête de méthode.
		if ( ! Brackethive_Tournament::exists( $tid ) ) {
			$tid = Brackethive_Tournament::current_public();
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );

		/*
		 * Bien que ce document soit autonome (il n'est pas rendu par le
		 * thème), ses feuilles de style et ses scripts passent par l'API
		 * d'enregistrement de WordPress, puis sont imprimés explicitement :
		 * versions, dépendances et filtres restent ainsi respectés.
		 */
		wp_enqueue_style( 'brackethive-public', BRACKETHIVE_URL . 'assets/css/brackethive-public.css', array(), BRACKETHIVE_VERSION );
		wp_add_inline_style( 'brackethive-public', 'html,body{margin:0;padding:0;background:transparent}' );

		wp_enqueue_script( 'brackethive-public', BRACKETHIVE_URL . 'assets/js/brackethive-public.js', array(), BRACKETHIVE_VERSION, true );
		wp_localize_script(
			'brackethive-public',
			'BRACKETHIVE_CFG',
			array(
				'endpoint' => '',
				'interval' => 0,
				'i18n'     => array(),
			)
		);

		// L'iframe annonce sa hauteur à la page qui l'héberge.
		wp_add_inline_script(
			'brackethive-public',
			'(function(){function send(){parent.postMessage({ brackethivePreviewHeight: document.documentElement.scrollHeight }, window.location.origin);}'
			. 'window.addEventListener("load", send);window.addEventListener("resize", send);'
			. 'document.addEventListener("click", function(){ setTimeout(send, 60); });setInterval(send, 1000);})();'
		);

		echo '<!DOCTYPE html><html ';
		language_attributes();
		echo '><head><meta charset="utf-8" />';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
		echo '<title>' . esc_html__( 'Preview', 'brackethive' ) . '</title>';
		wp_print_styles();
		echo '</head><body>';

		echo Brackethive_Render::view( $view, array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendu HTML complet, échappement effectué dans les gabarits de Brackethive_Render.

		wp_print_footer_scripts();

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
		$tid = Brackethive_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Tournaments', 'brackethive' ) );
			return;
		}

		$settings = Brackethive_Tournament::settings( $tid );
		$stats    = Brackethive_Data::get_stats( $tid );
		$teams    = Brackethive_Data::get_teams_map( $tid );
		$rounds   = Brackethive_Data::get_matches_by_round( $tid );
		$labels   = Brackethive_Bracket::round_order( $settings );

		echo '<div class="wrap brackethive-admin">';
		echo '<h1>' . esc_html__( 'Dashboard', 'brackethive' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'brackethive-dashboard' );

		echo '<div class="brackethive-cards">';
		$cards = array(
			array( __( 'Signed-up teams', 'brackethive' ), $stats['teams_total'] ),
			array( __( 'Approved teams', 'brackethive' ), $stats['teams_active'] ),
			array( __( 'Seeds assigned', 'brackethive' ), $stats['teams_seeded'] . ' / ' . $stats['teams_max'] ),
			array( __( 'Matches played', 'brackethive' ), $stats['matches_done'] . ' / ' . $stats['matches_total'] ),
		);
		foreach ( $cards as $card ) {
			echo '<div class="brackethive-card"><span class="brackethive-card__value">' . esc_html( $card[1] ) . '</span><span class="brackethive-card__label">' . esc_html( $card[0] ) . '</span></div>';
		}
		echo '</div>';

		if ( $stats['champion_id'] ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'Winner:', 'brackethive' ) . '</strong> ' . esc_html( Brackethive_Data::team_name( $stats['champion_id'], $teams ) );
			if ( $stats['third_id'] ) {
				echo ' — ' . esc_html__( 'Third place:', 'brackethive' ) . ' ' . esc_html( Brackethive_Data::team_name( $stats['third_id'], $teams ) );
			}
			echo '</p></div>';
		}

		if ( $stats['teams_seeded'] < $stats['teams_max'] ) {
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %d: nombre d'équipes attendu */
					__( 'The bracket is not complete: assign a seed (1 to %d) to each approved team from the Teams screen.', 'brackethive' ),
					$stats['teams_max']
				)
			) . '</p></div>';
		}

		if ( 'groups' === $settings['format'] && 'manual' === $settings['group_mode'] ) {
			$layout   = Brackethive_Bracket::group_layout( $settings );
			$expected = (int) ceil( $layout['count'] / max( 1, $layout['groups'] ) );
			$uneven   = array();
			$tiny     = array();
			for ( $g = 1; $g <= $layout['groups']; $g++ ) {
				$size = count( Brackethive_Data::group_members( $tid, $g ) );
				if ( $size < 2 ) {
					$tiny[] = Brackethive_Data::group_name( $g ) . ' (' . $size . ')';
				} elseif ( $size !== $expected ) {
					$uneven[] = Brackethive_Data::group_name( $g ) . ' (' . $size . ')';
				}
			}
			if ( $tiny ) {
				echo '<div class="notice notice-error"><p>' . esc_html(
					sprintf(
						/* translators: %s: liste des poules */
						__( 'Incomplete groups: %s. A group must have at least 2 approved teams for its matches to exist; assign a group to each team from the Teams screen.', 'brackethive' ),
						implode( ', ', $tiny )
					)
				) . '</p></div>';
			}
			if ( $uneven ) {
				echo '<div class="notice notice-warning"><p>' . esc_html(
					sprintf(
						/* translators: 1: liste des poules, 2: effectif attendu */
						__( 'Unbalanced groups: %1$s (reference size: %2$d). Each group plays its own matches, but a larger group plays more of them; the number of qualified teams is limited by the smallest group.', 'brackethive' ),
						implode( ', ', $uneven ),
						$expected
					)
				) . '</p></div>';
			}
		}

		self::public_links( $tid, $settings );
		self::next_steps( $tid, $stats, $settings );

		// Aperçu du tableau.
		echo '<h2>' . esc_html__( 'Bracket preview', 'brackethive' ) . '</h2>';
		echo '<div class="brackethive-admin-bracket">';
		foreach ( $labels as $round => $round_label ) {
			if ( empty( $rounds[ $round ] ) ) {
				continue;
			}
			echo '<div class="brackethive-admin-round"><h3>' . esc_html( $round_label ) . '</h3>';
			foreach ( $rounds[ $round ] as $match ) {
				$n1 = (int) $match['team1_id'] ? Brackethive_Data::team_name( (int) $match['team1_id'], $teams ) : Brackethive_Data::source_label( $match['src1'] );
				$n2 = (int) $match['team2_id'] ? Brackethive_Data::team_name( (int) $match['team2_id'], $teams ) : Brackethive_Data::source_label( $match['src2'] );
				echo '<div class="brackethive-admin-match brackethive-admin-match--' . esc_attr( $match['status'] ) . '">';
				echo '<strong>' . esc_html( $match['code'] ) . '</strong> ';
				echo esc_html( $n1 ) . ' <em>vs</em> ' . esc_html( $n2 );
				if ( 'done' === $match['status'] ) {
					echo ' <span class="brackethive-admin-score">' . (int) $match['score1'] . ' - ' . (int) $match['score2'] . '</span>';
				}
				echo '</div>';
			}
			echo '</div>';
		}
		echo '</div>';

		// Outils.
		echo '<h2>' . esc_html__( 'Tools', 'brackethive' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'brackethive_tools' );
		echo '<input type="hidden" name="action" value="brackethive_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button" name="tool" value="rebuild">' . esc_html__( 'Regenerate the bracket', 'brackethive' ) . '</button> ';
		echo '<button class="button" name="tool" value="autoseed">' . esc_html__( 'Random draw for seeds', 'brackethive' ) . '</button> ';
		echo '<button class="button button-link-delete" name="tool" value="reset_results" onclick="return confirm(\'' . esc_js( __( 'Reset all results to zero? The teams are kept.', 'brackethive' ) ) . '\');">' . esc_html__( 'Reset all results', 'brackethive' ) . '</button>';
		echo '</form>';

		// Sauvegarde.
		echo '<h2>' . esc_html__( 'Backup', 'brackethive' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The backup only covers the selected tournament.', 'brackethive' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-inline-form">';
		wp_nonce_field( 'brackethive_export' );
		echo '<input type="hidden" name="action" value="brackethive_export" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button button-primary">' . esc_html__( 'Download a backup (JSON)', 'brackethive' ) . '</button>';
		echo '</form>';

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-box" style="max-width:720px">';
		wp_nonce_field( 'brackethive_import' );
		echo '<input type="hidden" name="action" value="brackethive_import" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p><label for="brackethive-backup"><strong>' . esc_html__( 'Restore a backup into this tournament', 'brackethive' ) . '</strong></label><br />';
		echo '<input type="file" id="brackethive-backup" name="backup" accept=".json,application/json" required /></p>';
		echo '<p><label><input type="checkbox" name="with_settings" value="1" /> ' . esc_html__( 'Also restore the tournament settings', 'brackethive' ) . '</label></p>';
		echo '<p><button class="button" onclick="return confirm(\'' . esc_js( __( 'Replace this tournament\'s data with the contents of the file?', 'brackethive' ) ) . '\');">' . esc_html__( 'Restore', 'brackethive' ) . '</button></p>';
		echo '</form>';

		// Duplication.
		echo '<h2>' . esc_html__( 'Duplicate this tournament', 'brackethive' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-box" style="max-width:640px">';
		wp_nonce_field( 'brackethive_duplicate_tournament' );
		echo '<input type="hidden" name="action" value="brackethive_duplicate_tournament" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p class="description">' . esc_html__( 'Carries over the settings and the format, without the results. Ideal for preparing the next edition.', 'brackethive' ) . '</p>';
		echo '<p><label>' . esc_html__( 'Name of the new tournament', 'brackethive' ) . ' *<br /><input type="text" class="regular-text" name="title" required /></label></p>';
		echo '<p><label><input type="checkbox" name="with_teams" value="1" /> ' . esc_html__( 'Also carry over the teams (pending approval, without a seed)', 'brackethive' ) . '</label></p>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Duplicate', 'brackethive' ) . '</button></p>';
		echo '</form>';

		// Shortcodes.
		echo '<h2>' . esc_html__( 'Shortcodes', 'brackethive' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The tournament page already shows everything automatically: these shortcodes are only useful if you want the same content elsewhere on the site.', 'brackethive' ) . '</p>';
		echo '<p class="description"><strong>' . esc_html__( 'How to use them:', 'brackethive' ) . '</strong> ' . esc_html__( 'create a WordPress page (Pages › Add New), paste the shortcode you want into it, then click Publish. The tournament content will appear on that page, and will update by itself during the event.', 'brackethive' ) . '</p>';
		echo '<table class="widefat striped brackethive-shortcodes"><thead><tr><th>' . esc_html__( 'Shortcode', 'brackethive' ) . '</th><th>' . esc_html__( 'Display', 'brackethive' ) . '</th></tr></thead><tbody>';
		$slug         = get_post_field( 'post_name', $tid );
		$descriptions = array(
			'brackethive_tournois'     => __( 'List of all tournaments on the site', 'brackethive' ),
			'brackethive_tournoi'      => __( 'Full tabbed page', 'brackethive' ),
			'brackethive_tableau'      => __( 'Single-elimination bracket', 'brackethive' ),
			'brackethive_planning'     => __( 'Time schedule', 'brackethive' ),
			'brackethive_resultats'    => __( 'Results sheet', 'brackethive' ),
			'brackethive_classement'   => __( 'Overall ranking', 'brackethive' ),
			'brackethive_poules'       => __( 'Group standings', 'brackethive' ),
			'brackethive_equipes'      => __( 'Participating teams', 'brackethive' ),
			'brackethive_reglement'    => __( 'Rules', 'brackethive' ),
			'brackethive_organisation' => __( 'Staff needed', 'brackethive' ),
			'brackethive_checklist'    => __( 'Pre-opening checklist', 'brackethive' ),
			'brackethive_inscription'  => __( 'Sign-up form (simple="yes": Team, Nickname, Email, Phone)', 'brackethive' ),
		);
		foreach ( $descriptions as $tag => $desc ) {
			$example = 'brackethive_tournois' === $tag ? '[' . $tag . ']' : '[' . $tag . ' tournoi="' . $slug . '"]';
			echo '<tr><td><code>' . esc_html( $example ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Without the "tournoi" attribute, the shortcode shows the site\'s default tournament. A page containing a shortcode stays invisible to visitors until it is published.', 'brackethive' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( add_query_arg( 'post_type', 'page', admin_url( 'post-new.php' ) ) ) . '">' . esc_html__( 'Create a page now', 'brackethive' ) . '</a></p>';

		echo '</div>';
	}

	/**
	 * Gestion des équipes.
	 */
	public static function page_teams() {
		$tid = Brackethive_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Teams', 'brackethive' ) );
			return;
		}

		$max     = (int) Brackethive_Tournament::get( $tid, 'team_count' );
		$max     = $max > 0 ? $max : 16;
		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.
		$editing = $edit_id ? Brackethive_Data::get_team( $edit_id ) : null;

		if ( $editing && (int) $editing['tournament_id'] !== $tid ) {
			$editing = null;
		}

		$teams = Brackethive_Data::get_teams( array( 'tournament_id' => $tid ) );

		$status_labels = array(
			'pending'  => __( 'Pending', 'brackethive' ),
			'active'   => __( 'Approved', 'brackethive' ),
			'rejected' => __( 'Rejected', 'brackethive' ),
		);

		echo '<div class="wrap brackethive-admin">';
		echo '<h1>' . esc_html__( 'Teams', 'brackethive' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'brackethive-teams' );

		// Formulaire.
		echo '<div class="brackethive-teams-form" id="brackethive-team-form">';
		echo '<h2>' . ( $editing ? esc_html__( 'Edit team', 'brackethive' ) : esc_html__( 'Add a team', 'brackethive' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-box brackethive-team-form">';
		wp_nonce_field( 'brackethive_save_team' );
		echo '<input type="hidden" name="action" value="brackethive_save_team" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<input type="hidden" name="id" value="' . ( $editing ? (int) $editing['id'] : 0 ) . '" />';

		$val = function ( $key ) use ( $editing ) {
			return $editing && isset( $editing[ $key ] ) ? $editing[ $key ] : '';
		};

		echo '<p><label>' . esc_html__( 'Team name', 'brackethive' ) . ' *<br /><input type="text" class="regular-text" name="name" required value="' . esc_attr( $val( 'name' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Tag', 'brackethive' ) . '<br /><input type="text" class="regular-text" name="tag" value="' . esc_attr( $val( 'tag' ) ) . '" /></label></p>';

		echo '<p><label>' . esc_html(
			sprintf(
				/* translators: %d: nombre d'équipes */
				__( 'Seed in the bracket (1 to %d)', 'brackethive' ),
				$max
			)
		) . '<br /><select name="seed">';
		$current_seed = $editing ? (int) $editing['seed'] : 0;
		echo '<option value="0"' . selected( $current_seed, 0, false ) . '>' . esc_html__( '— unseeded —', 'brackethive' ) . '</option>';
		for ( $i = 1; $i <= $max; $i++ ) {
			echo '<option value="' . (int) $i . '"' . selected( $current_seed, $i, false ) . '>' . esc_html( $i ) . '</option>';
		}
		echo '</select></label></p>';

		if ( 'groups' === Brackethive_Tournament::get( $tid, 'format' ) && 'manual' === Brackethive_Tournament::get( $tid, 'group_mode' ) ) {
			$group_count = (int) Brackethive_Tournament::get( $tid, 'group_count' );
			echo '<p><label>' . esc_html__( 'Group', 'brackethive' ) . '<br /><select name="group_id">';
			echo '<option value="0"' . selected( (int) $val( 'group_id' ), 0, false ) . '>' . esc_html__( '— unassigned —', 'brackethive' ) . '</option>';
			for ( $g = 1; $g <= $group_count; $g++ ) {
				echo '<option value="' . (int) $g . '"' . selected( (int) $val( 'group_id' ), $g, false ) . '>' . esc_html( Brackethive_Data::group_name( $g ) ) . '</option>';
			}
			echo '</select></label></p>';
		}

		echo '<p><label>' . esc_html__( 'Captain', 'brackethive' ) . '<br /><input type="text" class="regular-text" name="captain" value="' . esc_attr( $val( 'captain' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Email', 'brackethive' ) . '<br /><input type="email" class="regular-text" name="email" value="' . esc_attr( $val( 'email' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Phone', 'brackethive' ) . '<br /><input type="text" class="regular-text" name="phone" value="' . esc_attr( $val( 'phone' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Players (one per line)', 'brackethive' ) . '<br /><textarea name="players" rows="5" class="large-text">' . esc_textarea( $val( 'players' ) ) . '</textarea></label></p>';
		echo '<p><label>' . esc_html__( 'Internal notes', 'brackethive' ) . '<br /><textarea name="notes" rows="3" class="large-text">' . esc_textarea( $val( 'notes' ) ) . '</textarea></label></p>';

		echo '<p><label>' . esc_html__( 'Status', 'brackethive' ) . '<br /><select name="status">';
		$current_status = $editing ? $editing['status'] : 'active';
		foreach ( $status_labels as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current_status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><br /><span class="description">' . esc_html__( 'Only approved teams appear in the bracket.', 'brackethive' ) . '</span></label></p>';

		echo '<p><button class="button button-primary">' . esc_html__( 'Save', 'brackethive' ) . '</button> ';
		if ( $editing ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'brackethive-teams', 'tournament' => $tid ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Cancel', 'brackethive' ) . '</a>';
		}
		echo '</p>';
		echo '</form>';
		echo '</div>';


		// Liste.
		echo '<div class="brackethive-teams-list">';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-inline-form">';
		wp_nonce_field( 'brackethive_tools' );
		echo '<input type="hidden" name="action" value="brackethive_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button" name="tool" value="validate_all">' . esc_html__( 'Approve pending sign-ups', 'brackethive' ) . '</button> ';
		echo '<button class="button" name="tool" value="autoseed">' . esc_html__( 'Random draw for the free seeds', 'brackethive' ) . '</button>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Seed', 'brackethive' ) . '</th>';
		echo '<th>' . esc_html__( 'Team', 'brackethive' ) . '</th>';
		echo '<th>' . esc_html__( 'Captain', 'brackethive' ) . '</th>';
		echo '<th>' . esc_html__( 'Contact', 'brackethive' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'brackethive' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'brackethive' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $teams ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No team in this tournament.', 'brackethive' ) . '</td></tr>';
		}

		foreach ( $teams as $team ) {
			echo '<tr>';
			echo '<td>' . ( (int) $team['seed'] ? esc_html( (int) $team['seed'] ) : '—' ) . '</td>';
			echo '<td><strong>' . esc_html( $team['name'] ) . '</strong>' . ( '' !== $team['tag'] ? ' <span class="brackethive-tag">[' . esc_html( $team['tag'] ) . ']</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $team['captain'] ) . '</td>';
			echo '<td>' . esc_html( trim( $team['email'] . ' ' . $team['phone'] ) ) . '</td>';
			echo '<td><span class="brackethive-status brackethive-status--' . esc_attr( $team['status'] ) . '">' . esc_html( isset( $status_labels[ $team['status'] ] ) ? $status_labels[ $team['status'] ] : $team['status'] ) . '</span></td>';
			echo '<td>';
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'brackethive-teams', 'tournament' => $tid, 'edit' => (int) $team['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edit', 'brackethive' ) . '</a> ';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'brackethive_delete_team' );
			echo '<input type="hidden" name="action" value="brackethive_delete_team" />';
			echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
			echo '<input type="hidden" name="id" value="' . (int) $team['id'] . '" />';
			echo '<button class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Delete this team?', 'brackethive' ) ) . '\');">' . esc_html__( 'Delete', 'brackethive' ) . '</button>';
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
		$tid = Brackethive_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Matches & scores', 'brackethive' ) );
			return;
		}

		$settings = Brackethive_Tournament::settings( $tid );
		$rounds   = Brackethive_Data::get_matches_by_round( $tid );
		$teams    = Brackethive_Data::get_teams_map( $tid );
		$labels   = Brackethive_Bracket::round_order( $settings );
		$games    = Brackethive_Data::get_all_games( $tid );

		echo '<div class="wrap brackethive-admin">';
		echo '<h1>' . esc_html__( 'Matches & scores', 'brackethive' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'brackethive-matches' );
		echo '<p class="description">' . esc_html__( 'Confirm a match to send the winner to the next round automatically. Editing a result already confirmed resets the following matches involved.', 'brackethive' ) . '</p>';

		foreach ( $labels as $round => $round_label ) {
			if ( empty( $rounds[ $round ] ) ) {
				continue;
			}

			echo '<h2>' . esc_html( $round_label ) . '</h2>';

			foreach ( $rounds[ $round ] as $match ) {
				$id    = (int) $match['id'];
				$t1    = (int) $match['team1_id'];
				$t2    = (int) $match['team2_id'];
				$n1    = $t1 ? Brackethive_Data::team_name( $t1, $teams ) : Brackethive_Data::source_label( $match['src1'] );
				$n2    = $t2 ? Brackethive_Data::team_name( $t2, $teams ) : Brackethive_Data::source_label( $match['src2'] );
				$bo    = (int) $match['bo'];
				$multi = $bo > 1;
				$mg    = isset( $games[ $id ] ) ? $games[ $id ] : array();

				echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-match-form brackethive-match-form--' . esc_attr( $match['status'] ) . '">';
				wp_nonce_field( 'brackethive_save_result' );
				echo '<input type="hidden" name="action" value="brackethive_save_result" />';
				echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
				echo '<input type="hidden" name="match_id" value="' . (int) $id . '" />';

				echo '<div class="brackethive-match-form__head">';
				echo '<span class="brackethive-code">' . esc_html( $match['code'] ) . '</span> ';
				echo '<span class="brackethive-vs"><strong>' . esc_html( $n1 ) . '</strong> vs <strong>' . esc_html( $n2 ) . '</strong></span> ';
				echo '<span class="brackethive-bo">BO' . (int) $bo . '</span>';
				echo '</div>';

				echo '<div class="brackethive-match-form__body">';

				if ( $multi ) {
					echo '<div class="brackethive-games">';
					for ( $g = 0; $g < $bo; $g++ ) {
						$s1 = isset( $mg[ $g ] ) ? (int) $mg[ $g ]['score1'] : '';
						$s2 = isset( $mg[ $g ] ) ? (int) $mg[ $g ]['score2'] : '';
						$mp = isset( $mg[ $g ] ) ? $mg[ $g ]['map'] : '';
						echo '<span class="brackethive-game">';
						/* translators: %d: numéro de manche */
						echo '<label>' . esc_html( sprintf( __( 'Game %d', 'brackethive' ), $g + 1 ) ) . '</label>';
						echo '<input type="number" min="0" name="games[' . (int) $g . '][score1]" value="' . esc_attr( $s1 ) . '" /> - ';
						echo '<input type="number" min="0" name="games[' . (int) $g . '][score2]" value="' . esc_attr( $s2 ) . '" />';
						echo '<input type="text" class="brackethive-map" name="games[' . (int) $g . '][map]" value="' . esc_attr( $mp ) . '" placeholder="' . esc_attr__( 'Map / mode', 'brackethive' ) . '" />';
						echo '</span>';
					}
					echo '</div>';
					echo '<p class="description">' . esc_html(
						sprintf(
							/* translators: 1: format BO, 2: manches à gagner */
							__( 'Leave unplayed games empty. In BO%1$d, the winner must win %2$d of them.', 'brackethive' ),
							$bo,
							(int) floor( $bo / 2 ) + 1
						)
					) . '</p>';
				} else {
					echo '<span class="brackethive-scores">';
					echo '<label>' . esc_html__( 'Score', 'brackethive' ) . '</label>';
					echo '<input type="number" min="0" name="score1" value="' . esc_attr( (int) $match['score1'] ) . '" /> - ';
					echo '<input type="number" min="0" name="score2" value="' . esc_attr( (int) $match['score2'] ) . '" />';
					echo '</span>';
				}

				echo '<span class="brackethive-field-inline"><label>' . esc_html__( 'Stations', 'brackethive' ) . '</label><input type="text" size="5" name="stations" value="' . esc_attr( $match['stations'] ) . '" /></span>';
				echo '<span class="brackethive-field-inline"><label>' . esc_html__( 'Start', 'brackethive' ) . '</label><input type="text" size="5" name="start_time" value="' . esc_attr( $match['start_time'] ) . '" /></span>';
				echo '<span class="brackethive-field-inline"><label>' . esc_html__( 'Referee', 'brackethive' ) . '</label><input type="text" name="referee" value="' . esc_attr( $match['referee'] ) . '" /></span>';

				echo '<span class="brackethive-field-inline"><label>' . esc_html__( 'Status', 'brackethive' ) . '</label><select name="status">';
				foreach ( array( 'pending', 'live', 'done' ) as $status ) {
					echo '<option value="' . esc_attr( $status ) . '"' . selected( $match['status'], $status, false ) . '>' . esc_html( Brackethive_Render::status_label( $status ) ) . '</option>';
				}
				echo '</select></span>';

				echo '<button class="button button-primary">' . esc_html__( 'Save', 'brackethive' ) . '</button>';
				echo '</div>';
				echo '</form>';
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-inline-form">';
		wp_nonce_field( 'brackethive_tools' );
		echo '<input type="hidden" name="action" value="brackethive_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button button-link-delete" name="tool" value="reset_results" onclick="return confirm(\'' . esc_js( __( 'Reset every result to zero?', 'brackethive' ) ) . '\');">' . esc_html__( 'Reset all results', 'brackethive' ) . '</button>';
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Planning + feuille de scores imprimable.
	 */
	public static function page_planning() {
		$tid = Brackethive_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Schedule', 'brackethive' ) );
			return;
		}

		echo '<div class="wrap brackethive-admin">';
		echo '<h1>' . esc_html__( 'Schedule & score sheet', 'brackethive' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'brackethive-planning' );
		echo '<p><button class="button" onclick="window.print();return false;">' . esc_html__( 'Print', 'brackethive' ) . '</button></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-inline-form">';
		wp_nonce_field( 'brackethive_tools' );
		echo '<input type="hidden" name="action" value="brackethive_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button" name="tool" value="reschedule" onclick="return confirm(\'' . esc_js( __( 'Recalculate every time and station from the settings? Manual adjustments will be lost.', 'brackethive' ) ) . '\');">' . esc_html__( 'Recalculate the schedule', 'brackethive' ) . '</button>';
		echo '</form>';

		echo '<div class="brackethive-print">';
		echo Brackethive_Render::view( 'planning', array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendu HTML complet, échappement effectué dans les gabarits de Brackethive_Render.

		$matches = Brackethive_Data::get_matches( $tid );
		$teams   = Brackethive_Data::get_teams_map( $tid );

		echo '<h2>' . esc_html__( 'Score sheet', 'brackethive' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Match', 'brackethive' ) . '</th><th>' . esc_html__( 'Team', 'brackethive' ) . '</th><th>' . esc_html__( 'Score', 'brackethive' ) . '</th><th>' . esc_html__( 'Team', 'brackethive' ) . '</th><th>' . esc_html__( 'Score', 'brackethive' ) . '</th><th>' . esc_html__( 'Referee', 'brackethive' ) . '</th></tr></thead><tbody>';
		foreach ( $matches as $match ) {
			$n1 = (int) $match['team1_id'] ? Brackethive_Data::team_name( (int) $match['team1_id'], $teams ) : Brackethive_Data::source_label( $match['src1'] );
			$n2 = (int) $match['team2_id'] ? Brackethive_Data::team_name( (int) $match['team2_id'], $teams ) : Brackethive_Data::source_label( $match['src2'] );
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

		echo '<h2>' . esc_html__( 'Pre-opening checklist', 'brackethive' ) . '</h2>';
		echo Brackethive_Render::view( 'checklist', array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendu HTML complet, échappement effectué dans les gabarits de Brackethive_Render.
		echo '<h2>' . esc_html__( 'Staff', 'brackethive' ) . '</h2>';
		echo Brackethive_Render::view( 'staff', array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendu HTML complet, échappement effectué dans les gabarits de Brackethive_Render.
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Aperçu du rendu public.
	 */
	public static function page_preview() {
		$tid = Brackethive_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Public preview', 'brackethive' ) );
			return;
		}

		$views = array(
			'full'         => __( 'Full page (tabs)', 'brackethive' ),
			'bracket'      => __( 'Bracket', 'brackethive' ),
			'planning'     => __( 'Schedule', 'brackethive' ),
			'results'      => __( 'Results', 'brackethive' ),
			'ranking'      => __( 'Ranking', 'brackethive' ),
			'teams'        => __( 'Teams', 'brackethive' ),
			'rules'        => __( 'Rules', 'brackethive' ),
			'registration' => __( 'Sign-up', 'brackethive' ),
			'list'         => __( 'Tournament list', 'brackethive' ),
		);

		$widths = array(
			'desktop' => array( __( 'Desktop', 'brackethive' ), '100%' ),
			'tablet'  => array( __( 'Tablet', 'brackethive' ), '768px' ),
			'mobile'  => array( __( 'Mobile', 'brackethive' ), '390px' ),
		);

		$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'full'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.
		$width = isset( $_GET['w'] ) ? sanitize_key( wp_unslash( $_GET['w'] ) ) : 'desktop'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.

		if ( ! isset( $views[ $view ] ) ) {
			$view = 'full';
		}
		if ( ! isset( $widths[ $width ] ) ) {
			$width = 'desktop';
		}

		$base = add_query_arg( array( 'page' => 'brackethive-preview', 'tournament' => $tid ), admin_url( 'admin.php' ) );

		echo '<div class="wrap brackethive-admin">';
		echo '<h1>' . esc_html__( 'Public preview', 'brackethive' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'brackethive-preview' );
		echo '<p class="description">' . esc_html__( 'Exactly what visitors will see. The theme wrapper (menu, footer) is not reproduced here.', 'brackethive' ) . '</p>';

		echo '<div class="brackethive-preview-bar">';

		echo '<span class="brackethive-preview-group"><strong>' . esc_html__( 'Displayed view', 'brackethive' ) . '</strong>';
		foreach ( $views as $key => $label ) {
			$url = add_query_arg( array( 'view' => $key, 'w' => $width ), $base );
			echo '<a class="button button-small' . ( $key === $view ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
		}
		echo '</span>';

		echo '<span class="brackethive-preview-group"><strong>' . esc_html__( 'Width', 'brackethive' ) . '</strong>';
		foreach ( $widths as $key => $def ) {
			$url = add_query_arg( array( 'view' => $view, 'w' => $key ), $base );
			echo '<a class="button button-small' . ( $key === $width ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $def[0] ) . '</a> ';
		}
		echo '</span>';

		echo '</div>';

		$frame_url = wp_nonce_url(
			add_query_arg(
				array(
					'action'     => 'brackethive_preview_frame',
					'view'       => $view,
					'tournament' => $tid,
				),
				admin_url( 'admin-post.php' )
			),
			'brackethive_preview_frame'
		);

		echo '<div class="brackethive-preview-stage">';
		echo '<iframe id="brackethive-preview-frame" class="brackethive-preview-frame brackethive-preview-frame--' . esc_attr( $width ) . '" ';
		echo 'src="' . esc_url( $frame_url ) . '" ';
		echo 'style="width:100%;max-width:' . esc_attr( $widths[ $width ][1] ) . '" ';
		echo 'title="' . esc_attr__( 'Preview of the public display', 'brackethive' ) . '" loading="lazy"></iframe>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Réglages propres au tournoi.
	 */
	public static function page_tournament() {
		$tid = Brackethive_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Tournament settings', 'brackethive' ) );
			return;
		}

		$s = Brackethive_Tournament::settings( $tid );

		echo '<div class="wrap brackethive-admin">';
		echo '<h1>' . esc_html__( 'Tournament settings', 'brackethive' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'brackethive-tournament' );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'brackethive_save_tournament' );
		echo '<input type="hidden" name="action" value="brackethive_save_tournament" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<input type="hidden" name="brackethive_checkbox_scope" value="third_place,bracket_reset,show_rules,show_staff,registration_open,auto_content,public_page" />';

		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Name', 'brackethive' ) . '</th><td>';
		echo '<strong>' . esc_html( $s['tournament_name'] ) . '</strong> ';
		echo '<a class="button button-small" href="' . esc_url( get_edit_post_link( $tid ) ) . '">' . esc_html__( 'Rename', 'brackethive' ) . '</a>';
		echo '<p class="description">' . esc_html__( 'The name and the page address are changed from the tournament page.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		$text_fields = array(
			'game_name' => __( 'Game', 'brackethive' ),
			'subtitle'  => __( 'Subtitle', 'brackethive' ),
			'venue'     => __( 'Venue', 'brackethive' ),
		);
		foreach ( $text_fields as $key => $label ) {
			echo '<tr><th scope="row"><label for="brackethive-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input type="text" class="regular-text" id="brackethive-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '" /></td></tr>';
		}

		echo '<tr><th scope="row"><label for="brackethive-event_date">' . esc_html__( 'Date', 'brackethive' ) . '</label></th>';
		echo '<td><input type="date" id="brackethive-event_date" name="event_date" value="' . esc_attr( $s['event_date'] ) . '" /></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Times', 'brackethive' ) . '</th><td>';
		echo '<label>' . esc_html__( 'Start', 'brackethive' ) . ' <input type="time" name="start_time" value="' . esc_attr( $s['start_time'] ) . '" /></label> ';
		echo '<label>' . esc_html__( 'Expected end time', 'brackethive' ) . ' <input type="time" name="end_time" value="' . esc_attr( $s['end_time'] ) . '" /></label>';
		echo '<p class="description">' . esc_html__( 'Changing the start time shifts the whole schedule automatically.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Gaming stations', 'brackethive' ) . '</th>';
		echo '<td><input type="number" min="1" name="stations" value="' . esc_attr( (int) $s['stations'] ) . '" class="small-text" /></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Players per team', 'brackethive' ) . '</th>';
		echo '<td><input type="number" min="1" name="players_per_team" value="' . esc_attr( (int) $s['players_per_team'] ) . '" class="small-text" /></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Accent color', 'brackethive' ) . '</th>';
		echo '<td><input type="color" name="accent_color" value="' . esc_attr( $s['accent_color'] ) . '" /></td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Format', 'brackethive' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		$formats = array(
			'single' => __( 'Single elimination', 'brackethive' ),
			'double' => __( 'Double elimination (with losers bracket)', 'brackethive' ),
			'groups' => __( 'Group stage then playoffs', 'brackethive' ),
		);

		echo '<tr><th scope="row">' . esc_html__( 'Tournament format', 'brackethive' ) . '</th><td>';
		echo '<select name="format">';
		foreach ( $formats as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $s['format'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'In double elimination, a team is only eliminated after two losses. In groups, each team meets every other team of its group before the final phase.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Number of teams', 'brackethive' ) . '</th><td>';
		echo '<input type="number" min="2" max="64" name="team_count" value="' . esc_attr( (int) $s['team_count'] ) . '" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'From 2 to 64. If the number is not a power of two, the top seeds get a bye in the first round.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		echo '<tr class="brackethive-if-groups"><th scope="row">' . esc_html__( 'Groups', 'brackethive' ) . '</th><td>';
		echo '<label>' . esc_html__( 'Number of groups', 'brackethive' ) . ' <input type="number" min="2" max="8" name="group_count" value="' . esc_attr( (int) $s['group_count'] ) . '" class="small-text" /></label> ';
		echo '<label>' . esc_html__( 'Qualifiers per group', 'brackethive' ) . ' <input type="number" min="1" max="4" name="qualifiers_per_group" value="' . esc_attr( (int) $s['qualifiers_per_group'] ) . '" class="small-text" /></label><br /><br />';
		echo '<label>' . esc_html__( 'Make-up', 'brackethive' ) . ' <select name="group_mode">';
		echo '<option value="auto"' . selected( $s['group_mode'], 'auto', false ) . '>' . esc_html__( 'Automatic (snake order by seed)', 'brackethive' ) . '</option>';
		echo '<option value="manual"' . selected( $s['group_mode'], 'manual', false ) . '>' . esc_html__( 'Manual (group chosen for each team)', 'brackethive' ) . '</option>';
		echo '</select></label>';
		echo '<p class="description">' . esc_html__( 'With manual make-up, the group is chosen on each team\'s record. Qualified teams are crossed between groups either way.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		$bo_rounds = array(
			'bo_group' => __( 'Groups', 'brackethive' ),
			'bo_r64'   => __( 'R64', 'brackethive' ),
			'bo_r32'   => __( 'R32', 'brackethive' ),
			'bo_r16'   => __( 'R16', 'brackethive' ),
			'bo_qf'    => __( 'Quarters', 'brackethive' ),
			'bo_sf'    => __( 'Semis', 'brackethive' ),
			'bo_final' => __( 'Final', 'brackethive' ),
			'bo_lb'    => __( 'Losers bracket', 'brackethive' ),
		);

		echo '<tr><th scope="row">' . esc_html__( 'Number of games', 'brackethive' ) . '</th><td>';
		foreach ( $bo_rounds as $key => $label ) {
			echo '<label style="display:inline-block;margin:0 18px 8px 0">' . esc_html( $label ) . ' <select name="' . esc_attr( $key ) . '">';
			foreach ( array( 1, 3, 5 ) as $bo ) {
				echo '<option value="' . (int) $bo . '"' . selected( (int) $s[ $key ], $bo, false ) . '>BO' . (int) $bo . '</option>';
			}
			echo '</select></label>';
		}
		echo '<p class="description">' . esc_html__( 'Only the rounds actually present in the chosen format are used.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Third place match', 'brackethive' ) . '</th><td>';
		echo '<label><input type="checkbox" name="third_place" value="1"' . checked( $s['third_place'], 1, false ) . ' /> ' . esc_html__( 'Pit the two semi-final losers against each other', 'brackethive' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Not applicable in double elimination: the loser of the losers bracket final is third.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Bracket reset', 'brackethive' ) . '</th><td>';
		echo '<label><input type="checkbox" name="bracket_reset" value="1"' . checked( $s['bracket_reset'], 1, false ) . ' /> ' . esc_html__( 'Replay the final if the team coming from the losers bracket wins it (« bracket reset »)', 'brackethive' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Strict double elimination rule: the team coming from the losers bracket already has one loss, so it must inflict two. The match is only played if needed.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Schedule', 'brackethive' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Time calculation', 'brackethive' ) . '</th><td>';
		echo '<label>' . esc_html__( 'Match length (min)', 'brackethive' ) . ' <input type="number" min="5" name="match_duration" value="' . esc_attr( (int) $s['match_duration'] ) . '" class="small-text" /></label> ';
		echo '<label>' . esc_html__( 'Simultaneous matches', 'brackethive' ) . ' <input type="number" min="1" name="matches_parallel" value="' . esc_attr( (int) $s['matches_parallel'] ) . '" class="small-text" /></label><br /><br />';
		echo '<label>' . esc_html__( 'Welcome time before the 1st match (min)', 'brackethive' ) . ' <input type="number" min="0" name="warmup_minutes" value="' . esc_attr( (int) $s['warmup_minutes'] ) . '" class="small-text" /></label> ';
		echo '<label>' . esc_html__( 'Break between rounds (min)', 'brackethive' ) . ' <input type="number" min="0" name="break_minutes" value="' . esc_attr( (int) $s['break_minutes'] ) . '" class="small-text" /></label>';
		echo '<p class="description">' . esc_html__( 'A BO3 counts as two slots, a BO5 as three. Times already set are not overwritten: use « Recalculate the schedule » below to regenerate them.', 'brackethive' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Display', 'brackethive' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Tabs', 'brackethive' ) . '</th><td>';
		echo '<label><input type="checkbox" name="show_rules" value="1"' . checked( $s['show_rules'], 1, false ) . ' /> ' . esc_html__( 'Rules', 'brackethive' ) . '</label><br />';
		echo '<label><input type="checkbox" name="show_staff" value="1"' . checked( $s['show_staff'], 1, false ) . ' /> ' . esc_html__( 'Organisation (staff)', 'brackethive' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Tournament page', 'brackethive' ) . '</th><td>';
		echo '<label><input type="checkbox" name="public_page" value="1"' . checked( $s['public_page'], 1, false ) . ' /> ' . esc_html__( 'Dedicated public page', 'brackethive' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Unchecked, the tournament page answers « not found » to visitors; the tournament is then shown only by the shortcodes placed on your own pages.', 'brackethive' ) . '</p>';
		echo '<label><input type="checkbox" name="auto_content" value="1"' . checked( $s['auto_content'], 1, false ) . ' /> ' . esc_html__( 'Show the tournament automatically on its page', 'brackethive' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Uncheck if you prefer to build the page yourself with the shortcodes.', 'brackethive' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Sign-ups', 'brackethive' ) . '</h2>';
		echo '<table class="form-table"><tbody><tr><th scope="row">' . esc_html__( 'Public form', 'brackethive' ) . '</th><td>';
		echo '<label><input type="checkbox" name="registration_open" value="1"' . checked( $s['registration_open'], 1, false ) . ' /> ' . esc_html__( 'Open sign-ups for this tournament', 'brackethive' ) . '</label><br /><br />';
		echo '<label>' . esc_html__( 'Maximum number of teams', 'brackethive' ) . ' <input type="number" min="0" name="registration_max" value="' . esc_attr( (int) $s['registration_max'] ) . '" class="small-text" /></label><br /><br />';
		echo '<label>' . esc_html__( 'Notification email', 'brackethive' ) . ' <input type="email" class="regular-text" name="notify_email" value="' . esc_attr( $s['notify_email'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" /></label>';
		echo '<p class="description">' . esc_html__( 'Notified on every sign-up. Empty: the site administration address. The captain gets an acknowledgement of his own.', 'brackethive' ) . '</p><br />';
		echo '<label>' . esc_html__( 'Confirmation message', 'brackethive' ) . '<br /><textarea name="registration_msg" rows="3" class="large-text">' . esc_textarea( $s['registration_msg'] ) . '</textarea></label>';
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
		$s   = Brackethive_Settings::all();
		$all = Brackethive_Tournament::all( array( 'post_status' => 'publish' ) );

		echo '<div class="wrap brackethive-admin">';
		echo '<h1>' . esc_html__( 'Plugin settings', 'brackethive' ) . '</h1>';
		self::notice();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'brackethive_save_settings' );
		echo '<input type="hidden" name="action" value="brackethive_save_settings" />';
		echo '<input type="hidden" name="brackethive_checkbox_scope" value="hide_page_title,delete_data_on_uninstall" />';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="brackethive-plugin_locale">' . esc_html__( 'Language', 'brackethive' ) . '</label></th><td>';
		echo '<select id="brackethive-plugin_locale" name="plugin_locale">';
		echo '<option value=""' . selected( $s['plugin_locale'], '', false ) . '>' . esc_html__( 'Same as the site', 'brackethive' ) . '</option>';
		echo '<option value="en_US"' . selected( $s['plugin_locale'], 'en_US', false ) . '>' . esc_html__( 'English', 'brackethive' ) . '</option>';
		foreach ( brackethive_available_locales() as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $s['plugin_locale'], $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Language of the tournament screens and of the public page. Choose "Same as the site" to follow the site language.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Default tournament', 'brackethive' ) . '</th><td>';
		if ( empty( $all ) ) {
			echo '<em>' . esc_html__( 'No published tournament.', 'brackethive' ) . '</em>';
		} else {
			$default = (int) get_option( 'brackethive_default_tournament' );
			echo '<select name="default_tournament">';
			echo '<option value="0">' . esc_html__( '— the most recent —', 'brackethive' ) . '</option>';
			foreach ( $all as $post ) {
				echo '<option value="' . (int) $post->ID . '"' . selected( $default, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Used by the shortcodes without a « tournoi » attribute.', 'brackethive' ) . '</p>';
		}
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Auto refresh', 'brackethive' ) . '</th>';
		echo '<td><input type="number" min="0" name="refresh_interval" value="' . esc_attr( (int) $s['refresh_interval'] ) . '" class="small-text" /> ' . esc_html__( 'seconds (0 to disable)', 'brackethive' ) . '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Page title', 'brackethive' ) . '</th><td>';
		echo '<label><input type="checkbox" name="hide_page_title" value="1"' . checked( $s['hide_page_title'], 1, false ) . ' /> ' . esc_html__( 'Hide the theme title banner on tournament pages', 'brackethive' ) . '</label>';
		echo '<p><label>' . esc_html__( 'Additional CSS selectors', 'brackethive' ) . '<br />';
		echo '<input type="text" class="large-text code" name="hide_title_selector" value="' . esc_attr( $s['hide_title_selector'] ) . '" placeholder=".mon-theme-titre, #header-page" /></label></p>';
		echo '</td></tr>';

		if ( self::has_updater() ) {
			echo '<tr><th scope="row"><label for="brackethive-update_manifest_url">' . esc_html__( 'Update manifest URL', 'brackethive' ) . '</label></th><td>';
			echo '<input type="url" class="large-text code" id="brackethive-update_manifest_url" name="update_manifest_url" value="' . esc_attr( $s['update_manifest_url'] ) . '" placeholder="https://exemple.fr/maj/brackethive.json" />';
			echo '<p class="description">' . esc_html__( 'Leave empty to disable automatic updates.', 'brackethive' ) . '</p>';

			$manifest = Brackethive_Updater::manifest();
			if ( $manifest ) {
				if ( version_compare( $manifest['version'], BRACKETHIVE_VERSION, '>' ) ) {
					echo '<p><strong>' . esc_html(
						sprintf(
							/* translators: 1: version disponible, 2: version installée */
							__( 'Version %1$s is available (you are running %2$s).', 'brackethive' ),
							$manifest['version'],
							BRACKETHIVE_VERSION
						)
					) . '</strong></p>';
				} else {
					echo '<p>' . esc_html(
						sprintf(
							/* translators: %s: version installée */
							__( 'Up to date (version %s).', 'brackethive' ),
							BRACKETHIVE_VERSION
						)
					) . '</p>';
				}
			}
			echo '</td></tr>';
		}

		echo '<tr><th scope="row">' . esc_html__( 'Data on uninstall', 'brackethive' ) . '</th><td>';
		echo '<label><input type="checkbox" name="delete_data_on_uninstall" value="1"' . checked( $s['delete_data_on_uninstall'], 1, false ) . ' /> ' . esc_html__( 'Delete everything if the plugin is uninstalled', 'brackethive' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Unchecked (recommended), tournaments and their data are kept.', 'brackethive' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button();
		echo '</form>';

		if ( self::has_updater() ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-inline-form">';
			wp_nonce_field( 'brackethive_check_update' );
			echo '<input type="hidden" name="action" value="brackethive_check_update" />';
			echo '<button class="button">' . esc_html__( 'Check for updates now', 'brackethive' ) . '</button>';
			echo '</form>';
		}

		echo '<h2>' . esc_html__( 'Emails', 'brackethive' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Every public sign-up sends an acknowledgement to the captain and an alert to the tournament notification address (failing that, the site administration address). Delivery depends on your hosting: test it here.', 'brackethive' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="brackethive-inline-form">';
		wp_nonce_field( 'brackethive_test_mail' );
		echo '<input type="hidden" name="action" value="brackethive_test_mail" />';
		echo '<label>' . esc_html__( 'Send a test email to', 'brackethive' ) . ' <input type="email" name="to" class="regular-text" value="' . esc_attr( get_option( 'admin_email' ) ) . '" /></label> ';
		echo '<button class="button">' . esc_html__( 'Send', 'brackethive' ) . '</button>';
		echo '</form>';

		echo '</div>';
	}
}
