<?php
/**
 * Création des tables, migrations et données par défaut.
 *
 * Le schéma est prévu dès maintenant pour les trois paliers de la feuille de
 * route (multi-tournois, formats variables, poules et double élimination),
 * afin d'éviter une nouvelle migration des données à chaque version.
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Install {

	/**
	 * Nouvelles valeurs par défaut appliquées une seule fois aux
	 * installations existantes, indépendamment du numéro de version de base
	 * (une mise à niveau interrompue peut avoir enregistré ce numéro sans
	 * aller au bout).
	 */
	protected static function apply_new_defaults() {
		// Depuis 2.3.2, le bandeau de titre du thème est masqué par défaut
		// sur les pages de tournoi.
		if ( ! get_option( 'wgt_defaults_232' ) ) {
			$settings = get_option( WGT_Settings::OPTION );
			if ( is_array( $settings ) ) {
				$settings['hide_page_title'] = 1;
				update_option( WGT_Settings::OPTION, $settings );
			}
			update_option( 'wgt_defaults_232', 1 );
		}
	}

	/**
	 * Activation du plugin.
	 */
	public static function activate() {
		// Invitation affichée une fois : choix de la langue, premier tournoi.
		add_option( 'wgt_welcome', 1 );

		self::create_tables();
		WGT_Settings::install_defaults();
		self::migrate();
		update_option( 'wgt_db_version', WGT_DB_VERSION );

		// Les règles de réécriture ne connaissent le type de contenu que s'il
		// est déclaré avant leur régénération (à l'activation, « init » a déjà
		// eu lieu sans notre hook). register_post_type() est idempotent.
		WGT_Tournament::register();
		flush_rewrite_rules();
	}

	/**
	 * Désactivation.
	 */
	public static function deactivate() {
		flush_rewrite_rules();
	}

	/**
	 * Mise à jour du schéma si nécessaire.
	 *
	 * Dès que la version stockée diffère de WGT_DB_VERSION, dbDelta() est
	 * relancé : il ne fait qu'ajouter colonnes et index manquants, sans
	 * jamais supprimer de données.
	 */
	public static function maybe_upgrade() {
		self::apply_new_defaults();

		if ( (string) get_option( 'wgt_db_version' ) === (string) WGT_DB_VERSION ) {
			return;
		}

		self::create_tables();
		WGT_Settings::install_defaults();
		self::migrate();
		update_option( 'wgt_db_version', WGT_DB_VERSION );

		/*
		 * Cette méthode est accrochée sur « init » (après la déclaration du
		 * type de contenu) et non sur « plugins_loaded » : à ce dernier
		 * moment, le moteur de réécriture ($wp_rewrite) n'existe pas encore
		 * et register_post_type() ou flush_rewrite_rules() provoquent une
		 * erreur fatale qui bloque tout le site.
		 */
		WGT_Tournament::register();
		flush_rewrite_rules();
	}

	/**
	 * Schéma des tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$teams   = wgt_table( 'teams' );
		$matches = wgt_table( 'matches' );
		$games   = wgt_table( 'games' );

		$sql_teams = "CREATE TABLE {$teams} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) unsigned NOT NULL DEFAULT 0,
			name varchar(120) NOT NULL DEFAULT '',
			tag varchar(20) NOT NULL DEFAULT '',
			seed smallint(5) unsigned NOT NULL DEFAULT 0,
			group_id smallint(5) unsigned NOT NULL DEFAULT 0,
			captain varchar(120) NOT NULL DEFAULT '',
			email varchar(190) NOT NULL DEFAULT '',
			phone varchar(40) NOT NULL DEFAULT '',
			players text NULL,
			notes text NULL,
			status varchar(20) NOT NULL DEFAULT 'pending',
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY tournament (tournament_id),
			KEY tournament_seed (tournament_id,seed),
			KEY tournament_status (tournament_id,status)
		) {$charset};";

		$sql_matches = "CREATE TABLE {$matches} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) unsigned NOT NULL DEFAULT 0,
			code varchar(16) NOT NULL DEFAULT '',
			phase varchar(20) NOT NULL DEFAULT 'bracket',
			bracket varchar(10) NOT NULL DEFAULT 'W',
			group_id smallint(5) unsigned NOT NULL DEFAULT 0,
			round varchar(20) NOT NULL DEFAULT '',
			round_no smallint(5) unsigned NOT NULL DEFAULT 0,
			position smallint(5) unsigned NOT NULL DEFAULT 0,
			bo tinyint(3) unsigned NOT NULL DEFAULT 1,
			team1_id bigint(20) unsigned NOT NULL DEFAULT 0,
			team2_id bigint(20) unsigned NOT NULL DEFAULT 0,
			src1 varchar(32) NOT NULL DEFAULT '',
			src2 varchar(32) NOT NULL DEFAULT '',
			score1 smallint(5) unsigned NOT NULL DEFAULT 0,
			score2 smallint(5) unsigned NOT NULL DEFAULT 0,
			winner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			stations varchar(40) NOT NULL DEFAULT '',
			referee varchar(120) NOT NULL DEFAULT '',
			start_time varchar(10) NOT NULL DEFAULT '',
			end_time varchar(10) NOT NULL DEFAULT '',
			status varchar(20) NOT NULL DEFAULT 'pending',
			next_code varchar(16) NOT NULL DEFAULT '',
			next_slot tinyint(3) unsigned NOT NULL DEFAULT 0,
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			UNIQUE KEY tournament_code (tournament_id,code),
			KEY tournament (tournament_id),
			KEY tournament_round (tournament_id,round,position)
		) {$charset};";

		$sql_games = "CREATE TABLE {$games} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) unsigned NOT NULL DEFAULT 0,
			match_id bigint(20) unsigned NOT NULL DEFAULT 0,
			game_no tinyint(3) unsigned NOT NULL DEFAULT 1,
			score1 smallint(5) unsigned NOT NULL DEFAULT 0,
			score2 smallint(5) unsigned NOT NULL DEFAULT 0,
			winner_id bigint(20) unsigned NOT NULL DEFAULT 0,
			map varchar(120) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY match_game (match_id,game_no),
			KEY tournament (tournament_id)
		) {$charset};";

		dbDelta( $sql_teams );
		dbDelta( $sql_matches );
		dbDelta( $sql_games );
	}

	/**
	 * Migrations de données.
	 */
	public static function migrate() {
		self::migrate_to_multi_tournament();
	}

	/**
	 * Passage au multi-tournois.
	 *
	 * Les données existantes, qui n'étaient rattachées à rien, deviennent le
	 * premier tournoi, constitué à partir des réglages en place. Rien n'est
	 * supprimé ni ressaisi.
	 */
	protected static function migrate_to_multi_tournament() {
		global $wpdb;

		if ( get_option( 'wgt_migrated_multi' ) ) {
			return;
		}

		$teams   = wgt_table( 'teams' );
		$matches = wgt_table( 'matches' );
		$games   = wgt_table( 'games' );

		$orphans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$matches} WHERE tournament_id = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de wgt_table() ; requête de migration sans donnée utilisateur ni mise en cache.
		$orphans += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$teams} WHERE tournament_id = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de wgt_table() ; requête de migration sans donnée utilisateur ni mise en cache.

		$legacy = get_option( 'wgt_settings' );
		$legacy = is_array( $legacy ) ? $legacy : array();

		// Aucune donnée à reprendre : rien à migrer.
		if ( ! $orphans && empty( $legacy['tournament_name'] ) ) {
			update_option( 'wgt_migrated_multi', 1 );
			return;
		}

		// Le type de contenu doit être déclaré avant d'insérer.
		WGT_Tournament::register();

		$title = ! empty( $legacy['tournament_name'] ) ? $legacy['tournament_name'] : __( 'Tournament', 'wegame-tournoi' );
		if ( ! empty( $legacy['game_name'] ) ) {
			$title .= ' — ' . $legacy['game_name'];
		}

		$carry = array(
			'game_name', 'subtitle', 'event_date', 'venue', 'start_time', 'end_time',
			'stations', 'players_per_team', 'accent_color', 'show_rules', 'show_staff',
			'registration_open', 'registration_max', 'registration_msg', 'notify_email',
		);

		$settings = array();
		foreach ( $carry as $key ) {
			if ( isset( $legacy[ $key ] ) ) {
				$settings[ $key ] = $legacy[ $key ];
			}
		}

		$post_id = wp_insert_post(
			array(
				'post_type'   => WGT_Tournament::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			// On réessaiera au prochain chargement plutôt que de perdre le lien.
			return;
		}

		update_post_meta( $post_id, WGT_Tournament::META, array_merge( WGT_Tournament::defaults(), $settings ) );

		// Rattachement des données orphelines.
		$wpdb->query( $wpdb->prepare( "UPDATE {$teams} SET tournament_id = %d WHERE tournament_id = 0", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de wgt_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		$wpdb->query( $wpdb->prepare( "UPDATE {$matches} SET tournament_id = %d WHERE tournament_id = 0", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de wgt_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		$wpdb->query( $wpdb->prepare( "UPDATE {$games} SET tournament_id = %d WHERE tournament_id = 0", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de wgt_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.

		update_option( 'wgt_default_tournament', $post_id );
		update_option( 'wgt_migrated_multi', 1 );

		self::maybe_complete_bracket( $post_id );
	}

	/**
	 * Complète la structure des matchs migrés (src1/src2/round_no) si elle
	 * est absente, sans rien détruire.
	 *
	 * WGT_Data::ensure_bracket() met à jour les matchs dont le code existe
	 * (scores et équipes préservés) mais SUPPRIME ceux dont le code ne figure
	 * pas dans la structure attendue. On ne l'appelle donc que si :
	 *  - tous les matchs ont src1, src2 vides et round_no à 0 (données
	 *    d'avant les sources de tableau) ;
	 *  - et chaque code présent existe dans la structure calculée, afin
	 *    qu'aucun match ne soit perdu.
	 *
	 * @param int $post_id Tournoi migré.
	 */
	protected static function maybe_complete_bracket( $post_id ) {
		global $wpdb;

		$post_id = (int) $post_id;
		if ( ! $post_id || ! class_exists( 'WGT_Data' ) || ! class_exists( 'WGT_Bracket' ) ) {
			return;
		}

		$matches = wgt_table( 'matches' );

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$matches} WHERE tournament_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de wgt_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		if ( ! $total ) {
			// Aucun match : ensure_bracket ne fait que créer, sans risque.
			WGT_Data::ensure_bracket( $post_id );
			return;
		}

		$filled = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$matches} WHERE tournament_id = %d AND ( src1 <> '' OR src2 <> '' OR round_no <> 0 )", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de wgt_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		if ( $filled > 0 ) {
			// Structure déjà renseignée : on ne touche à rien.
			return;
		}

		$existing = $wpdb->get_col( $wpdb->prepare( "SELECT code FROM {$matches} WHERE tournament_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de wgt_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		$existing = is_array( $existing ) ? $existing : array();

		$wanted = array();
		foreach ( (array) WGT_Bracket::structure( WGT_Tournament::settings( $post_id ) ) as $def ) {
			if ( isset( $def['code'] ) ) {
				$wanted[] = (string) $def['code'];
			}
		}

		foreach ( $existing as $code ) {
			if ( ! in_array( (string) $code, $wanted, true ) ) {
				// Un match serait supprimé : on préfère laisser les données en l'état.
				return;
			}
		}

		WGT_Data::ensure_bracket( $post_id );
	}
}
