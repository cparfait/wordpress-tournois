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
		add_action( 'admin_notices', array( __CLASS__, 'welcome_notice' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_dismiss_welcome' ) );
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
			__( 'Tournaments', 'wegame-tournoi' ),
			__( 'Tournaments', 'wegame-tournoi' ),
			$cap,
			'wgt-dashboard',
			array( __CLASS__, 'page_dashboard' ),
			'dashicons-games',
			26
		);

		add_submenu_page( 'wgt-dashboard', __( 'Dashboard', 'wegame-tournoi' ), __( 'Dashboard', 'wegame-tournoi' ), $cap, 'wgt-dashboard', array( __CLASS__, 'page_dashboard' ) );
		add_submenu_page( 'wgt-dashboard', __( 'New tournament', 'wegame-tournoi' ), __( 'New tournament', 'wegame-tournoi' ), $cap, 'wgt-new', array( __CLASS__, 'page_new' ) );
		add_submenu_page( 'wgt-dashboard', __( 'All tournaments', 'wegame-tournoi' ), __( 'All tournaments', 'wegame-tournoi' ), $cap, 'edit.php?post_type=' . WGT_Tournament::POST_TYPE );
		add_submenu_page( 'wgt-dashboard', __( 'Teams', 'wegame-tournoi' ), __( 'Teams', 'wegame-tournoi' ), $cap, 'wgt-teams', array( __CLASS__, 'page_teams' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Matches & scores', 'wegame-tournoi' ), __( 'Matches & scores', 'wegame-tournoi' ), $cap, 'wgt-matches', array( __CLASS__, 'page_matches' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Schedule', 'wegame-tournoi' ), __( 'Schedule', 'wegame-tournoi' ), $cap, 'wgt-planning', array( __CLASS__, 'page_planning' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Tournament settings', 'wegame-tournoi' ), __( 'Tournament settings', 'wegame-tournoi' ), $cap, 'wgt-tournament', array( __CLASS__, 'page_tournament' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Public preview', 'wegame-tournoi' ), __( 'Public preview', 'wegame-tournoi' ), $cap, 'wgt-preview', array( __CLASS__, 'page_preview' ) );
		add_submenu_page( 'wgt-dashboard', __( 'Plugin settings', 'wegame-tournoi' ), __( 'Plugin', 'wegame-tournoi' ), $cap, 'wgt-settings', array( __CLASS__, 'page_settings' ) );
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
					'copied' => __( 'Copied', 'wegame-tournoi' ),
					'copy'   => __( 'Copy', 'wegame-tournoi' ),
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
			wp_die( esc_html__( 'Access denied.', 'wegame-tournoi' ) );
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
		if ( empty( $_GET['wgt_msg'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.
			return;
		}
		$type = isset( $_GET['wgt_type'] ) && 'error' === $_GET['wgt_type'] ? 'error' : 'success'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.
		// Message renvoyé par nos propres redirections : assaini avant décodage, puis échappé à l'affichage.
		$raw  = sanitize_text_field( wp_unslash( $_GET['wgt_msg'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple message d'état, aucune action déclenchée.
		$msg  = sanitize_text_field( rawurldecode( $raw ) );
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
		echo '<span class="wgt-switcher__label">' . esc_html__( 'Tournament', 'wegame-tournoi' ) . '</span>';

		if ( empty( $all ) ) {
			echo '<em>' . esc_html__( 'no tournament', 'wegame-tournoi' ) . '</em>';
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
			echo ' <a class="button button-small" href="' . esc_url( get_edit_post_link( $tid ) ) . '">' . esc_html__( 'Edit page', 'wegame-tournoi' ) . '</a>';
			echo ' <a class="button button-small" href="' . esc_url( get_permalink( $tid ) ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'wegame-tournoi' ) . '</a>';
			// Renvoie vers la zone sensible des réglages du tournoi, où la
			// suppression demande une confirmation par saisie.
			$danger = add_query_arg( array( 'page' => 'wgt-tournament', 'tournament' => (int) $tid ), admin_url( 'admin.php' ) ) . '#wgt-danger';
			echo ' <a class="button button-small wgt-switcher__delete" href="' . esc_url( $danger ) . '">' . esc_html__( 'Delete', 'wegame-tournoi' ) . '</a>';
		}

		echo ' <a class="button button-small" href="' . esc_url( add_query_arg( 'page', 'wgt-new', admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Create a tournament', 'wegame-tournoi' ) . '</a>';
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
		echo '<div class="notice notice-info"><p>' . esc_html__( 'No tournament exists yet. The wizard below guides you through four steps.', 'wegame-tournoi' ) . '</p></div>';
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
		echo '<h2>' . esc_html__( 'New tournament', 'wegame-tournoi' ) . '</h2>';
		echo '<p><label>' . esc_html__( 'Name', 'wegame-tournoi' ) . ' *<br /><input type="text" class="regular-text" name="title" required placeholder="' . esc_attr__( 'We Game 2027 — Call of Duty', 'wegame-tournoi' ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Game', 'wegame-tournoi' ) . '<br /><input type="text" class="regular-text" name="game_name" /></label></p>';
		echo '<p><label>' . esc_html__( 'Date', 'wegame-tournoi' ) . '<br /><input type="date" name="event_date" /></label></p>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Create', 'wegame-tournoi' ) . '</button></p>';
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

		$id = WGT_Tournament::create( $title, $settings, $status );

		if ( is_wp_error( $id ) ) {
			self::back( 'wgt-new', 'error', $id->get_error_message() );
		}

		// L'assistant transmet tous les réglages : même sanitisation que
		// l'écran Réglages du tournoi, puis structure du tableau.
		if ( ! empty( $_POST['wgt_wizard'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			$input                       = wp_unslash( $_POST ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			$input['public_page']        = 'none' === $choice ? 0 : 1;
			$input['wgt_checkbox_scope'] = ( isset( $input['wgt_checkbox_scope'] ) ? $input['wgt_checkbox_scope'] . ',' : '' ) . 'public_page';
			WGT_Tournament::save_settings( $id, $input );
			WGT_Data::ensure_bracket( $id );
			WGT_Data::recalculate( $id );

			if ( ! empty( $_POST['create_registration_page'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
				WGT_Tournament::create_registration_page( $id, 'none' === $choice ? 'publish' : $status );
			}
			if ( 'draft' === $choice ) {
				$msg = __( 'Tournament created as a draft. Next step: add the teams, then publish the page.', 'wegame-tournoi' );
			} elseif ( 'none' === $choice ) {
				$msg = __( 'Tournament created without a dedicated page. Place the shortcodes on your own pages, then add the teams.', 'wegame-tournoi' );
			} else {
				$msg = __( 'Tournament created and page published. Next step: add the teams.', 'wegame-tournoi' );
			}
			self::back( 'wgt-dashboard', 'updated', $msg, $id );
		}

		self::back( 'wgt-dashboard', 'updated', __( 'Tournament created.', 'wegame-tournoi' ), $id );
	}

	/**
	 * Écran « Nouveau tournoi » : assistant de création en quatre étapes.
	 */
	public static function page_new() {
		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'New tournament', 'wegame-tournoi' ) . '</h1>';
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
				__( 'Single elimination', 'wegame-tournoi' ),
				__( 'The simplest and fastest format: one loss and the team is out. Ideal for a single evening.', 'wegame-tournoi' ),
				__( '16 teams: 15 matches', 'wegame-tournoi' ),
			),
			'double' => array(
				__( 'Double elimination', 'wegame-tournoi' ),
				__( 'A team is only out after two losses, thanks to a losers bracket. Longer, but fairer.', 'wegame-tournoi' ),
				__( '16 teams: 30 matches', 'wegame-tournoi' ),
			),
			'groups' => array(
				__( 'Group stage then playoffs', 'wegame-tournoi' ),
				__( 'Each team plays several matches in its group, then the best ones face off in a bracket. The longest format.', 'wegame-tournoi' ),
				__( '16 teams in 4 groups: 24 + 7 matches', 'wegame-tournoi' ),
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
			__( 'The tournament', 'wegame-tournoi' ),
			__( 'The format', 'wegame-tournoi' ),
			__( 'Matches and times', 'wegame-tournoi' ),
			__( 'Sign-ups and creation', 'wegame-tournoi' ),
		);
		echo '<ol class="wgt-wizard__steps">';
		foreach ( $steps as $i => $label ) {
			echo '<li class="wgt-wizard__step' . ( 0 === $i ? ' is-current' : '' ) . '" data-wgt-step-link="' . (int) ( $i + 1 ) . '"><span class="wgt-wizard__num">' . (int) ( $i + 1 ) . '</span> ' . esc_html( $label ) . '</li>';
		}
		echo '</ol>';

		/* ---------------- Étape 1 : identité ---------------- */
		echo '<section class="wgt-wizard__panel" data-wgt-step="1">';
		echo '<h2>' . esc_html__( '1. The tournament', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'This information appears at the top of the public page. Only the name is required; everything can be changed later in Tournament settings.', 'wegame-tournoi' ) . '</p>';
		echo '<div class="wgt-wizard__grid">';
		echo '<p class="wgt-wizard__full"><label for="wgt-w-title">' . esc_html__( 'Tournament name', 'wegame-tournoi' ) . ' *</label><input type="text" id="wgt-w-title" class="regular-text" name="title" required placeholder="' . esc_attr__( 'We Game 2027 — Call of Duty', 'wegame-tournoi' ) . '" /><span class="description">' . esc_html__( 'It also becomes the title and the address of the tournament page.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-game">' . esc_html__( 'Game', 'wegame-tournoi' ) . '</label><input type="text" id="wgt-w-game" class="regular-text" name="game_name" placeholder="' . esc_attr__( 'Call of Duty', 'wegame-tournoi' ) . '" /></p>';
		echo '<p><label for="wgt-w-venue">' . esc_html__( 'Venue', 'wegame-tournoi' ) . '</label><input type="text" id="wgt-w-venue" class="regular-text" name="venue" placeholder="' . esc_attr__( 'Community hall', 'wegame-tournoi' ) . '" /></p>';
		echo '<p><label for="wgt-w-date">' . esc_html__( 'Date', 'wegame-tournoi' ) . '</label><input type="date" id="wgt-w-date" name="event_date" /></p>';
		echo '<p><label for="wgt-w-start">' . esc_html__( 'First match time', 'wegame-tournoi' ) . '</label><input type="time" id="wgt-w-start" name="start_time" value="' . esc_attr( $d['start_time'] ) . '" /></p>';
		echo '<p><label for="wgt-w-subtitle">' . esc_html__( 'Subtitle (optional)', 'wegame-tournoi' ) . '</label><input type="text" id="wgt-w-subtitle" class="regular-text" name="subtitle" placeholder="' . esc_attr__( 'Amateur tournament open to all', 'wegame-tournoi' ) . '" /></p>';
		echo '<p><label for="wgt-w-color">' . esc_html__( 'Accent color', 'wegame-tournoi' ) . '</label><input type="color" id="wgt-w-color" name="accent_color" value="' . esc_attr( $d['accent_color'] ) . '" /></p>';
		echo '</div>';
		echo '</section>';

		/* ---------------- Étape 2 : format ---------------- */
		echo '<section class="wgt-wizard__panel" data-wgt-step="2" hidden>';
		echo '<h2>' . esc_html__( '2. The format', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Choose how the teams face each other. The match bracket is generated automatically from this choice.', 'wegame-tournoi' ) . '</p>';
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
		echo '<p><label for="wgt-w-count">' . esc_html__( 'Number of teams', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-count" min="2" max="64" name="team_count" value="' . (int) $d['team_count'] . '" class="small-text" /><span class="description">' . esc_html__( 'From 2 to 64. If it is not a power of two (8, 16, 32…), the top seeds get a bye in the first round.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-players">' . esc_html__( 'Players per team', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-players" min="1" name="players_per_team" value="' . (int) $d['players_per_team'] . '" class="small-text" /></p>';

		echo '<p class="wgt-if-groups"><label for="wgt-w-groups">' . esc_html__( 'Number of groups', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-groups" min="2" max="8" name="group_count" value="' . (int) $d['group_count'] . '" class="small-text" /><span class="description">' . esc_html__( 'Teams are spread across the groups in snake order according to their seed. You can also build the groups by hand.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p class="wgt-if-groups"><label for="wgt-w-qual">' . esc_html__( 'Qualifiers per group', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-qual" min="1" max="4" name="qualifiers_per_group" value="' . (int) $d['qualifiers_per_group'] . '" class="small-text" /><span class="description">' . esc_html__( 'Qualifiers are cross-matched between groups for the playoffs.', 'wegame-tournoi' ) . '</span></p>';
		echo '<input type="hidden" name="group_mode" value="auto" />';

		echo '<p class="wgt-wizard__full wgt-if-not-double"><label><input type="checkbox" name="third_place" value="1" /> ' . esc_html__( 'Play a third place match between the semi-final losers', 'wegame-tournoi' ) . '</label></p>';
		echo '<p class="wgt-wizard__full wgt-if-double"><label><input type="checkbox" name="bracket_reset" value="1" /> ' . esc_html__( 'Bracket reset if the team coming from the losers bracket wins the first grand final (strict rule)', 'wegame-tournoi' ) . '</label><span class="description">' . esc_html__( 'In double elimination, the loser of the losers bracket final is automatically 3rd.', 'wegame-tournoi' ) . '</span></p>';
		echo '</div>';
		echo '</section>';

		/* ---------------- Étape 3 : matchs et horaires ---------------- */
		echo '<section class="wgt-wizard__panel" data-wgt-step="3" hidden>';
		echo '<h2>' . esc_html__( '3. Matches and times', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The schedule is calculated from these values: each match is given a time and stations. You can then adjust each time by hand.', 'wegame-tournoi' ) . '</p>';

		echo '<h3>' . esc_html__( 'Number of games per round', 'wegame-tournoi' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'BO1: one game. BO3: the first team to win two games. BO5: the first to three. A BO3 takes about twice as long as a BO1.', 'wegame-tournoi' ) . '</p>';
		$bo_rounds = array(
			'bo_group' => array( __( 'Group matches', 'wegame-tournoi' ), 'wgt-if-groups' ),
			'bo_r64'   => array( __( 'Early rounds (round of 64, round of 32)', 'wegame-tournoi' ), '' ),
			'bo_r16'   => array( __( 'R16', 'wegame-tournoi' ), '' ),
			'bo_qf'    => array( __( 'Quarters', 'wegame-tournoi' ), '' ),
			'bo_sf'    => array( __( 'Semi-finals', 'wegame-tournoi' ), '' ),
			'bo_final' => array( __( 'Final', 'wegame-tournoi' ), '' ),
			'bo_lb'    => array( __( 'Losers bracket', 'wegame-tournoi' ), 'wgt-if-double' ),
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

		echo '<h3>' . esc_html__( 'Stations and durations', 'wegame-tournoi' ) . '</h3>';
		echo '<div class="wgt-wizard__grid">';
		echo '<p><label for="wgt-w-stations">' . esc_html__( 'Available gaming stations', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-stations" min="1" name="stations" value="' . (int) $d['stations'] . '" class="small-text" /><span class="description">' . esc_html__( 'Total number of PCs or consoles.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-parallel">' . esc_html__( 'Matches played at the same time', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-parallel" min="1" name="matches_parallel" value="' . (int) $d['matches_parallel'] . '" class="small-text" /><span class="description">' . esc_html__( 'With 8 stations and teams of 4, two matches can run simultaneously.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-duration">' . esc_html__( 'Length of one game (minutes)', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-duration" min="5" name="match_duration" value="' . (int) $d['match_duration'] . '" class="small-text" /><span class="description">' . esc_html__( 'Setup and team changeover included.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-warmup">' . esc_html__( 'Check-in before the first match (minutes)', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-warmup" min="0" name="warmup_minutes" value="' . (int) $d['warmup_minutes'] . '" class="small-text" /></p>';
		echo '<p><label for="wgt-w-break">' . esc_html__( 'Break between rounds (minutes)', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-break" min="0" name="break_minutes" value="' . (int) $d['break_minutes'] . '" class="small-text" /></p>';
		echo '<p><label for="wgt-w-end">' . esc_html__( 'Expected end time', 'wegame-tournoi' ) . '</label><input type="time" id="wgt-w-end" name="end_time" value="' . esc_attr( $d['end_time'] ) . '" /><span class="description">' . esc_html__( 'Approximate, shown on the public page.', 'wegame-tournoi' ) . '</span></p>';
		echo '</div>';
		echo '</section>';

		/* ---------------- Étape 4 : inscriptions et création ---------------- */
		echo '<section class="wgt-wizard__panel" data-wgt-step="4" hidden>';
		echo '<h2>' . esc_html__( '4. Sign-ups and creation', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Two ways to build the teams: enter them yourself in the Teams screen, or open a public form for the players to fill in (requests arrive pending approval).', 'wegame-tournoi' ) . '</p>';
		echo '<div class="wgt-wizard__grid">';
		echo '<p class="wgt-wizard__full"><label><input type="checkbox" name="registration_open" value="1" /> ' . esc_html__( 'Open the public sign-up form on the tournament page', 'wegame-tournoi' ) . '</label></p>';
		echo '<p><label for="wgt-w-max">' . esc_html__( 'Maximum number of sign-ups', 'wegame-tournoi' ) . '</label><input type="number" id="wgt-w-max" min="0" name="registration_max" value="' . (int) $d['registration_max'] . '" class="small-text" /><span class="description">' . esc_html__( '0 = unlimited. A rejected team frees up its slot.', 'wegame-tournoi' ) . '</span></p>';
		echo '<p><label for="wgt-w-notify">' . esc_html__( 'E-mail notified on each sign-up', 'wegame-tournoi' ) . '</label><input type="email" id="wgt-w-notify" class="regular-text" name="notify_email" value="' . esc_attr( get_option( 'admin_email' ) ) . '" /></p>';
		echo '<p class="wgt-wizard__full"><label for="wgt-w-msg">' . esc_html__( 'Message shown after sign-up', 'wegame-tournoi' ) . '</label><textarea id="wgt-w-msg" name="registration_msg" rows="2" class="large-text">' . esc_textarea( $d['registration_msg'] ) . '</textarea></p>';
		echo '</div>';

		echo '<h3>' . esc_html__( 'Publishing', 'wegame-tournoi' ) . '</h3>';
		echo '<p class="description">' . esc_html__( 'Creation generates a dedicated tournament page (listed under "Tournaments › All tournaments", not under "Pages"), with its Bracket, Schedule, Results and Teams tabs and, if you open it, Sign-up. As long as the page is not published, only administrators can see it. Its address is then shown on the dashboard.', 'wegame-tournoi' ) . '</p>';
		echo '<div class="wgt-wizard__choices">';
		echo '<label class="wgt-choice is-selected"><input type="radio" name="post_status" value="publish" checked="checked" />';
		echo '<span class="wgt-choice__title">' . esc_html__( 'Publish the page right away', 'wegame-tournoi' ) . '</span>';
		echo '<span class="wgt-choice__desc">' . esc_html__( 'The page goes live immediately: players can view it and sign up if the form is open.', 'wegame-tournoi' ) . '</span></label>';
		echo '<label class="wgt-choice"><input type="radio" name="post_status" value="draft" />';
		echo '<span class="wgt-choice__title">' . esc_html__( 'Keep as a draft', 'wegame-tournoi' ) . '</span>';
		echo '<span class="wgt-choice__desc">' . esc_html__( 'You prepare the teams and the content at your own pace, then publish the page from "All tournaments" or "Edit page".', 'wegame-tournoi' ) . '</span></label>';
		echo '<label class="wgt-choice"><input type="radio" name="post_status" value="none" />';
		echo '<span class="wgt-choice__title">' . esc_html__( 'No dedicated page', 'wegame-tournoi' ) . '</span>';
		echo '<span class="wgt-choice__desc">' . esc_html__( 'No page is visible for this tournament. You place the bracket, the form or the schedule on your own pages yourself, using the shortcodes.', 'wegame-tournoi' ) . '</span></label>';
		echo '</div>';

		echo '<p class="wgt-wizard__full"><label><input type="checkbox" name="create_registration_page" value="1" /> ' . esc_html__( 'Also create a dedicated sign-up page', 'wegame-tournoi' ) . '</label><span class="description">' . esc_html__( 'A WordPress page containing only the simplified form (Team, Nickname, E-mail, Phone): ideal for a QR code or a poster. Its address and its QR code will appear on the dashboard.', 'wegame-tournoi' ) . '</span></p>';

		echo '<div class="wgt-wizard__after">';
		echo '<h3>' . esc_html__( 'What happens after creation?', 'wegame-tournoi' ) . '</h3>';
		echo '<ol>';
		echo '<li>' . esc_html__( 'Links: as soon as the tournament is created, the dashboard shows the address of the tournament page and of the sign-up form, ready to copy or to turn into a QR code.', 'wegame-tournoi' ) . '</li>';
		echo '<li>' . esc_html__( 'Tournament page: it is created automatically. It does not appear in the WordPress "Pages" menu but under "Tournaments › All tournaments" (the "View" button in the tournament bar). To publish or unpublish it later: "Edit page", then the Publish button. The "No dedicated page" option can be changed in Tournament settings.', 'wegame-tournoi' ) . '</li>';
		echo '<li>' . esc_html__( 'Sign-up page: if you open sign-ups, the form is already in the Sign-up tab of the tournament page. You can also create a separate WordPress page, easier to share, by pasting the shortcode below into it, then publish it.', 'wegame-tournoi' ) . '</li>';
		echo '<li>' . esc_html__( 'Sharing: a QR code or a link to one of these pages is all players need to sign up and then follow the tournament live.', 'wegame-tournoi' ) . '</li>';
		echo '<li>' . esc_html__( 'Teams: enter them in the Teams screen or approve the sign-ups received, then assign the seeds. The dashboard reminds you of these steps.', 'wegame-tournoi' ) . '</li>';
		echo '</ol>';
		echo '<p><code>[wegame_inscription simple="yes"]</code> <span class="description">' . esc_html__( 'short form (Team, Nickname, E-mail, Phone)', 'wegame-tournoi' ) . '</span><br /><code>[wegame_inscription]</code> <span class="description">' . esc_html__( 'full form with the player list', 'wegame-tournoi' ) . '</span></p>';
		echo '</div>';

		echo '<h3>' . esc_html__( 'Summary', 'wegame-tournoi' ) . '</h3>';
		echo '<dl class="wgt-wizard__summary" data-wgt-summary>';
		$summary = array(
			'title'    => __( 'Tournament', 'wegame-tournoi' ),
			'format'   => __( 'Format', 'wegame-tournoi' ),
			'teams'    => __( 'Teams', 'wegame-tournoi' ),
			'matches'  => __( 'Matches to play', 'wegame-tournoi' ),
			'when'     => __( 'When', 'wegame-tournoi' ),
			'end'      => __( 'Estimated end', 'wegame-tournoi' ),
			'signup'   => __( 'Sign-ups', 'wegame-tournoi' ),
			'status'   => __( 'Tournament page', 'wegame-tournoi' ),
		);
		foreach ( $summary as $key => $label ) {
			echo '<dt>' . esc_html( $label ) . '</dt><dd data-wgt-sum="' . esc_attr( $key ) . '">—</dd>';
		}
		echo '</dl>';
		echo '<p class="description">' . esc_html__( 'Everything can still be changed after creation. The match bracket is created immediately; it fills in as the teams are given a seed.', 'wegame-tournoi' ) . '</p>';
		echo '</section>';

		// Navigation.
		echo '<div class="wgt-wizard__nav">';
		echo '<button type="button" class="button" data-wgt-prev>' . esc_html__( '← Previous', 'wegame-tournoi' ) . '</button>';
		echo '<button type="button" class="button button-primary" data-wgt-next>' . esc_html__( 'Next →', 'wegame-tournoi' ) . '</button>';
		echo '<button type="submit" class="button button-primary button-hero" data-wgt-submit>' . esc_html__( 'Create the tournament', 'wegame-tournoi' ) . '</button>';
		echo '</div>';

		// Libellés pour le récapitulatif calculé par le script.
		echo '<script type="application/json" data-wgt-wizard-i18n>' . wp_json_encode(
			array(
				'formats'  => array(
					'single' => $formats['single'][0],
					'double' => $formats['double'][0],
					'groups' => $formats['groups'][0],
				),
				/* translators: %d: nombre d'équipes */
				'teams'    => __( '%d teams', 'wegame-tournoi' ),
				/* translators: 1: nombre de poules, 2: nombre de qualifiés par poule */
				'groups'   => __( '%1$d groups, %2$d qualifiers per group', 'wegame-tournoi' ),
				/* translators: %d: nombre de matchs estimé */
				'matches'  => __( 'about %d', 'wegame-tournoi' ),
				'open'     => __( 'Public form open', 'wegame-tournoi' ),
				'closed'   => __( 'Entered by the organizers', 'wegame-tournoi' ),
				'publish'  => __( 'Published immediately', 'wegame-tournoi' ),
				'draft'    => __( 'Draft, to publish later', 'wegame-tournoi' ),
				'none'     => __( 'No dedicated page (shortcodes)', 'wegame-tournoi' ),
				'at'       => __( 'to', 'wegame-tournoi' ),
				'required' => __( 'The tournament name is required.', 'wegame-tournoi' ),
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
		echo '<h2 id="wgt-danger">' . esc_html__( 'Danger zone: reset or delete the tournament', 'wegame-tournoi' ) . '</h2>';
		echo '<div class="wgt-danger">';
		echo '<p><strong>' . esc_html__( 'These actions cannot be undone and only affect the selected tournament.', 'wegame-tournoi' ) . '</strong></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p><label for="wgt-confirm">' . esc_html__( 'To empty the tournament (teams + results), type REINITIALISER:', 'wegame-tournoi' ) . '</label><br />';
		echo '<input type="text" id="wgt-confirm" name="confirm" value="" autocomplete="off" placeholder="REINITIALISER" /> ';
		echo '<button class="button button-link-delete" name="tool" value="reset_all" onclick="return confirm(\'' . esc_js( __( 'Permanently delete all teams and all results of this tournament?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Reset', 'wegame-tournoi' ) . '</button></p>';
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p><label for="wgt-confirm-del">' . esc_html__( 'To delete the tournament and its page, type SUPPRIMER:', 'wegame-tournoi' ) . '</label><br />';
		echo '<input type="text" id="wgt-confirm-del" name="confirm" value="" autocomplete="off" placeholder="SUPPRIMER" /> ';
		echo '<button class="button button-link-delete" name="tool" value="delete_tournament" onclick="return confirm(\'' . esc_js( __( 'Permanently delete this tournament, its page and all its data?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Delete the tournament', 'wegame-tournoi' ) . '</button></p>';
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

		echo '<div class="wgt-links"><h2>' . esc_html__( 'Public links', 'wegame-tournoi' ) . '</h2>';

		if ( $has_page && 'publish' !== $status ) {
			echo '<p class="wgt-links__warn">' . esc_html__( 'The tournament page is a draft: its address will only work for visitors once it is published.', 'wegame-tournoi' ) . '</p>';
		}

		if ( $has_page ) {
			self::link_row(
				__( 'Tournament page', 'wegame-tournoi' ),
				__( 'bracket, schedule, results, teams', 'wegame-tournoi' ),
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
				__( 'Sign-up page', 'wegame-tournoi' ),
				'publish' === $reg_status
					? __( 'to share with players', 'wegame-tournoi' )
					: __( 'not published: publish it to make it accessible', 'wegame-tournoi' ),
				$reg_url,
				'inscription',
				get_edit_post_link( $reg_page )
			);
		} elseif ( $has_page && ! empty( $s['registration_open'] ) ) {
			self::link_row(
				__( 'Sign-up form', 'wegame-tournoi' ),
				__( 'Sign-up tab of the tournament page', 'wegame-tournoi' ),
				$url . '#wgt-registration',
				'inscription'
			);
		}

		if ( ! $reg_page ) {
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
			wp_nonce_field( 'wgt_create_registration_page' );
			echo '<input type="hidden" name="action" value="wgt_create_registration_page" />';
			echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
			echo '<button class="button">' . esc_html__( 'Create a dedicated sign-up page', 'wegame-tournoi' ) . '</button> ';
			echo '<span class="description">' . esc_html__( 'A WordPress page containing only the simplified form: shorter to share and to print as a QR code.', 'wegame-tournoi' ) . '</span>';
			echo '</form>';
		}

		if ( empty( $s['registration_open'] ) ) {
			echo '<p class="description">' . esc_html__( 'Sign-ups are closed: the form will show "sign-ups closed" until you open them in Tournament settings.', 'wegame-tournoi' ) . '</p>';
		}

		if ( ! $has_page ) {
			echo '<p class="description">' . esc_html__( 'This tournament has no dedicated page. Paste these shortcodes into your own pages, then publish them:', 'wegame-tournoi' ) . '</p>';
			echo '<p><code>[wegame_tournoi tournoi="' . esc_html( $slug ) . '"]</code> ' . esc_html__( 'full tabbed page', 'wegame-tournoi' ) . '<br />';
			echo '<code>[wegame_inscription simple="yes" tournoi="' . esc_html( $slug ) . '"]</code> ' . esc_html__( 'simplified sign-up form', 'wegame-tournoi' ) . '<br />';
			echo '<code>[wegame_tableau tournoi="' . esc_html( $slug ) . '"]</code> ' . esc_html__( 'bracket only (control room screen)', 'wegame-tournoi' ) . '</p>';
		}

		$pages = self::pages_using_shortcodes( $tid );
		if ( $pages ) {
			echo '<p><strong>' . esc_html__( 'Site pages using the shortcodes', 'wegame-tournoi' ) . '</strong><br />';
			foreach ( $pages as $page ) {
				echo '<a href="' . esc_url( get_permalink( $page ) ) . '" target="_blank" rel="noopener">' . esc_html( get_the_title( $page ) ) . '</a>';
				if ( 'publish' !== $page->post_status ) {
					echo ' <em>(' . esc_html__( 'not published', 'wegame-tournoi' ) . ')</em>';
				}
				echo ' — <a href="' . esc_url( get_edit_post_link( $page->ID ) ) . '">' . esc_html__( 'edit', 'wegame-tournoi' ) . '</a><br />';
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
		echo '<button type="button" class="button button-small" data-wgt-copy>' . esc_html__( 'Copy', 'wegame-tournoi' ) . '</button> ';
		echo '<a class="button button-small" href="' . esc_url( $url ) . '" target="_blank" rel="noopener">' . esc_html__( 'Open', 'wegame-tournoi' ) . '</a>';
		if ( $edit_url ) {
			echo ' <a class="button button-small" href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'wegame-tournoi' ) . '</a>';
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
					__( 'QR code to %s', 'wegame-tournoi' ),
					$label
				)
			) . '" />';
			echo '<span class="wgt-qr__actions"><span class="wgt-qr__label">' . esc_html__( 'Download the QR code', 'wegame-tournoi' ) . '</span>';
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

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Tables propres à l'extension, écriture directe nécessaire ; pas de cache objet pour ces données transactionnelles.
			"SELECT ID, post_title, post_status, post_type, post_name FROM {$wpdb->posts} WHERE post_type IN ('page','post') AND post_status IN ('publish','draft','pending','private') AND post_content LIKE '%[wegame_%' ORDER BY post_title ASC LIMIT 50" // phpcs:ignore WordPress.DB, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Requête littérale sans donnée utilisateur ; seule la propriété $wpdb->posts est interpolée.
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
				__( 'Publish the tournament page', 'wegame-tournoi' ),
				__( 'The page is a draft: only administrators can see it. Publish it once the content is ready so players can view it and sign up.', 'wegame-tournoi' ),
				get_edit_post_link( $tid, 'raw' ),
				__( 'Edit and publish', 'wegame-tournoi' ),
			);
		}

		$steps[] = array(
				$done_teams,
				sprintf(
					/* translators: 1: équipes validées, 2: équipes attendues */
					__( 'Build the teams (%1$d / %2$d approved)', 'wegame-tournoi' ),
					$stats['teams_active'],
					$stats['teams_max']
				),
				! empty( $s['registration_open'] )
					? __( 'The public form is open: approve the requests received, or add teams yourself.', 'wegame-tournoi' )
					: __( 'Add each team from the Teams screen, or open public sign-ups in Tournament settings.', 'wegame-tournoi' ),
				$teams_url,
				__( 'Open Teams', 'wegame-tournoi' ),
			);
		$steps[] = array(
				$done_seed,
				__( 'Assign the seeds', 'wegame-tournoi' ),
				__( 'Each approved team gets a number from 1 to N that places it in the bracket. The "Random draw for seeds" button does it for you.', 'wegame-tournoi' ),
				$teams_url,
				__( 'Place the teams', 'wegame-tournoi' ),
			);
		if ( WGT_Tournament::has_public_page( $tid ) ) {
			$steps[] = array(
				false,
				__( 'Check the public page', 'wegame-tournoi' ),
				__( 'The tournament page shows the bracket, schedule, results and sign-ups. Its links are in the "Public links" box above.', 'wegame-tournoi' ),
				$public_url,
				__( 'View the page', 'wegame-tournoi' ),
			);
		} else {
			$steps[] = array(
				false,
				__( 'Put the shortcodes on your pages', 'wegame-tournoi' ),
				__( 'This tournament has no dedicated page: copy the shortcodes from the "Public links" box into the pages of your choice, then publish them.', 'wegame-tournoi' ),
				add_query_arg( 'post_type', 'page', admin_url( 'post-new.php' ) ),
				__( 'Create a page', 'wegame-tournoi' ),
			);
		}
		$steps[] = array(
				false,
				__( 'On the day: enter the scores', 'wegame-tournoi' ),
				__( 'In Matches & scores, enter the result of each match: the winner automatically moves on to the next round and the public page updates by itself.', 'wegame-tournoi' ),
				$matches_url,
				__( 'Open Matches & scores', 'wegame-tournoi' ),
			);

		echo '<div class="wgt-next"><h2>' . esc_html__( 'Next steps', 'wegame-tournoi' ) . '</h2><ol class="wgt-next__list">';
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
		$title  = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
		$teams  = ! empty( $_POST['with_teams'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		$id = WGT_Tournament::duplicate( $source, $title, $teams );

		if ( is_wp_error( $id ) ) {
			self::back( 'wgt-dashboard', 'error', $id->get_error_message(), $source );
		}

		self::back( 'wgt-dashboard', 'updated', __( 'Tournament duplicated.', 'wegame-tournoi' ), $id );
	}

	/**
	 * Réglages d'un tournoi.
	 */
	public static function handle_save_tournament() {
		self::guard( 'wgt_save_tournament' );

		$tid = self::posted_tournament();
		if ( ! $tid ) {
			self::back( 'wgt-tournament', 'error', __( 'Tournament not found.', 'wegame-tournoi' ) );
		}

		$before = WGT_Tournament::settings( $tid );

		WGT_Tournament::save_settings( $tid, wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.

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

		self::back( 'wgt-tournament', 'updated', __( 'Tournament settings saved.', 'wegame-tournoi' ), $tid );
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

		$result = WGT_Data::save_team( $data );

		if ( is_wp_error( $result ) ) {
			$extra = $data['id'] ? array( 'edit' => $data['id'] ) : array();
			self::back( 'wgt-teams', 'error', $result->get_error_message(), $tid, $extra );
		}

		self::back( 'wgt-teams', 'updated', __( 'Team saved.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Suppression d'une équipe.
	 */
	public static function handle_delete_team() {
		self::guard( 'wgt_delete_team' );

		$tid = self::posted_tournament();
		$id  = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		if ( $id ) {
			WGT_Data::delete_team( $id );
		}

		self::back( 'wgt-teams', 'updated', __( 'Team deleted.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Enregistrement d'un résultat.
	 */
	public static function handle_save_result() {
		self::guard( 'wgt_save_result' );

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

		$result = WGT_Data::save_result(
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
			self::back( 'wgt-matches', 'error', $result->get_error_message(), $tid );
		}

		self::back( 'wgt-matches', 'updated', __( 'Result saved.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Outils.
	 */
	public static function handle_tools() {
		self::guard( 'wgt_tools' );

		$tid  = self::posted_tournament();
		$tool = isset( $_POST['tool'] ) ? sanitize_key( wp_unslash( $_POST['tool'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		if ( ! $tid ) {
			self::back( 'wgt-dashboard', 'error', __( 'No tournament selected.', 'wegame-tournoi' ) );
		}

		if ( 'autoseed' === $tool ) {
			$placed = WGT_Data::autoseed( $tid, true );
			self::back(
				'wgt-teams',
				'updated',
				sprintf(
					/* translators: %d: nombre d'équipes */
					__( '%d team(s) placed randomly in the bracket.', 'wegame-tournoi' ),
					$placed
				),
				$tid
			);
		}

		if ( 'reset_results' === $tool ) {
			WGT_Data::reset_results( $tid );
			self::back( 'wgt-matches', 'updated', __( 'All results have been reset to zero.', 'wegame-tournoi' ), $tid );
		}

		if ( 'rebuild' === $tool ) {
			WGT_Data::ensure_bracket( $tid );
			WGT_Data::recalculate( $tid );
			self::back( 'wgt-dashboard', 'updated', __( 'Bracket regenerated.', 'wegame-tournoi' ), $tid );
		}

		if ( 'reschedule' === $tool ) {
			WGT_Data::rebuild_schedule( $tid );
			self::back( 'wgt-planning', 'updated', __( 'Schedule recalculated from the tournament settings.', 'wegame-tournoi' ), $tid );
		}

		if ( 'validate_all' === $tool ) {
			global $wpdb;
			$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Tables propres à l'extension ; nom de table issu de wgt_table(), valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
				$wpdb->prepare(
					'UPDATE ' . wgt_table( 'teams' ) . " SET status = 'active' WHERE tournament_id = %d AND status = 'pending'", // phpcs:ignore WordPress.DB.PreparedSQL, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Nom de table construit par wgt_table(), sans donnée utilisateur ; les valeurs passent par $wpdb->prepare().
					$tid
				)
			);
			WGT_Data::recalculate( $tid );
			self::back( 'wgt-teams', 'updated', __( 'All pending sign-ups have been approved.', 'wegame-tournoi' ), $tid );
		}

		if ( 'reset_all' === $tool ) {
			$confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			if ( 'REINITIALISER' !== strtoupper( remove_accents( $confirm ) ) ) {
				self::back( 'wgt-dashboard', 'error', __( 'Reset cancelled: the confirmation word does not match.', 'wegame-tournoi' ), $tid );
			}
			WGT_Data::reset_all( $tid );
			self::back( 'wgt-dashboard', 'updated', __( 'Tournament reset: teams and results deleted, empty bracket recreated.', 'wegame-tournoi' ), $tid );
		}

		if ( 'delete_tournament' === $tool ) {
			$confirm = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			if ( 'SUPPRIMER' !== strtoupper( remove_accents( $confirm ) ) ) {
				self::back( 'wgt-dashboard', 'error', __( 'Deletion cancelled: the confirmation word does not match.', 'wegame-tournoi' ), $tid );
			}
			WGT_Tournament::delete( $tid );
			self::back( 'wgt-dashboard', 'updated', __( 'Tournament deleted.', 'wegame-tournoi' ) );
		}

		self::back( 'wgt-dashboard', 'error', __( 'Unknown action.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Réglages généraux.
	 */
	public static function handle_save_settings() {
		self::guard( 'wgt_save_settings' );

		WGT_Settings::save( wp_unslash( $_POST ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.

		if ( isset( $_POST['default_tournament'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			$default = (int) $_POST['default_tournament']; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			update_option( 'wgt_default_tournament', WGT_Tournament::exists( $default ) ? $default : 0 );
		}
		self::back( 'wgt-settings', 'updated', __( 'Settings saved.', 'wegame-tournoi' ) );
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
			self::back( 'wgt-dashboard', 'error', __( 'No tournament selected.', 'wegame-tournoi' ) );
		}

		$json = wp_json_encode( WGT_IO::build_export( $tid ), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );

		nocache_headers();
		header( 'Content-Type: application/json; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . WGT_IO::filename( $tid ) . '"' );
		header( 'Content-Length: ' . strlen( $json ) );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput -- Flux JSON produit par wp_json_encode() et servi en téléchargement : tout échappement HTML corromprait le fichier.
		exit;
	}

	/**
	 * Restauration depuis un fichier JSON.
	 */
	public static function handle_import() {
		self::guard( 'wgt_import' );

		$tid = self::posted_tournament();
		if ( ! $tid ) {
			self::back( 'wgt-dashboard', 'error', __( 'No tournament selected.', 'wegame-tournoi' ) );
		}

		if ( empty( $_FILES['backup']['tmp_name'] ) || ! is_uploaded_file( $_FILES['backup']['tmp_name'] ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
			self::back( 'wgt-dashboard', 'error', __( 'No file received.', 'wegame-tournoi' ), $tid );
		}

		if ( isset( $_FILES['backup']['error'] ) && UPLOAD_ERR_OK !== (int) $_FILES['backup']['error'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
			self::back( 'wgt-dashboard', 'error', __( 'The file upload failed.', 'wegame-tournoi' ), $tid );
		}

		$size = isset( $_FILES['backup']['size'] ) ? (int) $_FILES['backup']['size'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
		if ( $size <= 0 || $size > 5 * MB_IN_BYTES ) {
			self::back( 'wgt-dashboard', 'error', __( 'File empty or too large (5 MB maximum).', 'wegame-tournoi' ), $tid );
		}

		$path = sanitize_text_field( $_FILES['backup']['tmp_name'] ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput, WordPress.Security.NonceVerification.Missing -- Assainissement effectué en aval ; nonce vérifié par self::guard() en tête de méthode.
		$json = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- Lecture du fichier téléversé temporaire local, hors système de fichiers WordPress.

		if ( false === $json ) {
			self::back( 'wgt-dashboard', 'error', __( 'The file could not be read.', 'wegame-tournoi' ), $tid );
		}

		$result = WGT_IO::import( $json, $tid, ! empty( $_POST['with_settings'] ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.

		if ( is_wp_error( $result ) ) {
			self::back( 'wgt-dashboard', 'error', $result->get_error_message(), $tid );
		}

		self::back( 'wgt-dashboard', 'updated', __( 'Backup restored.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * Vérification manuelle des mises à jour.
	 */
	/**
	 * Invitation affichée une seule fois après l'activation : choix de la
	 * langue et création du premier tournoi.
	 */
	public static function welcome_notice() {
		if ( ! get_option( 'wgt_welcome' ) || ! current_user_can( self::cap() ) ) {
			return;
		}

		$settings = add_query_arg( 'page', 'wgt-settings', admin_url( 'admin.php' ) );
		$create   = add_query_arg( 'page', 'wgt-new', admin_url( 'admin.php' ) );
		$dismiss  = wp_nonce_url( add_query_arg( 'wgt_dismiss', '1', $settings ), 'wgt_dismiss' );
		$locale   = determine_locale();
		$names    = wgt_available_locales();
		$current  = isset( $names[ $locale ] ) ? $names[ $locale ] : __( 'English', 'wegame-tournoi' );

		echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'We Game Tournoi is ready.', 'wegame-tournoi' ) . '</strong> ';
		echo esc_html(
			sprintf(
				/* translators: %s: language name */
				__( 'The plugin follows your site language (%s). You can pick another one in its settings.', 'wegame-tournoi' ),
				$current
			)
		) . '</p><p>';
		echo '<a class="button button-primary" href="' . esc_url( $create ) . '">' . esc_html__( 'Create a tournament', 'wegame-tournoi' ) . '</a> ';
		echo '<a class="button" href="' . esc_url( $settings ) . '">' . esc_html__( 'Choose the language', 'wegame-tournoi' ) . '</a> ';
		echo '<a class="button-link" href="' . esc_url( $dismiss ) . '">' . esc_html__( 'Dismiss', 'wegame-tournoi' ) . '</a>';
		echo '</p></div>';
	}

	/**
	 * Fermeture de l'invitation.
	 */
	public static function maybe_dismiss_welcome() {
		if ( empty( $_GET['wgt_dismiss'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce vérifié juste après.
			return;
		}
		if ( ! current_user_can( self::cap() ) || ! isset( $_GET['_wpnonce'] ) ) {
			return;
		}
		if ( wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'wgt_dismiss' ) ) {
			delete_option( 'wgt_welcome' );
		}
	}

	/**
	 * Crée la page d'inscription du tournoi courant.
	 */
	public static function handle_create_registration_page() {
		self::guard( 'wgt_create_registration_page' );

		$tid = isset( $_POST['tournament_id'] ) ? (int) $_POST['tournament_id'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Nonce vérifié par self::guard() en tête de méthode.
		if ( ! $tid ) {
			self::back( 'wgt-dashboard', 'error', __( 'Tournament not found.', 'wegame-tournoi' ) );
		}

		$page_id = WGT_Tournament::create_registration_page( $tid );
		if ( is_wp_error( $page_id ) ) {
			self::back( 'wgt-dashboard', 'error', $page_id->get_error_message(), $tid );
		}

		self::back( 'wgt-dashboard', 'updated', __( 'Sign-up page created and published.', 'wegame-tournoi' ), $tid );
	}

	/**
	 * E-mail de test : vérifie que le site sait envoyer des messages.
	 */
	public static function handle_test_mail() {
		self::guard( 'wgt_test_mail' );

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
				__( '[%s] We Game Tournoi test email', 'wegame-tournoi' ),
				get_bloginfo( 'name' )
			),
			__( 'If you receive this message, the site can send sign-up notifications.', 'wegame-tournoi' ) . "\n" . home_url( '/' )
		);

		remove_action( 'wp_mail_failed', $catch );

		if ( $sent ) {
			self::back(
				'wgt-settings',
				'updated',
				sprintf(
					/* translators: %s: adresse e-mail */
					__( 'Test email handed to the server for %s. Check the inbox (and the spam folder); if it does not arrive, your hosting does not relay emails: install an SMTP plugin.', 'wegame-tournoi' ),
					$to
				)
			);
		}

		self::back(
			'wgt-settings',
			'error',
			sprintf(
				/* translators: %s: message d'erreur */
				__( 'The server could not send the email: %s. Set up sending (SMTP plugin) before opening sign-ups.', 'wegame-tournoi' ),
				$error ? $error : __( 'no mail function available', 'wegame-tournoi' )
			)
		);
	}

	public static function handle_check_update() {
		self::guard( 'wgt_check_update' );

		WGT_Updater::force_check();
		$manifest = WGT_Updater::manifest( true );

		if ( ! $manifest ) {
			self::back( 'wgt-settings', 'error', __( 'No valid manifest could be retrieved. Check the URL (the package must be served over HTTPS).', 'wegame-tournoi' ) );
		}

		if ( version_compare( $manifest['version'], WGT_VERSION, '>' ) ) {
			self::back(
				'wgt-settings',
				'updated',
				sprintf(
					/* translators: %s: numéro de version */
					__( 'Version %s available. Go to the Plugins page to install it.', 'wegame-tournoi' ),
					$manifest['version']
				)
			);
		}

		self::back( 'wgt-settings', 'updated', __( 'The plugin is up to date.', 'wegame-tournoi' ) );
	}

	/**
	 * Contenu de l'iframe d'aperçu.
	 */
	public static function handle_preview_frame() {
		self::guard( 'wgt_preview_frame' );

		$view = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'full'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce vérifié par self::guard() en tête de méthode.
		if ( ! in_array( $view, WGT_Render::views(), true ) ) {
			$view = 'full';
		}

		$tid = isset( $_GET['tournament'] ) ? (int) $_GET['tournament'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Nonce vérifié par self::guard() en tête de méthode.
		if ( ! WGT_Tournament::exists( $tid ) ) {
			$tid = WGT_Tournament::current_public();
		}

		nocache_headers();
		header( 'Content-Type: text/html; charset=utf-8' );
		header( 'X-Frame-Options: SAMEORIGIN' );

		$css = WGT_URL . 'assets/css/wgt-public.css?ver=' . rawurlencode( WGT_VERSION );
		$js  = WGT_URL . 'assets/js/wgt-public.js?ver=' . rawurlencode( WGT_VERSION );

		echo '<!DOCTYPE html><html ';
		language_attributes();
		echo '><head><meta charset="utf-8" />';
		echo '<meta name="viewport" content="width=device-width, initial-scale=1" />';
		echo '<title>' . esc_html__( 'Preview', 'wegame-tournoi' ) . '</title>';
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet -- Document autonome affiché en iframe, hors rendu WordPress : wp_head() n'y est pas appelé.
		echo '<link rel="stylesheet" href="' . esc_url( $css ) . '" />';
		echo '<style>html,body{margin:0;padding:0;background:transparent}</style>';
		echo '</head><body>';

		echo WGT_Render::view( $view, array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendu HTML complet, échappement effectué dans les gabarits de WGT_Render.

		echo '<script>var WGT_CFG={endpoint:"",interval:0,i18n:{}};</script>';
		// phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Document autonome affiché en iframe, hors rendu WordPress : wp_footer() n'y est pas appelé.
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
			self::no_tournament( __( 'Tournaments', 'wegame-tournoi' ) );
			return;
		}

		$settings = WGT_Tournament::settings( $tid );
		$stats    = WGT_Data::get_stats( $tid );
		$teams    = WGT_Data::get_teams_map( $tid );
		$rounds   = WGT_Data::get_matches_by_round( $tid );
		$labels   = WGT_Bracket::round_order( $settings );

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Dashboard', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-dashboard' );

		echo '<div class="wgt-cards">';
		$cards = array(
			array( __( 'Signed-up teams', 'wegame-tournoi' ), $stats['teams_total'] ),
			array( __( 'Approved teams', 'wegame-tournoi' ), $stats['teams_active'] ),
			array( __( 'Seeds assigned', 'wegame-tournoi' ), $stats['teams_seeded'] . ' / ' . $stats['teams_max'] ),
			array( __( 'Matches played', 'wegame-tournoi' ), $stats['matches_done'] . ' / ' . $stats['matches_total'] ),
		);
		foreach ( $cards as $card ) {
			echo '<div class="wgt-card"><span class="wgt-card__value">' . esc_html( $card[1] ) . '</span><span class="wgt-card__label">' . esc_html( $card[0] ) . '</span></div>';
		}
		echo '</div>';

		if ( $stats['champion_id'] ) {
			echo '<div class="notice notice-success"><p><strong>' . esc_html__( 'Winner:', 'wegame-tournoi' ) . '</strong> ' . esc_html( WGT_Data::team_name( $stats['champion_id'], $teams ) );
			if ( $stats['third_id'] ) {
				echo ' — ' . esc_html__( 'Third place:', 'wegame-tournoi' ) . ' ' . esc_html( WGT_Data::team_name( $stats['third_id'], $teams ) );
			}
			echo '</p></div>';
		}

		if ( $stats['teams_seeded'] < $stats['teams_max'] ) {
			echo '<div class="notice notice-warning"><p>' . esc_html(
				sprintf(
					/* translators: %d: nombre d'équipes attendu */
					__( 'The bracket is not complete: assign a seed (1 to %d) to each approved team from the Teams screen.', 'wegame-tournoi' ),
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
						__( 'Incomplete groups: %s. A group must have at least 2 approved teams for its matches to exist; assign a group to each team from the Teams screen.', 'wegame-tournoi' ),
						implode( ', ', $tiny )
					)
				) . '</p></div>';
			}
			if ( $uneven ) {
				echo '<div class="notice notice-warning"><p>' . esc_html(
					sprintf(
						/* translators: 1: liste des poules, 2: effectif attendu */
						__( 'Unbalanced groups: %1$s (reference size: %2$d). Each group plays its own matches, but a larger group plays more of them; the number of qualified teams is limited by the smallest group.', 'wegame-tournoi' ),
						implode( ', ', $uneven ),
						$expected
					)
				) . '</p></div>';
			}
		}

		self::public_links( $tid, $settings );
		self::next_steps( $tid, $stats, $settings );

		// Aperçu du tableau.
		echo '<h2>' . esc_html__( 'Bracket preview', 'wegame-tournoi' ) . '</h2>';
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
		echo '<h2>' . esc_html__( 'Tools', 'wegame-tournoi' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button" name="tool" value="rebuild">' . esc_html__( 'Regenerate the bracket', 'wegame-tournoi' ) . '</button> ';
		echo '<button class="button" name="tool" value="autoseed">' . esc_html__( 'Random draw for seeds', 'wegame-tournoi' ) . '</button> ';
		echo '<button class="button button-link-delete" name="tool" value="reset_results" onclick="return confirm(\'' . esc_js( __( 'Reset all results to zero? The teams are kept.', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Reset all results', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		// Sauvegarde.
		echo '<h2>' . esc_html__( 'Backup', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The backup only covers the selected tournament.', 'wegame-tournoi' ) . '</p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_export' );
		echo '<input type="hidden" name="action" value="wgt_export" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button button-primary">' . esc_html__( 'Download a backup (JSON)', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '<form method="post" enctype="multipart/form-data" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-box" style="max-width:720px">';
		wp_nonce_field( 'wgt_import' );
		echo '<input type="hidden" name="action" value="wgt_import" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p><label for="wgt-backup"><strong>' . esc_html__( 'Restore a backup into this tournament', 'wegame-tournoi' ) . '</strong></label><br />';
		echo '<input type="file" id="wgt-backup" name="backup" accept=".json,application/json" required /></p>';
		echo '<p><label><input type="checkbox" name="with_settings" value="1" /> ' . esc_html__( 'Also restore the tournament settings', 'wegame-tournoi' ) . '</label></p>';
		echo '<p><button class="button" onclick="return confirm(\'' . esc_js( __( 'Replace this tournament\'s data with the contents of the file?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Restore', 'wegame-tournoi' ) . '</button></p>';
		echo '</form>';

		// Duplication.
		echo '<h2>' . esc_html__( 'Duplicate this tournament', 'wegame-tournoi' ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-box" style="max-width:640px">';
		wp_nonce_field( 'wgt_duplicate_tournament' );
		echo '<input type="hidden" name="action" value="wgt_duplicate_tournament" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<p class="description">' . esc_html__( 'Carries over the settings and the format, without the results. Ideal for preparing the next edition.', 'wegame-tournoi' ) . '</p>';
		echo '<p><label>' . esc_html__( 'Name of the new tournament', 'wegame-tournoi' ) . ' *<br /><input type="text" class="regular-text" name="title" required /></label></p>';
		echo '<p><label><input type="checkbox" name="with_teams" value="1" /> ' . esc_html__( 'Also carry over the teams (pending approval, without a seed)', 'wegame-tournoi' ) . '</label></p>';
		echo '<p><button class="button button-primary">' . esc_html__( 'Duplicate', 'wegame-tournoi' ) . '</button></p>';
		echo '</form>';

		// Shortcodes.
		echo '<h2>' . esc_html__( 'Shortcodes', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'The tournament page already shows everything automatically: these shortcodes are only useful if you want the same content elsewhere on the site.', 'wegame-tournoi' ) . '</p>';
		echo '<p class="description"><strong>' . esc_html__( 'How to use them:', 'wegame-tournoi' ) . '</strong> ' . esc_html__( 'create a WordPress page (Pages › Add New), paste the shortcode you want into it, then click Publish. The tournament content will appear on that page, and will update by itself during the event.', 'wegame-tournoi' ) . '</p>';
		echo '<table class="widefat striped wgt-shortcodes"><thead><tr><th>' . esc_html__( 'Shortcode', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Display', 'wegame-tournoi' ) . '</th></tr></thead><tbody>';
		$slug         = get_post_field( 'post_name', $tid );
		$descriptions = array(
			'wegame_tournois'     => __( 'List of all tournaments on the site', 'wegame-tournoi' ),
			'wegame_tournoi'      => __( 'Full tabbed page', 'wegame-tournoi' ),
			'wegame_tableau'      => __( 'Single-elimination bracket', 'wegame-tournoi' ),
			'wegame_planning'     => __( 'Time schedule', 'wegame-tournoi' ),
			'wegame_resultats'    => __( 'Results sheet', 'wegame-tournoi' ),
			'wegame_classement'   => __( 'Overall ranking', 'wegame-tournoi' ),
			'wegame_poules'       => __( 'Group standings', 'wegame-tournoi' ),
			'wegame_equipes'      => __( 'Participating teams', 'wegame-tournoi' ),
			'wegame_reglement'    => __( 'Rules', 'wegame-tournoi' ),
			'wegame_organisation' => __( 'Staff needed', 'wegame-tournoi' ),
			'wegame_checklist'    => __( 'Pre-opening checklist', 'wegame-tournoi' ),
			'wegame_inscription'  => __( 'Sign-up form (simple="yes": Team, Nickname, Email, Phone)', 'wegame-tournoi' ),
		);
		foreach ( $descriptions as $tag => $desc ) {
			$example = 'wegame_tournois' === $tag ? '[' . $tag . ']' : '[' . $tag . ' tournoi="' . $slug . '"]';
			echo '<tr><td><code>' . esc_html( $example ) . '</code></td><td>' . esc_html( $desc ) . '</td></tr>';
		}
		echo '</tbody></table>';
		echo '<p class="description">' . esc_html__( 'Without the "tournoi" attribute, the shortcode shows the site\'s default tournament. A page containing a shortcode stays invisible to visitors until it is published.', 'wegame-tournoi' ) . '</p>';
		echo '<p><a class="button" href="' . esc_url( add_query_arg( 'post_type', 'page', admin_url( 'post-new.php' ) ) ) . '">' . esc_html__( 'Create a page now', 'wegame-tournoi' ) . '</a></p>';

		echo '</div>';
	}

	/**
	 * Gestion des équipes.
	 */
	public static function page_teams() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Teams', 'wegame-tournoi' ) );
			return;
		}

		$max     = (int) WGT_Tournament::get( $tid, 'team_count' );
		$max     = $max > 0 ? $max : 16;
		$edit_id = isset( $_GET['edit'] ) ? (int) $_GET['edit'] : 0; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.
		$editing = $edit_id ? WGT_Data::get_team( $edit_id ) : null;

		if ( $editing && (int) $editing['tournament_id'] !== $tid ) {
			$editing = null;
		}

		$teams = WGT_Data::get_teams( array( 'tournament_id' => $tid ) );

		$status_labels = array(
			'pending'  => __( 'Pending', 'wegame-tournoi' ),
			'active'   => __( 'Approved', 'wegame-tournoi' ),
			'rejected' => __( 'Rejected', 'wegame-tournoi' ),
		);

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Teams', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-teams' );

		// Formulaire.
		echo '<div class="wgt-teams-form" id="wgt-team-form">';
		echo '<h2>' . ( $editing ? esc_html__( 'Edit team', 'wegame-tournoi' ) : esc_html__( 'Add a team', 'wegame-tournoi' ) ) . '</h2>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-box wgt-team-form">';
		wp_nonce_field( 'wgt_save_team' );
		echo '<input type="hidden" name="action" value="wgt_save_team" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<input type="hidden" name="id" value="' . ( $editing ? (int) $editing['id'] : 0 ) . '" />';

		$val = function ( $key ) use ( $editing ) {
			return $editing && isset( $editing[ $key ] ) ? $editing[ $key ] : '';
		};

		echo '<p><label>' . esc_html__( 'Team name', 'wegame-tournoi' ) . ' *<br /><input type="text" class="regular-text" name="name" required value="' . esc_attr( $val( 'name' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Tag', 'wegame-tournoi' ) . '<br /><input type="text" class="regular-text" name="tag" value="' . esc_attr( $val( 'tag' ) ) . '" /></label></p>';

		echo '<p><label>' . esc_html(
			sprintf(
				/* translators: %d: nombre d'équipes */
				__( 'Seed in the bracket (1 to %d)', 'wegame-tournoi' ),
				$max
			)
		) . '<br /><select name="seed">';
		$current_seed = $editing ? (int) $editing['seed'] : 0;
		echo '<option value="0"' . selected( $current_seed, 0, false ) . '>' . esc_html__( '— unseeded —', 'wegame-tournoi' ) . '</option>';
		for ( $i = 1; $i <= $max; $i++ ) {
			echo '<option value="' . (int) $i . '"' . selected( $current_seed, $i, false ) . '>' . esc_html( $i ) . '</option>';
		}
		echo '</select></label></p>';

		if ( 'groups' === WGT_Tournament::get( $tid, 'format' ) && 'manual' === WGT_Tournament::get( $tid, 'group_mode' ) ) {
			$group_count = (int) WGT_Tournament::get( $tid, 'group_count' );
			echo '<p><label>' . esc_html__( 'Group', 'wegame-tournoi' ) . '<br /><select name="group_id">';
			echo '<option value="0"' . selected( (int) $val( 'group_id' ), 0, false ) . '>' . esc_html__( '— unassigned —', 'wegame-tournoi' ) . '</option>';
			for ( $g = 1; $g <= $group_count; $g++ ) {
				echo '<option value="' . (int) $g . '"' . selected( (int) $val( 'group_id' ), $g, false ) . '>' . esc_html( WGT_Data::group_name( $g ) ) . '</option>';
			}
			echo '</select></label></p>';
		}

		echo '<p><label>' . esc_html__( 'Captain', 'wegame-tournoi' ) . '<br /><input type="text" class="regular-text" name="captain" value="' . esc_attr( $val( 'captain' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Email', 'wegame-tournoi' ) . '<br /><input type="email" class="regular-text" name="email" value="' . esc_attr( $val( 'email' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Phone', 'wegame-tournoi' ) . '<br /><input type="text" class="regular-text" name="phone" value="' . esc_attr( $val( 'phone' ) ) . '" /></label></p>';
		echo '<p><label>' . esc_html__( 'Players (one per line)', 'wegame-tournoi' ) . '<br /><textarea name="players" rows="5" class="large-text">' . esc_textarea( $val( 'players' ) ) . '</textarea></label></p>';
		echo '<p><label>' . esc_html__( 'Internal notes', 'wegame-tournoi' ) . '<br /><textarea name="notes" rows="3" class="large-text">' . esc_textarea( $val( 'notes' ) ) . '</textarea></label></p>';

		echo '<p><label>' . esc_html__( 'Status', 'wegame-tournoi' ) . '<br /><select name="status">';
		$current_status = $editing ? $editing['status'] : 'active';
		foreach ( $status_labels as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $current_status, $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select><br /><span class="description">' . esc_html__( 'Only approved teams appear in the bracket.', 'wegame-tournoi' ) . '</span></label></p>';

		echo '<p><button class="button button-primary">' . esc_html__( 'Save', 'wegame-tournoi' ) . '</button> ';
		if ( $editing ) {
			echo '<a class="button" href="' . esc_url( add_query_arg( array( 'page' => 'wgt-teams', 'tournament' => $tid ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Cancel', 'wegame-tournoi' ) . '</a>';
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
		echo '<button class="button" name="tool" value="validate_all">' . esc_html__( 'Approve pending sign-ups', 'wegame-tournoi' ) . '</button> ';
		echo '<button class="button" name="tool" value="autoseed">' . esc_html__( 'Random draw for the free seeds', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '<table class="widefat striped"><thead><tr>';
		echo '<th>' . esc_html__( 'Seed', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Team', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Captain', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Contact', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Status', 'wegame-tournoi' ) . '</th>';
		echo '<th>' . esc_html__( 'Actions', 'wegame-tournoi' ) . '</th>';
		echo '</tr></thead><tbody>';

		if ( empty( $teams ) ) {
			echo '<tr><td colspan="6">' . esc_html__( 'No team in this tournament.', 'wegame-tournoi' ) . '</td></tr>';
		}

		foreach ( $teams as $team ) {
			echo '<tr>';
			echo '<td>' . ( (int) $team['seed'] ? esc_html( (int) $team['seed'] ) : '—' ) . '</td>';
			echo '<td><strong>' . esc_html( $team['name'] ) . '</strong>' . ( '' !== $team['tag'] ? ' <span class="wgt-tag">[' . esc_html( $team['tag'] ) . ']</span>' : '' ) . '</td>';
			echo '<td>' . esc_html( $team['captain'] ) . '</td>';
			echo '<td>' . esc_html( trim( $team['email'] . ' ' . $team['phone'] ) ) . '</td>';
			echo '<td><span class="wgt-status wgt-status--' . esc_attr( $team['status'] ) . '">' . esc_html( isset( $status_labels[ $team['status'] ] ) ? $status_labels[ $team['status'] ] : $team['status'] ) . '</span></td>';
			echo '<td>';
			echo '<a class="button button-small" href="' . esc_url( add_query_arg( array( 'page' => 'wgt-teams', 'tournament' => $tid, 'edit' => (int) $team['id'] ), admin_url( 'admin.php' ) ) ) . '">' . esc_html__( 'Edit', 'wegame-tournoi' ) . '</a> ';
			echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline">';
			wp_nonce_field( 'wgt_delete_team' );
			echo '<input type="hidden" name="action" value="wgt_delete_team" />';
			echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
			echo '<input type="hidden" name="id" value="' . (int) $team['id'] . '" />';
			echo '<button class="button button-small button-link-delete" onclick="return confirm(\'' . esc_js( __( 'Delete this team?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Delete', 'wegame-tournoi' ) . '</button>';
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
			self::no_tournament( __( 'Matches & scores', 'wegame-tournoi' ) );
			return;
		}

		$settings = WGT_Tournament::settings( $tid );
		$rounds   = WGT_Data::get_matches_by_round( $tid );
		$teams    = WGT_Data::get_teams_map( $tid );
		$labels   = WGT_Bracket::round_order( $settings );
		$games    = WGT_Data::get_all_games( $tid );

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Matches & scores', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-matches' );
		echo '<p class="description">' . esc_html__( 'Confirm a match to send the winner to the next round automatically. Editing a result already confirmed resets the following matches involved.', 'wegame-tournoi' ) . '</p>';

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
				echo '<input type="hidden" name="match_id" value="' . (int) $id . '" />';

				echo '<div class="wgt-match-form__head">';
				echo '<span class="wgt-code">' . esc_html( $match['code'] ) . '</span> ';
				echo '<span class="wgt-vs"><strong>' . esc_html( $n1 ) . '</strong> vs <strong>' . esc_html( $n2 ) . '</strong></span> ';
				echo '<span class="wgt-bo">BO' . (int) $bo . '</span>';
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
						echo '<label>' . esc_html( sprintf( __( 'Game %d', 'wegame-tournoi' ), $g + 1 ) ) . '</label>';
						echo '<input type="number" min="0" name="games[' . (int) $g . '][score1]" value="' . esc_attr( $s1 ) . '" /> - ';
						echo '<input type="number" min="0" name="games[' . (int) $g . '][score2]" value="' . esc_attr( $s2 ) . '" />';
						echo '<input type="text" class="wgt-map" name="games[' . (int) $g . '][map]" value="' . esc_attr( $mp ) . '" placeholder="' . esc_attr__( 'Map / mode', 'wegame-tournoi' ) . '" />';
						echo '</span>';
					}
					echo '</div>';
					echo '<p class="description">' . esc_html(
						sprintf(
							/* translators: 1: format BO, 2: manches à gagner */
							__( 'Leave unplayed games empty. In BO%1$d, the winner must win %2$d of them.', 'wegame-tournoi' ),
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

				echo '<span class="wgt-field-inline"><label>' . esc_html__( 'Stations', 'wegame-tournoi' ) . '</label><input type="text" size="5" name="stations" value="' . esc_attr( $match['stations'] ) . '" /></span>';
				echo '<span class="wgt-field-inline"><label>' . esc_html__( 'Start', 'wegame-tournoi' ) . '</label><input type="text" size="5" name="start_time" value="' . esc_attr( $match['start_time'] ) . '" /></span>';
				echo '<span class="wgt-field-inline"><label>' . esc_html__( 'Referee', 'wegame-tournoi' ) . '</label><input type="text" name="referee" value="' . esc_attr( $match['referee'] ) . '" /></span>';

				echo '<span class="wgt-field-inline"><label>' . esc_html__( 'Status', 'wegame-tournoi' ) . '</label><select name="status">';
				foreach ( array( 'pending', 'live', 'done' ) as $status ) {
					echo '<option value="' . esc_attr( $status ) . '"' . selected( $match['status'], $status, false ) . '>' . esc_html( WGT_Render::status_label( $status ) ) . '</option>';
				}
				echo '</select></span>';

				echo '<button class="button button-primary">' . esc_html__( 'Save', 'wegame-tournoi' ) . '</button>';
				echo '</div>';
				echo '</form>';
			}
		}

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button button-link-delete" name="tool" value="reset_results" onclick="return confirm(\'' . esc_js( __( 'Reset every result to zero?', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Reset all results', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '</div>';
	}

	/**
	 * Planning + feuille de scores imprimable.
	 */
	public static function page_planning() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Schedule', 'wegame-tournoi' ) );
			return;
		}

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Schedule & score sheet', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-planning' );
		echo '<p><button class="button" onclick="window.print();return false;">' . esc_html__( 'Print', 'wegame-tournoi' ) . '</button></p>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_tools' );
		echo '<input type="hidden" name="action" value="wgt_tools" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<button class="button" name="tool" value="reschedule" onclick="return confirm(\'' . esc_js( __( 'Recalculate every time and station from the settings? Manual adjustments will be lost.', 'wegame-tournoi' ) ) . '\');">' . esc_html__( 'Recalculate the schedule', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '<div class="wgt-print">';
		echo WGT_Render::view( 'planning', array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendu HTML complet, échappement effectué dans les gabarits de WGT_Render.

		$matches = WGT_Data::get_matches( $tid );
		$teams   = WGT_Data::get_teams_map( $tid );

		echo '<h2>' . esc_html__( 'Score sheet', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'Match', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Team', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Score', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Team', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Score', 'wegame-tournoi' ) . '</th><th>' . esc_html__( 'Referee', 'wegame-tournoi' ) . '</th></tr></thead><tbody>';
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

		echo '<h2>' . esc_html__( 'Pre-opening checklist', 'wegame-tournoi' ) . '</h2>';
		echo WGT_Render::view( 'checklist', array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendu HTML complet, échappement effectué dans les gabarits de WGT_Render.
		echo '<h2>' . esc_html__( 'Staff', 'wegame-tournoi' ) . '</h2>';
		echo WGT_Render::view( 'staff', array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.Security.EscapeOutput -- Rendu HTML complet, échappement effectué dans les gabarits de WGT_Render.
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Aperçu du rendu public.
	 */
	public static function page_preview() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Public preview', 'wegame-tournoi' ) );
			return;
		}

		$views = array(
			'full'         => __( 'Full page (tabs)', 'wegame-tournoi' ),
			'bracket'      => __( 'Bracket', 'wegame-tournoi' ),
			'planning'     => __( 'Schedule', 'wegame-tournoi' ),
			'results'      => __( 'Results', 'wegame-tournoi' ),
			'ranking'      => __( 'Ranking', 'wegame-tournoi' ),
			'teams'        => __( 'Teams', 'wegame-tournoi' ),
			'rules'        => __( 'Rules', 'wegame-tournoi' ),
			'registration' => __( 'Sign-up', 'wegame-tournoi' ),
			'list'         => __( 'Tournament list', 'wegame-tournoi' ),
		);

		$widths = array(
			'desktop' => array( __( 'Desktop', 'wegame-tournoi' ), '100%' ),
			'tablet'  => array( __( 'Tablet', 'wegame-tournoi' ), '768px' ),
			'mobile'  => array( __( 'Mobile', 'wegame-tournoi' ), '390px' ),
		);

		$view  = isset( $_GET['view'] ) ? sanitize_key( wp_unslash( $_GET['view'] ) ) : 'full'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.
		$width = isset( $_GET['w'] ) ? sanitize_key( wp_unslash( $_GET['w'] ) ) : 'desktop'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Lecture d'affichage sans effet de bord.

		if ( ! isset( $views[ $view ] ) ) {
			$view = 'full';
		}
		if ( ! isset( $widths[ $width ] ) ) {
			$width = 'desktop';
		}

		$base = add_query_arg( array( 'page' => 'wgt-preview', 'tournament' => $tid ), admin_url( 'admin.php' ) );

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Public preview', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-preview' );
		echo '<p class="description">' . esc_html__( 'Exactly what visitors will see. The theme wrapper (menu, footer) is not reproduced here.', 'wegame-tournoi' ) . '</p>';

		echo '<div class="wgt-preview-bar">';

		echo '<span class="wgt-preview-group"><strong>' . esc_html__( 'Displayed view', 'wegame-tournoi' ) . '</strong>';
		foreach ( $views as $key => $label ) {
			$url = add_query_arg( array( 'view' => $key, 'w' => $width ), $base );
			echo '<a class="button button-small' . ( $key === $view ? ' button-primary' : '' ) . '" href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a> ';
		}
		echo '</span>';

		echo '<span class="wgt-preview-group"><strong>' . esc_html__( 'Width', 'wegame-tournoi' ) . '</strong>';
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
		echo 'title="' . esc_attr__( 'Preview of the public display', 'wegame-tournoi' ) . '" loading="lazy"></iframe>';
		echo '</div>';

		echo '</div>';
	}

	/**
	 * Réglages propres au tournoi.
	 */
	public static function page_tournament() {
		$tid = WGT_Tournament::current_admin();

		if ( ! $tid ) {
			self::no_tournament( __( 'Tournament settings', 'wegame-tournoi' ) );
			return;
		}

		$s = WGT_Tournament::settings( $tid );

		echo '<div class="wrap wgt-admin">';
		echo '<h1>' . esc_html__( 'Tournament settings', 'wegame-tournoi' ) . '</h1>';
		self::notice();
		self::switcher( $tid, 'wgt-tournament' );

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_save_tournament' );
		echo '<input type="hidden" name="action" value="wgt_save_tournament" />';
		echo '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		echo '<input type="hidden" name="wgt_checkbox_scope" value="third_place,bracket_reset,show_rules,show_staff,registration_open,auto_content,public_page" />';

		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row">' . esc_html__( 'Name', 'wegame-tournoi' ) . '</th><td>';
		echo '<strong>' . esc_html( $s['tournament_name'] ) . '</strong> ';
		echo '<a class="button button-small" href="' . esc_url( get_edit_post_link( $tid ) ) . '">' . esc_html__( 'Rename', 'wegame-tournoi' ) . '</a>';
		echo '<p class="description">' . esc_html__( 'The name and the page address are changed from the tournament page.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		$text_fields = array(
			'game_name' => __( 'Game', 'wegame-tournoi' ),
			'subtitle'  => __( 'Subtitle', 'wegame-tournoi' ),
			'venue'     => __( 'Venue', 'wegame-tournoi' ),
		);
		foreach ( $text_fields as $key => $label ) {
			echo '<tr><th scope="row"><label for="wgt-' . esc_attr( $key ) . '">' . esc_html( $label ) . '</label></th>';
			echo '<td><input type="text" class="regular-text" id="wgt-' . esc_attr( $key ) . '" name="' . esc_attr( $key ) . '" value="' . esc_attr( $s[ $key ] ) . '" /></td></tr>';
		}

		echo '<tr><th scope="row"><label for="wgt-event_date">' . esc_html__( 'Date', 'wegame-tournoi' ) . '</label></th>';
		echo '<td><input type="date" id="wgt-event_date" name="event_date" value="' . esc_attr( $s['event_date'] ) . '" /></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Times', 'wegame-tournoi' ) . '</th><td>';
		echo '<label>' . esc_html__( 'Start', 'wegame-tournoi' ) . ' <input type="time" name="start_time" value="' . esc_attr( $s['start_time'] ) . '" /></label> ';
		echo '<label>' . esc_html__( 'Expected end time', 'wegame-tournoi' ) . ' <input type="time" name="end_time" value="' . esc_attr( $s['end_time'] ) . '" /></label>';
		echo '<p class="description">' . esc_html__( 'Changing the start time shifts the whole schedule automatically.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Gaming stations', 'wegame-tournoi' ) . '</th>';
		echo '<td><input type="number" min="1" name="stations" value="' . esc_attr( (int) $s['stations'] ) . '" class="small-text" /></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Players per team', 'wegame-tournoi' ) . '</th>';
		echo '<td><input type="number" min="1" name="players_per_team" value="' . esc_attr( (int) $s['players_per_team'] ) . '" class="small-text" /></td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Accent color', 'wegame-tournoi' ) . '</th>';
		echo '<td><input type="color" name="accent_color" value="' . esc_attr( $s['accent_color'] ) . '" /></td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Format', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="form-table"><tbody>';

		$formats = array(
			'single' => __( 'Single elimination', 'wegame-tournoi' ),
			'double' => __( 'Double elimination (with losers bracket)', 'wegame-tournoi' ),
			'groups' => __( 'Group stage then playoffs', 'wegame-tournoi' ),
		);

		echo '<tr><th scope="row">' . esc_html__( 'Tournament format', 'wegame-tournoi' ) . '</th><td>';
		echo '<select name="format">';
		foreach ( $formats as $key => $label ) {
			echo '<option value="' . esc_attr( $key ) . '"' . selected( $s['format'], $key, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'In double elimination, a team is only eliminated after two losses. In groups, each team meets every other team of its group before the final phase.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Number of teams', 'wegame-tournoi' ) . '</th><td>';
		echo '<input type="number" min="2" max="64" name="team_count" value="' . esc_attr( (int) $s['team_count'] ) . '" class="small-text" />';
		echo '<p class="description">' . esc_html__( 'From 2 to 64. If the number is not a power of two, the top seeds get a bye in the first round.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr class="wgt-if-groups"><th scope="row">' . esc_html__( 'Groups', 'wegame-tournoi' ) . '</th><td>';
		echo '<label>' . esc_html__( 'Number of groups', 'wegame-tournoi' ) . ' <input type="number" min="2" max="8" name="group_count" value="' . esc_attr( (int) $s['group_count'] ) . '" class="small-text" /></label> ';
		echo '<label>' . esc_html__( 'Qualifiers per group', 'wegame-tournoi' ) . ' <input type="number" min="1" max="4" name="qualifiers_per_group" value="' . esc_attr( (int) $s['qualifiers_per_group'] ) . '" class="small-text" /></label><br /><br />';
		echo '<label>' . esc_html__( 'Make-up', 'wegame-tournoi' ) . ' <select name="group_mode">';
		echo '<option value="auto"' . selected( $s['group_mode'], 'auto', false ) . '>' . esc_html__( 'Automatic (snake order by seed)', 'wegame-tournoi' ) . '</option>';
		echo '<option value="manual"' . selected( $s['group_mode'], 'manual', false ) . '>' . esc_html__( 'Manual (group chosen for each team)', 'wegame-tournoi' ) . '</option>';
		echo '</select></label>';
		echo '<p class="description">' . esc_html__( 'With manual make-up, the group is chosen on each team\'s record. Qualified teams are crossed between groups either way.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		$bo_rounds = array(
			'bo_group' => __( 'Groups', 'wegame-tournoi' ),
			'bo_r64'   => __( 'R64', 'wegame-tournoi' ),
			'bo_r32'   => __( 'R32', 'wegame-tournoi' ),
			'bo_r16'   => __( 'R16', 'wegame-tournoi' ),
			'bo_qf'    => __( 'Quarters', 'wegame-tournoi' ),
			'bo_sf'    => __( 'Semis', 'wegame-tournoi' ),
			'bo_final' => __( 'Final', 'wegame-tournoi' ),
			'bo_lb'    => __( 'Losers bracket', 'wegame-tournoi' ),
		);

		echo '<tr><th scope="row">' . esc_html__( 'Number of games', 'wegame-tournoi' ) . '</th><td>';
		foreach ( $bo_rounds as $key => $label ) {
			echo '<label style="display:inline-block;margin:0 18px 8px 0">' . esc_html( $label ) . ' <select name="' . esc_attr( $key ) . '">';
			foreach ( array( 1, 3, 5 ) as $bo ) {
				echo '<option value="' . (int) $bo . '"' . selected( (int) $s[ $key ], $bo, false ) . '>BO' . (int) $bo . '</option>';
			}
			echo '</select></label>';
		}
		echo '<p class="description">' . esc_html__( 'Only the rounds actually present in the chosen format are used.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Third place match', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="third_place" value="1"' . checked( $s['third_place'], 1, false ) . ' /> ' . esc_html__( 'Pit the two semi-final losers against each other', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Not applicable in double elimination: the loser of the losers bracket final is third.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Bracket reset', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="bracket_reset" value="1"' . checked( $s['bracket_reset'], 1, false ) . ' /> ' . esc_html__( 'Replay the final if the team coming from the losers bracket wins it (« bracket reset »)', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Strict double elimination rule: the team coming from the losers bracket already has one loss, so it must inflict two. The match is only played if needed.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Schedule', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Time calculation', 'wegame-tournoi' ) . '</th><td>';
		echo '<label>' . esc_html__( 'Match length (min)', 'wegame-tournoi' ) . ' <input type="number" min="5" name="match_duration" value="' . esc_attr( (int) $s['match_duration'] ) . '" class="small-text" /></label> ';
		echo '<label>' . esc_html__( 'Simultaneous matches', 'wegame-tournoi' ) . ' <input type="number" min="1" name="matches_parallel" value="' . esc_attr( (int) $s['matches_parallel'] ) . '" class="small-text" /></label><br /><br />';
		echo '<label>' . esc_html__( 'Welcome time before the 1st match (min)', 'wegame-tournoi' ) . ' <input type="number" min="0" name="warmup_minutes" value="' . esc_attr( (int) $s['warmup_minutes'] ) . '" class="small-text" /></label> ';
		echo '<label>' . esc_html__( 'Break between rounds (min)', 'wegame-tournoi' ) . ' <input type="number" min="0" name="break_minutes" value="' . esc_attr( (int) $s['break_minutes'] ) . '" class="small-text" /></label>';
		echo '<p class="description">' . esc_html__( 'A BO3 counts as two slots, a BO5 as three. Times already set are not overwritten: use « Recalculate the schedule » below to regenerate them.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Display', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="form-table"><tbody>';
		echo '<tr><th scope="row">' . esc_html__( 'Tabs', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="show_rules" value="1"' . checked( $s['show_rules'], 1, false ) . ' /> ' . esc_html__( 'Rules', 'wegame-tournoi' ) . '</label><br />';
		echo '<label><input type="checkbox" name="show_staff" value="1"' . checked( $s['show_staff'], 1, false ) . ' /> ' . esc_html__( 'Organisation (staff)', 'wegame-tournoi' ) . '</label>';
		echo '</td></tr>';
		echo '<tr><th scope="row">' . esc_html__( 'Tournament page', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="public_page" value="1"' . checked( $s['public_page'], 1, false ) . ' /> ' . esc_html__( 'Dedicated public page', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Unchecked, the tournament page answers « not found » to visitors; the tournament is then shown only by the shortcodes placed on your own pages.', 'wegame-tournoi' ) . '</p>';
		echo '<label><input type="checkbox" name="auto_content" value="1"' . checked( $s['auto_content'], 1, false ) . ' /> ' . esc_html__( 'Show the tournament automatically on its page', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Uncheck if you prefer to build the page yourself with the shortcodes.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';
		echo '</tbody></table>';

		echo '<h2>' . esc_html__( 'Sign-ups', 'wegame-tournoi' ) . '</h2>';
		echo '<table class="form-table"><tbody><tr><th scope="row">' . esc_html__( 'Public form', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="registration_open" value="1"' . checked( $s['registration_open'], 1, false ) . ' /> ' . esc_html__( 'Open sign-ups for this tournament', 'wegame-tournoi' ) . '</label><br /><br />';
		echo '<label>' . esc_html__( 'Maximum number of teams', 'wegame-tournoi' ) . ' <input type="number" min="0" name="registration_max" value="' . esc_attr( (int) $s['registration_max'] ) . '" class="small-text" /></label><br /><br />';
		echo '<label>' . esc_html__( 'Notification email', 'wegame-tournoi' ) . ' <input type="email" class="regular-text" name="notify_email" value="' . esc_attr( $s['notify_email'] ) . '" placeholder="' . esc_attr( get_option( 'admin_email' ) ) . '" /></label>';
		echo '<p class="description">' . esc_html__( 'Notified on every sign-up. Empty: the site administration address. The captain gets an acknowledgement of his own.', 'wegame-tournoi' ) . '</p><br />';
		echo '<label>' . esc_html__( 'Confirmation message', 'wegame-tournoi' ) . '<br /><textarea name="registration_msg" rows="3" class="large-text">' . esc_textarea( $s['registration_msg'] ) . '</textarea></label>';
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
		echo '<h1>' . esc_html__( 'Plugin settings', 'wegame-tournoi' ) . '</h1>';
		self::notice();

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'wgt_save_settings' );
		echo '<input type="hidden" name="action" value="wgt_save_settings" />';
		echo '<input type="hidden" name="wgt_checkbox_scope" value="hide_page_title,delete_data_on_uninstall" />';
		echo '<table class="form-table"><tbody>';

		echo '<tr><th scope="row"><label for="wgt-plugin_locale">' . esc_html__( 'Language', 'wegame-tournoi' ) . '</label></th><td>';
		echo '<select id="wgt-plugin_locale" name="plugin_locale">';
		echo '<option value=""' . selected( $s['plugin_locale'], '', false ) . '>' . esc_html__( 'Same as the site', 'wegame-tournoi' ) . '</option>';
		echo '<option value="en_US"' . selected( $s['plugin_locale'], 'en_US', false ) . '>' . esc_html__( 'English', 'wegame-tournoi' ) . '</option>';
		foreach ( wgt_available_locales() as $code => $label ) {
			echo '<option value="' . esc_attr( $code ) . '"' . selected( $s['plugin_locale'], $code, false ) . '>' . esc_html( $label ) . '</option>';
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Language of the tournament screens and of the public page. Choose "Same as the site" to follow the site language.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Default tournament', 'wegame-tournoi' ) . '</th><td>';
		if ( empty( $all ) ) {
			echo '<em>' . esc_html__( 'No published tournament.', 'wegame-tournoi' ) . '</em>';
		} else {
			$default = (int) get_option( 'wgt_default_tournament' );
			echo '<select name="default_tournament">';
			echo '<option value="0">' . esc_html__( '— the most recent —', 'wegame-tournoi' ) . '</option>';
			foreach ( $all as $post ) {
				echo '<option value="' . (int) $post->ID . '"' . selected( $default, (int) $post->ID, false ) . '>' . esc_html( get_the_title( $post ) ) . '</option>';
			}
			echo '</select>';
			echo '<p class="description">' . esc_html__( 'Used by the shortcodes without a « tournoi » attribute.', 'wegame-tournoi' ) . '</p>';
		}
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Auto refresh', 'wegame-tournoi' ) . '</th>';
		echo '<td><input type="number" min="0" name="refresh_interval" value="' . esc_attr( (int) $s['refresh_interval'] ) . '" class="small-text" /> ' . esc_html__( 'seconds (0 to disable)', 'wegame-tournoi' ) . '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Page title', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="hide_page_title" value="1"' . checked( $s['hide_page_title'], 1, false ) . ' /> ' . esc_html__( 'Hide the theme title banner on tournament pages', 'wegame-tournoi' ) . '</label>';
		echo '<p><label>' . esc_html__( 'Additional CSS selectors', 'wegame-tournoi' ) . '<br />';
		echo '<input type="text" class="large-text code" name="hide_title_selector" value="' . esc_attr( $s['hide_title_selector'] ) . '" placeholder=".mon-theme-titre, #header-page" /></label></p>';
		echo '</td></tr>';

		echo '<tr><th scope="row"><label for="wgt-update_manifest_url">' . esc_html__( 'Update manifest URL', 'wegame-tournoi' ) . '</label></th><td>';
		echo '<input type="url" class="large-text code" id="wgt-update_manifest_url" name="update_manifest_url" value="' . esc_attr( $s['update_manifest_url'] ) . '" placeholder="https://exemple.fr/maj/wegame-tournoi.json" />';
		echo '<p class="description">' . esc_html__( 'Leave empty to disable automatic updates.', 'wegame-tournoi' ) . '</p>';

		$manifest = WGT_Updater::manifest();
		if ( $manifest ) {
			if ( version_compare( $manifest['version'], WGT_VERSION, '>' ) ) {
				echo '<p><strong>' . esc_html(
					sprintf(
						/* translators: 1: version disponible, 2: version installée */
						__( 'Version %1$s is available (you are running %2$s).', 'wegame-tournoi' ),
						$manifest['version'],
						WGT_VERSION
					)
				) . '</strong></p>';
			} else {
				echo '<p>' . esc_html(
					sprintf(
						/* translators: %s: version installée */
						__( 'Up to date (version %s).', 'wegame-tournoi' ),
						WGT_VERSION
					)
				) . '</p>';
			}
		}
		echo '</td></tr>';

		echo '<tr><th scope="row">' . esc_html__( 'Data on uninstall', 'wegame-tournoi' ) . '</th><td>';
		echo '<label><input type="checkbox" name="delete_data_on_uninstall" value="1"' . checked( $s['delete_data_on_uninstall'], 1, false ) . ' /> ' . esc_html__( 'Delete everything if the plugin is uninstalled', 'wegame-tournoi' ) . '</label>';
		echo '<p class="description">' . esc_html__( 'Unchecked (recommended), tournaments and their data are kept.', 'wegame-tournoi' ) . '</p>';
		echo '</td></tr>';

		echo '</tbody></table>';
		submit_button();
		echo '</form>';

		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_check_update' );
		echo '<input type="hidden" name="action" value="wgt_check_update" />';
		echo '<button class="button">' . esc_html__( 'Check for updates now', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '<h2>' . esc_html__( 'Emails', 'wegame-tournoi' ) . '</h2>';
		echo '<p class="description">' . esc_html__( 'Every public sign-up sends an acknowledgement to the captain and an alert to the tournament notification address (failing that, the site administration address). Delivery depends on your hosting: test it here.', 'wegame-tournoi' ) . '</p>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="wgt-inline-form">';
		wp_nonce_field( 'wgt_test_mail' );
		echo '<input type="hidden" name="action" value="wgt_test_mail" />';
		echo '<label>' . esc_html__( 'Send a test email to', 'wegame-tournoi' ) . ' <input type="email" name="to" class="regular-text" value="' . esc_attr( get_option( 'admin_email' ) ) . '" /></label> ';
		echo '<button class="button">' . esc_html__( 'Send', 'wegame-tournoi' ) . '</button>';
		echo '</form>';

		echo '</div>';
	}
}
