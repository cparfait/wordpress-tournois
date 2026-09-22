<?php
/**
 * Les tournois, en tant que contenus WordPress.
 *
 * Chaque tournoi est un contenu dédié : il dispose donc d'une page et d'une
 * URL propres, d'un titre, d'un extrait et de la corbeille, sans que nous
 * ayons à réécrire cette plomberie.
 *
 * @package Brackethive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Brackethive_Tournament {

	const POST_TYPE = 'brackethive_tournament';
	const META      = '_brackethive_settings';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'the_content', array( __CLASS__, 'append_to_content' ), 20 );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_hide_page' ) );

		// Suppression définitive depuis la corbeille WordPress, WP-CLI, REST…
		// Les données propres au tournoi doivent partir avec le contenu.
		add_action( 'before_delete_post', array( __CLASS__, 'on_before_delete_post' ), 10, 2 );
	}

	/**
	 * Déclaration du type de contenu.
	 */
	public static function register() {
		$labels = array(
			'name'               => __( 'Tournaments', 'brackethive' ),
			'singular_name'      => __( 'Tournament', 'brackethive' ),
			'add_new'            => __( 'Add', 'brackethive' ),
			'add_new_item'       => __( 'Add a tournament', 'brackethive' ),
			'edit_item'          => __( 'Edit tournament', 'brackethive' ),
			'new_item'           => __( 'New tournament', 'brackethive' ),
			'view_item'          => __( 'View tournament', 'brackethive' ),
			'search_items'       => __( 'Search a tournament', 'brackethive' ),
			'not_found'          => __( 'No tournament', 'brackethive' ),
			'not_found_in_trash' => __( 'No tournament in the trash', 'brackethive' ),
			'all_items'          => __( 'All tournaments', 'brackethive' ),
			'menu_name'          => __( 'Tournaments', 'brackethive' ),
		);

		register_post_type(
			self::POST_TYPE,
			array(
				'labels'       => $labels,
				'public'       => true,
				'has_archive'  => true,
				'menu_icon'    => 'dashicons-games',
				'show_in_menu' => false, // Rattaché à notre propre menu.
				'supports'     => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
				'rewrite'      => array( 'slug' => 'tournoi', 'with_front' => false ),
				'show_in_rest' => true,
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Réglages propres à un tournoi
	 * ------------------------------------------------------------------ */

	/**
	 * Valeurs par défaut.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'game_name'        => '',
			'subtitle'         => '',
			'event_date'       => '',
			'venue'            => '',
			'start_time'       => '19:00',
			'end_time'         => '00:00',
			'stations'         => 8,
			'players_per_team' => 4,
			'accent_color'     => '#e63946',

			// Format.
			'format'               => 'single',
			'team_count'           => 16,
			'group_count'          => 4,
			'qualifiers_per_group' => 2,
			'group_mode'           => 'auto',
			'bracket_reset'        => 0,
			'bo_group'             => 1,
			'bo_r64'               => 1,
			'bo_r32'               => 1,
			'bo_r16'               => 1,
			'bo_qf'                => 1,
			'bo_sf'                => 3,
			'bo_final'             => 3,
			'bo_lb'                => 1,
			'third_place'          => 0,

			// Planning.
			'match_duration'   => 30,
			'matches_parallel' => 2,
			'warmup_minutes'   => 15,
			'break_minutes'    => 10,

			// Affichage.
			'show_rules' => 1,
			'show_staff' => 0,

			// Inscriptions.
			'registration_open' => 0,
			'registration_max'  => 16,
			'registration_msg'  => 'Merci ! Votre inscription a bien été enregistrée, elle sera validée par l’organisation.',
			'notify_email'      => '',

			// Publication automatique sur la page du tournoi.
			'auto_content'      => 1,
			// 0 : pas de page publique dédiée, le tournoi ne s'affiche que
			// par les codes courts placés sur d'autres pages.
			'public_page'       => 1,
		);
	}

	/**
	 * Réglages d'un tournoi.
	 *
	 * @param int $tournament_id Identifiant.
	 * @return array
	 */
	public static function settings( $tournament_id ) {
		$saved = get_post_meta( (int) $tournament_id, self::META, true );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		$out = array_merge( self::defaults(), $saved );

		// Le nom du tournoi est le titre du contenu.
		$out['tournament_name'] = get_the_title( (int) $tournament_id );

		return $out;
	}

	/**
	 * Un réglage.
	 *
	 * @param int    $tournament_id Identifiant.
	 * @param string $key           Clé.
	 * @return mixed
	 */
	public static function get( $tournament_id, $key ) {
		$all = self::settings( $tournament_id );
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Enregistrement après nettoyage.
	 *
	 * @param int   $tournament_id Identifiant.
	 * @param array $input         Données brutes.
	 */
	public static function save_settings( $tournament_id, $input ) {
		$out = self::settings( $tournament_id );
		unset( $out['tournament_name'] );

		$text_keys = array( 'game_name', 'subtitle', 'venue', 'start_time', 'end_time', 'accent_color', 'event_date' );
		foreach ( $text_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = sanitize_text_field( $input[ $key ] );
			}
		}

		if ( isset( $input['registration_msg'] ) ) {
			$out['registration_msg'] = sanitize_textarea_field( $input['registration_msg'] );
		}
		if ( isset( $input['notify_email'] ) ) {
			$out['notify_email'] = sanitize_email( $input['notify_email'] );
		}

		$int_keys = array( 'stations', 'players_per_team', 'registration_max', 'match_duration', 'matches_parallel', 'warmup_minutes', 'break_minutes' );
		foreach ( $int_keys as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$out[ $key ] = max( 0, (int) $input[ $key ] );
			}
		}

		if ( isset( $input['format'] ) ) {
			$format        = sanitize_key( $input['format'] );
			$out['format'] = in_array( $format, array( 'single', 'double', 'groups' ), true ) ? $format : 'single';
		}

		if ( isset( $input['team_count'] ) ) {
			$count             = (int) $input['team_count'];
			$out['team_count'] = max( 2, min( 64, $count ) );
		}

		if ( isset( $input['group_mode'] ) ) {
			$mode              = sanitize_key( $input['group_mode'] );
			$out['group_mode'] = in_array( $mode, array( 'auto', 'manual' ), true ) ? $mode : 'auto';
		}

		if ( isset( $input['group_count'] ) ) {
			$out['group_count'] = max( 2, min( 8, (int) $input['group_count'] ) );
		}

		if ( isset( $input['qualifiers_per_group'] ) ) {
			$out['qualifiers_per_group'] = max( 1, min( 4, (int) $input['qualifiers_per_group'] ) );
		}

		foreach ( array( 'bo_group', 'bo_r64', 'bo_r32', 'bo_r16', 'bo_qf', 'bo_sf', 'bo_final', 'bo_lb' ) as $key ) {
			if ( isset( $input[ $key ] ) ) {
				$bo          = (int) $input[ $key ];
				$out[ $key ] = in_array( $bo, array( 1, 3, 5 ), true ) ? $bo : 1;
			}
		}

		$checkboxes = array( 'third_place', 'bracket_reset', 'show_rules', 'show_staff', 'registration_open', 'auto_content', 'public_page' );
		$scope      = $checkboxes;
		if ( isset( $input['brackethive_checkbox_scope'] ) ) {
			$declared = array_filter( array_map( 'sanitize_key', explode( ',', (string) $input['brackethive_checkbox_scope'] ) ) );
			$scope    = array_intersect( $checkboxes, $declared );
		}
		foreach ( $scope as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $out['accent_color'] ) ) {
			$out['accent_color'] = '#e63946';
		}

		update_post_meta( (int) $tournament_id, self::META, $out );
	}

	/* ---------------------------------------------------------------------
	 * Sélection
	 * ------------------------------------------------------------------ */

	/**
	 * Tous les tournois, du plus récent au plus ancien.
	 *
	 * @param array $args Arguments supplémentaires.
	 * @return array Tableau d'objets WP_Post.
	 */
	public static function all( $args = array() ) {
		$defaults = array(
			'post_type'        => self::POST_TYPE,
			'post_status'      => array( 'publish', 'draft', 'private', 'pending' ),
			'numberposts'      => 100,
			'orderby'          => 'date',
			'order'            => 'DESC',
			'suppress_filters' => false,
		);

		return get_posts( array_merge( $defaults, $args ) );
	}

	/**
	 * Le tournoi sur lequel travaille l'administration.
	 *
	 * Ordre de résolution : paramètre d'URL, puis dernier choix de
	 * l'utilisateur, puis tournoi le plus récent.
	 *
	 * @return int Identifiant, ou 0 si aucun tournoi n'existe.
	 */
	public static function current_admin() {
		$user_id = get_current_user_id();

		if ( isset( $_GET['tournament'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple sélection d'affichage dans l'administration, sans effet de bord.
			$id = (int) $_GET['tournament']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple sélection d'affichage dans l'administration, sans effet de bord ; valeur transtypée en entier.
			if ( self::exists( $id ) ) {
				if ( $user_id ) {
					update_user_meta( $user_id, 'brackethive_current_tournament', $id );
				}
				return $id;
			}
		}

		if ( $user_id ) {
			$saved = (int) get_user_meta( $user_id, 'brackethive_current_tournament', true );
			if ( $saved && self::exists( $saved ) ) {
				return $saved;
			}
		}

		return self::latest();
	}

	/**
	 * Le tournoi affiché par défaut côté public.
	 *
	 * Seuls les tournois publiés sont proposés aux visiteurs ; un utilisateur
	 * autorisé à modifier le tournoi par défaut le voit même en brouillon
	 * (prévisualisation).
	 *
	 * @return int
	 */
	public static function current_public() {
		$default = (int) get_option( 'brackethive_default_tournament' );
		if ( $default && self::is_visible( $default ) ) {
			return $default;
		}
		return self::latest( true );
	}

	/**
	 * Le tournoi le plus récent.
	 *
	 * @param bool $public_only Ne retenir que les tournois publiés (côté public).
	 * @return int
	 */
	public static function latest( $public_only = false ) {
		$args = array( 'numberposts' => 1 );
		if ( $public_only ) {
			$args['post_status'] = 'publish';
		}
		$posts = self::all( $args );
		return $posts ? (int) $posts[0]->ID : 0;
	}

	/**
	 * Résout un identifiant à partir d'un slug ou d'un identifiant.
	 *
	 * Par défaut, seuls les tournois visibles par l'utilisateur courant sont
	 * résolus (voir is_visible) : cette méthode reçoit des références fournies
	 * par des visiteurs (codes courts, REST).
	 *
	 * @param string|int $ref    Slug ou identifiant.
	 * @param bool       $public Restreindre aux tournois visibles (défaut).
	 * @return int
	 */
	public static function resolve( $ref, $public = true ) {
		$ref = trim( (string) $ref );

		if ( '' === $ref ) {
			return 0;
		}

		if ( ctype_digit( $ref ) ) {
			$id = (int) $ref;
		} else {
			// get_page_by_path ne filtre pas le statut : on vérifie ensuite.
			$post = get_page_by_path( sanitize_title( $ref ), OBJECT, self::POST_TYPE );
			$id   = $post ? (int) $post->ID : 0;
		}

		if ( ! $id ) {
			return 0;
		}

		if ( $public ) {
			return self::is_visible( $id ) ? $id : 0;
		}

		return self::exists( $id ) ? $id : 0;
	}

	/**
	 * Le tournoi existe-t-il (hors corbeille, tous statuts) ?
	 *
	 * Usage administration. Côté public, utiliser is_visible().
	 *
	 * @param int  $id     Identifiant.
	 * @param bool $public Exiger en plus la visibilité publique.
	 * @return bool
	 */
	public static function exists( $id, $public = false ) {
		if ( $public ) {
			return self::is_visible( $id );
		}
		$post = get_post( (int) $id );
		return $post && self::POST_TYPE === $post->post_type && 'trash' !== $post->post_status;
	}

	/**
	 * Le tournoi est-il visible par l'utilisateur courant ?
	 *
	 * Publié : visible de tous. Brouillon, privé, en attente : visible
	 * uniquement de qui peut le modifier (prévisualisation). Corbeille : jamais.
	 *
	 * @param int $id Identifiant.
	 * @return bool
	 */
	public static function is_visible( $id ) {
		$id   = (int) $id;
		$post = $id ? get_post( $id ) : null;

		if ( ! $post || self::POST_TYPE !== $post->post_type || 'trash' === $post->post_status ) {
			return false;
		}

		if ( 'publish' === $post->post_status ) {
			return true;
		}

		return is_user_logged_in() && current_user_can( 'edit_post', $id );
	}

	/**
	 * Création d'un tournoi.
	 *
	 * @param string $title    Titre.
	 * @param array  $settings Réglages initiaux.
	 * @return int|WP_Error
	 */
	public static function create( $title, $settings = array(), $status = 'publish' ) {
		$title = sanitize_text_field( $title );

		if ( '' === $title ) {
			return new WP_Error( 'brackethive_no_title', __( 'The tournament name is required.', 'brackethive' ) );
		}

		$id = wp_insert_post(
			array(
				'post_type'   => self::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'draft' === $status ? 'draft' : 'publish',
			),
			true
		);

		if ( is_wp_error( $id ) ) {
			return $id;
		}

		update_post_meta( $id, self::META, array_merge( self::defaults(), $settings ) );
		Brackethive_Data::ensure_bracket( $id );

		return (int) $id;
	}

	/**
	 * Duplication : reprend les réglages et les équipes, sans les résultats.
	 *
	 * @param int    $source_id Tournoi source.
	 * @param string $title     Titre du nouveau tournoi.
	 * @param bool   $with_teams Copier les équipes.
	 * @return int|WP_Error
	 */
	public static function duplicate( $source_id, $title, $with_teams = false ) {
		if ( ! self::exists( $source_id ) ) {
			return new WP_Error( 'brackethive_no_source', __( 'Source tournament not found.', 'brackethive' ) );
		}

		$settings = self::settings( $source_id );
		unset( $settings['tournament_name'] );

		// Une nouvelle édition repart sans date ni inscriptions ouvertes.
		$settings['event_date']        = '';
		$settings['registration_open'] = 0;

		$new_id = self::create( $title, $settings );

		if ( is_wp_error( $new_id ) ) {
			return $new_id;
		}

		if ( $with_teams ) {
			foreach ( Brackethive_Data::get_teams( array( 'tournament_id' => $source_id ) ) as $team ) {
				Brackethive_Data::save_team(
					array(
						'tournament_id' => $new_id,
						'name'          => $team['name'],
						'tag'           => $team['tag'],
						'seed'          => 0,
						'captain'       => $team['captain'],
						'email'         => $team['email'],
						'phone'         => $team['phone'],
						'players'       => $team['players'],
						'status'        => 'pending',
					)
				);
			}
		}

		return $new_id;
	}

	/**
	 * Suppression complète d'un tournoi et de ses données.
	 *
	 * @param int $id Identifiant.
	 */
	public static function delete( $id ) {
		$id = (int) $id;
		if ( ! $id ) {
			return;
		}

		// Le nettoyage des tables est fait par le hook before_delete_post ;
		// on ne le fait ici directement que si le hook n'est pas en place.
		if ( false === has_action( 'before_delete_post', array( __CLASS__, 'on_before_delete_post' ) ) ) {
			self::purge_data( $id );
		}

		wp_delete_post( $id, true );
	}

	/**
	 * Suppression définitive d'un contenu (corbeille vidée, WP-CLI, REST…).
	 *
	 * @param int          $post_id Identifiant.
	 * @param WP_Post|null $post    Contenu (depuis WP 5.5).
	 */
	public static function on_before_delete_post( $post_id, $post = null ) {
		if ( ! $post instanceof WP_Post ) {
			$post = get_post( $post_id );
		}
		if ( ! $post || self::POST_TYPE !== $post->post_type ) {
			return;
		}
		self::purge_data( (int) $post_id );
	}

	/**
	 * Supprime équipes, matchs, manches et références d'un tournoi.
	 *
	 * Le tournoi étant lui-même un contenu (sa page publique est son
	 * permalien), il n'y a pas de page séparée à supprimer.
	 *
	 * @param int $id Identifiant.
	 */
	protected static function purge_data( $id ) {
		global $wpdb;

		$id = (int) $id;
		if ( ! $id ) {
			return;
		}

		$wpdb->delete( brackethive_table( 'games' ), array( 'tournament_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		$wpdb->delete( brackethive_table( 'matches' ), array( 'tournament_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		$wpdb->delete( brackethive_table( 'teams' ), array( 'tournament_id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.

		// Références : tournoi par défaut et dernier choix des utilisateurs.
		if ( (int) get_option( 'brackethive_default_tournament' ) === $id ) {
			update_option( 'brackethive_default_tournament', 0 );
		}
		delete_metadata( 'user', 0, 'brackethive_current_tournament', $id, true );

		// Page d'inscription créée par l'extension : elle part avec le tournoi.
		$page_id = (int) get_post_meta( $id, '_brackethive_registration_page', true );
		if ( $page_id && get_post( $page_id ) ) {
			wp_delete_post( $page_id, true );
		}
	}

	/* ---------------------------------------------------------------------
	 * Affichage automatique sur la page du tournoi
	 * ------------------------------------------------------------------ */

	/**
	 * Ajoute le tableau sous le contenu de la page du tournoi.
	 *
	 * @param string $content Contenu.
	 * @return string
	 */
	/**
	 * Un tournoi « sans page dédiée » répond 404 aux visiteurs ; les
	 * administrateurs y accèdent toujours (aperçu).
	 */
	public static function maybe_hide_page() {
		if ( ! is_singular( self::POST_TYPE ) ) {
			return;
		}
		$id = get_queried_object_id();
		if ( ! $id || self::has_public_page( $id ) || current_user_can( 'edit_post', $id ) ) {
			return;
		}
		global $wp_query;
		$wp_query->set_404();
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Le tournoi dispose-t-il d'une page publique dédiée ?
	 *
	 * @param int $id Tournoi.
	 * @return bool
	 */
	/**
	 * Page WordPress d'inscription associée à un tournoi, si elle existe.
	 *
	 * @param int $id Tournoi.
	 * @return int Identifiant de page, ou 0.
	 */
	public static function registration_page( $id ) {
		$page_id = (int) get_post_meta( (int) $id, '_brackethive_registration_page', true );
		if ( $page_id && get_post( $page_id ) && 'trash' !== get_post_status( $page_id ) ) {
			return $page_id;
		}
		return 0;
	}

	/**
	 * Crée la page d'inscription d'un tournoi (code court simplifié).
	 *
	 * @param int    $id     Tournoi.
	 * @param string $status publish|draft.
	 * @return int|WP_Error
	 */
	public static function create_registration_page( $id, $status = 'publish' ) {
		$id = (int) $id;
		if ( ! $id || ! get_post( $id ) ) {
			return new WP_Error( 'brackethive_no_tournament', __( 'Tournament not found.', 'brackethive' ) );
		}

		$existing = self::registration_page( $id );
		if ( $existing ) {
			return $existing;
		}

		$slug    = get_post_field( 'post_name', $id );
		$page_id = wp_insert_post(
			array(
				'post_type'   => 'page',
				'post_status' => 'draft' === $status ? 'draft' : 'publish',
				'post_title'  => sprintf(
					/* translators: %s: nom du tournoi */
					__( 'Sign-up — %s', 'brackethive' ),
					get_the_title( $id )
				),
				'post_name'    => 'inscription-' . $slug,
				'post_content' => '[brackethive_inscription simple="yes" tournoi="' . $slug . '"]',
			),
			true
		);

		if ( is_wp_error( $page_id ) ) {
			return $page_id;
		}

		update_post_meta( $id, '_brackethive_registration_page', (int) $page_id );

		return (int) $page_id;
	}

	public static function has_public_page( $id ) {
		return (bool) self::get( (int) $id, 'public_page' );
	}

	public static function append_to_content( $content ) {
		if ( ! is_singular( self::POST_TYPE ) || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}

		$id = get_the_ID();

		if ( ! Brackethive_Tournament::get( $id, 'auto_content' ) ) {
			return $content;
		}

		// Si l'auteur a déjà placé un code court, on ne double pas l'affichage.
		foreach ( array_keys( Brackethive_Shortcodes::map() ) as $tag ) {
			if ( has_shortcode( $content, $tag ) ) {
				return $content;
			}
		}

		wp_enqueue_style( 'brackethive-public' );
		wp_enqueue_script( 'brackethive-public' );

		return $content . Brackethive_Render::view( 'full', array( 'tournament_id' => $id ) );
	}
}
