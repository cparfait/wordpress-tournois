<?php
/**
 * Création des tables, migrations et données par défaut.
 *
 * Le schéma est prévu dès maintenant pour les trois paliers de la feuille de
 * route (multi-tournois, formats variables, poules et double élimination),
 * afin d'éviter une nouvelle migration des données à chaque version.
 *
 * @package Brackethive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Brackethive_Install {

	/**
	 * Option marquant la reprise des données d'avant le renommage.
	 */
	const LEGACY_FLAG = 'brackethive_legacy_migrated';

	/**
	 * Nouvelles valeurs par défaut appliquées une seule fois aux
	 * installations existantes, indépendamment du numéro de version de base
	 * (une mise à niveau interrompue peut avoir enregistré ce numéro sans
	 * aller au bout).
	 */
	protected static function apply_new_defaults() {
		// Depuis 2.3.2, le bandeau de titre du thème est masqué par défaut
		// sur les pages de tournoi.
		if ( ! get_option( 'brackethive_defaults_232' ) ) {
			$settings = get_option( Brackethive_Settings::OPTION );
			if ( is_array( $settings ) ) {
				$settings['hide_page_title'] = 1;
				update_option( Brackethive_Settings::OPTION, $settings );
			}
			update_option( 'brackethive_defaults_232', 1 );
		}
	}

	/**
	 * Reprise des données d'avant le renommage de l'extension.
	 *
	 * Jusqu'à la version 2.5.0 l'extension stockait tout sous le préfixe
	 * « wgt_ », trop court au regard des règles du répertoire officiel. Les
	 * tables, options, métadonnées et le type de contenu sont donc renommés
	 * une fois pour toutes, sans perte : un site mis à jour retrouve ses
	 * tournois, ses équipes et ses réglages.
	 *
	 * Appelée avant toute lecture de données, et avant create_tables() : une
	 * table renommée ne doit pas être recréée vide à côté de l'ancienne.
	 */
	public static function migrate_legacy_prefix() {
		if ( get_option( self::LEGACY_FLAG ) ) {
			return;
		}

		global $wpdb;

		// Rien à reprendre sur une installation neuve : on ne vide alors ni le
		// cache d'objets ni les règles de réécriture.
		$moved = 0;

		// Tables. Renommées seulement si l'ancienne existe et la nouvelle non.
		foreach ( array( 'teams', 'matches', 'games' ) as $table ) {
			$old = $wpdb->prefix . 'wgt_' . $table;
			$new = $wpdb->prefix . 'brackethive_' . $table;

			$has_old = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) ) === $old; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration ponctuelle sur les tables de l'extension.
			$has_new = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $new ) ) === $new; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Migration ponctuelle sur les tables de l'extension.

			if ( $has_old && ! $has_new ) {
				$moved += (int) $wpdb->query( "RENAME TABLE `{$old}` TO `{$new}`" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Noms construits depuis $wpdb->prefix, sans donnée utilisateur ; migration ponctuelle non mise en cache.
			}
		}

		// Options. L'ancienne valeur n'est retirée qu'une fois recopiée.
		$options = array( 'settings', 'db_version', 'default_tournament', 'defaults_232', 'migrated_multi', 'welcome' );
		foreach ( $options as $name ) {
			$old   = 'wgt_' . $name;
			$value = get_option( $old, null );
			if ( null === $value || false === $value ) {
				continue;
			}
			if ( false === get_option( 'brackethive_' . $name, false ) ) {
				update_option( 'brackethive_' . $name, $value );
			}
			delete_option( $old );
			$moved++;
		}

		// Type de contenu des tournois.
		$moved += (int) $wpdb->update( $wpdb->posts, array( 'post_type' => 'brackethive_tournament' ), array( 'post_type' => 'wgt_tournament' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Renommage en masse, sans équivalent dans l'API ; migration ponctuelle.

		// Métadonnée de page d'inscription, et dernier tournoi choisi par
		// chaque utilisateur de l'administration.
		$moved += (int) $wpdb->update( $wpdb->postmeta, array( 'meta_key' => '_brackethive_registration_page' ), array( 'meta_key' => '_wgt_registration_page' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Renommage en masse, sans équivalent dans l'API ; migration ponctuelle.
		$moved += (int) $wpdb->update( $wpdb->usermeta, array( 'meta_key' => 'brackethive_current_tournament' ), array( 'meta_key' => 'wgt_current_tournament' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Renommage en masse, sans équivalent dans l'API ; migration ponctuelle.

		// Les transients sont des caches : ils se régénèrent, on les jette.
		$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\_transient\_wgt\_%' OR option_name LIKE '\_transient\_timeout\_wgt\_%'" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Purge des caches de l'ancien préfixe ; migration ponctuelle.

		update_option( self::LEGACY_FLAG, 1 );

		if ( ! $moved ) {
			return;
		}

		wp_cache_flush();

		// Les permaliens connaissent encore l'ancien type de contenu.
		add_option( 'brackethive_flush_rewrite', 1 );
	}

	/**
	 * Activation du plugin.
	 */
	public static function activate() {
		// Avant tout : reprendre les données d'une installation antérieure au
		// renommage, sinon create_tables() créerait des tables vides à côté.
		self::migrate_legacy_prefix();

		// Invitation affichée une fois : choix de la langue, premier tournoi.
		add_option( 'brackethive_welcome', 1 );

		self::create_tables();
		Brackethive_Settings::install_defaults();
		self::migrate();
		update_option( 'brackethive_db_version', BRACKETHIVE_DB_VERSION );

		// Les règles de réécriture ne connaissent le type de contenu que s'il
		// est déclaré avant leur régénération (à l'activation, « init » a déjà
		// eu lieu sans notre hook). register_post_type() est idempotent.
		Brackethive_Tournament::register();
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
	 * Dès que la version stockée diffère de BRACKETHIVE_DB_VERSION, dbDelta() est
	 * relancé : il ne fait qu'ajouter colonnes et index manquants, sans
	 * jamais supprimer de données.
	 */
	public static function maybe_upgrade() {
		self::apply_new_defaults();

		// Le renommage du type de contenu laisse des règles de réécriture
		// périmées : on les régénère au premier « init » qui suit.
		if ( get_option( 'brackethive_flush_rewrite' ) ) {
			delete_option( 'brackethive_flush_rewrite' );
			Brackethive_Tournament::register();
			flush_rewrite_rules();
		}

		if ( (string) get_option( 'brackethive_db_version' ) === (string) BRACKETHIVE_DB_VERSION ) {
			return;
		}

		self::create_tables();
		Brackethive_Settings::install_defaults();
		self::migrate();
		update_option( 'brackethive_db_version', BRACKETHIVE_DB_VERSION );

		/*
		 * Cette méthode est accrochée sur « init » (après la déclaration du
		 * type de contenu) et non sur « plugins_loaded » : à ce dernier
		 * moment, le moteur de réécriture ($wp_rewrite) n'existe pas encore
		 * et register_post_type() ou flush_rewrite_rules() provoquent une
		 * erreur fatale qui bloque tout le site.
		 */
		Brackethive_Tournament::register();
		flush_rewrite_rules();
	}

	/**
	 * Schéma des tables.
	 */
	public static function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$teams   = brackethive_table( 'teams' );
		$matches = brackethive_table( 'matches' );
		$games   = brackethive_table( 'games' );

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

		if ( get_option( 'brackethive_migrated_multi' ) ) {
			return;
		}

		$teams   = brackethive_table( 'teams' );
		$matches = brackethive_table( 'matches' );
		$games   = brackethive_table( 'games' );

		$orphans = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$matches} WHERE tournament_id = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; requête de migration sans donnée utilisateur ni mise en cache.
		$orphans += (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$teams} WHERE tournament_id = 0" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; requête de migration sans donnée utilisateur ni mise en cache.

		$legacy = get_option( 'brackethive_settings' );
		$legacy = is_array( $legacy ) ? $legacy : array();

		// Aucune donnée à reprendre : rien à migrer.
		if ( ! $orphans && empty( $legacy['tournament_name'] ) ) {
			update_option( 'brackethive_migrated_multi', 1 );
			return;
		}

		// Le type de contenu doit être déclaré avant d'insérer.
		Brackethive_Tournament::register();

		$title = ! empty( $legacy['tournament_name'] ) ? $legacy['tournament_name'] : __( 'Tournament', 'brackethive' );
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
				'post_type'   => Brackethive_Tournament::POST_TYPE,
				'post_title'  => $title,
				'post_status' => 'publish',
			),
			true
		);

		if ( is_wp_error( $post_id ) || ! $post_id ) {
			// On réessaiera au prochain chargement plutôt que de perdre le lien.
			return;
		}

		update_post_meta( $post_id, Brackethive_Tournament::META, array_merge( Brackethive_Tournament::defaults(), $settings ) );

		// Rattachement des données orphelines.
		$wpdb->query( $wpdb->prepare( "UPDATE {$teams} SET tournament_id = %d WHERE tournament_id = 0", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		$wpdb->query( $wpdb->prepare( "UPDATE {$matches} SET tournament_id = %d WHERE tournament_id = 0", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		$wpdb->query( $wpdb->prepare( "UPDATE {$games} SET tournament_id = %d WHERE tournament_id = 0", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.

		update_option( 'brackethive_default_tournament', $post_id );
		update_option( 'brackethive_migrated_multi', 1 );

		self::maybe_complete_bracket( $post_id );
	}

	/**
	 * Complète la structure des matchs migrés (src1/src2/round_no) si elle
	 * est absente, sans rien détruire.
	 *
	 * Brackethive_Data::ensure_bracket() met à jour les matchs dont le code existe
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
		if ( ! $post_id || ! class_exists( 'Brackethive_Data' ) || ! class_exists( 'Brackethive_Bracket' ) ) {
			return;
		}

		$matches = brackethive_table( 'matches' );

		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$matches} WHERE tournament_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		if ( ! $total ) {
			// Aucun match : ensure_bracket ne fait que créer, sans risque.
			Brackethive_Data::ensure_bracket( $post_id );
			return;
		}

		$filled = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$matches} WHERE tournament_id = %d AND ( src1 <> '' OR src2 <> '' OR round_no <> 0 )", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		if ( $filled > 0 ) {
			// Structure déjà renseignée : on ne touche à rien.
			return;
		}

		$existing = $wpdb->get_col( $wpdb->prepare( "SELECT code FROM {$matches} WHERE tournament_id = %d", $post_id ) ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; migration non mise en cache.
		$existing = is_array( $existing ) ? $existing : array();

		$wanted = array();
		foreach ( (array) Brackethive_Bracket::structure( Brackethive_Tournament::settings( $post_id ) ) as $def ) {
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

		Brackethive_Data::ensure_bracket( $post_id );
	}
}
