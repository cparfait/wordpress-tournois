<?php
/**
 * Sauvegarde et restauration du tournoi (JSON).
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_IO {

	const FORMAT = 1;

	/**
	 * Construit la sauvegarde complète.
	 *
	 * @return array
	 */
	public static function build_export( $tournament_id ) {
		$tid = (int) $tournament_id;

		return array(
			'format'      => self::FORMAT,
			'plugin'      => 'wegame-tournoi',
			'version'     => WGT_VERSION,
			'exported_at' => current_time( 'mysql' ),
			'site'        => home_url( '/' ),
			'tournament'  => array(
				'title'    => get_the_title( $tid ),
				'slug'     => get_post_field( 'post_name', $tid ),
				'settings' => WGT_Tournament::settings( $tid ),
			),
			'teams'       => WGT_Data::get_teams( array( 'tournament_id' => $tid ) ),
			'matches'     => WGT_Data::get_matches( $tid ),
			'games'       => self::flat_games( $tid ),
		);
	}

	/**
	 * Toutes les manches, à plat.
	 *
	 * @return array
	 */
	protected static function flat_games( $tournament_id ) {
		$out = array();
		foreach ( WGT_Data::get_all_games( $tournament_id ) as $rows ) {
			foreach ( $rows as $row ) {
				$out[] = $row;
			}
		}
		return $out;
	}

	/**
	 * Nom de fichier proposé.
	 *
	 * @return string
	 */
	public static function filename( $tournament_id = 0 ) {
		$slug = $tournament_id ? get_post_field( 'post_name', (int) $tournament_id ) : '';
		$slug = $slug ? $slug . '-' : '';
		return 'wegame-' . $slug . gmdate( 'Y-m-d-Hi', (int) current_time( 'timestamp' ) ) . '.json'; // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp -- Horodatage local volontaire : le nom de fichier doit refléter l'heure du site.
	}

	/**
	 * Restaure une sauvegarde.
	 *
	 * @param string $json          Contenu du fichier.
	 * @param bool   $with_settings Restaurer aussi les réglages.
	 * @return true|WP_Error
	 */
	public static function import( $json, $tournament_id, $with_settings = false ) {
		global $wpdb;

		$tid = (int) $tournament_id;
		if ( ! $tid || ! WGT_Tournament::exists( $tid ) ) {
			return new WP_Error( 'wgt_no_tournament', __( 'Target tournament not found.', 'wegame-tournoi' ) );
		}

		$data = json_decode( $json, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'wgt_bad_json', __( 'Unreadable file: this is not valid JSON.', 'wegame-tournoi' ) );
		}

		if ( ! isset( $data['plugin'] ) || 'wegame-tournoi' !== $data['plugin'] ) {
			return new WP_Error( 'wgt_bad_source', __( 'This file does not come from the We Game Tournoi plugin.', 'wegame-tournoi' ) );
		}

		if ( ! isset( $data['format'] ) || (int) $data['format'] > self::FORMAT ) {
			return new WP_Error( 'wgt_bad_format', __( 'This file was produced by a newer version of the plugin.', 'wegame-tournoi' ) );
		}

		if ( ! isset( $data['teams'], $data['matches'] ) || ! is_array( $data['teams'] ) || ! is_array( $data['matches'] ) ) {
			return new WP_Error( 'wgt_incomplete', __( 'Incomplete backup: teams or matches are missing.', 'wegame-tournoi' ) );
		}

		$games = isset( $data['games'] ) && is_array( $data['games'] ) ? $data['games'] : array();

		/*
		 * Validation complète AVANT toute suppression : chaque ligne doit être
		 * un tableau, chaque équipe avoir un nom, chaque match un code unique.
		 */
		$check = self::validate_rows( $data['teams'], $data['matches'], $games );
		if ( is_wp_error( $check ) ) {
			return $check;
		}

		$teams_table   = wgt_table( 'teams' );
		$matches_table = wgt_table( 'matches' );
		$games_table   = wgt_table( 'games' );

		/*
		 * Transaction : n'a d'effet qu'avec un moteur transactionnel (InnoDB,
		 * le défaut de MySQL depuis 5.5). Sous MyISAM, START TRANSACTION et
		 * ROLLBACK sont acceptés mais sans effet : c'est pourquoi la
		 * validation précède la suppression, et qu'un échec est signalé par
		 * un WP_Error explicite plutôt qu'un faux « Sauvegarde restaurée ».
		 */
		$wpdb->query( 'START TRANSACTION' ); // phpcs:ignore WordPress.DB -- Ordre transactionnel littéral sur les tables propres à l'extension ; rien à préparer ni à mettre en cache.

		// Table rase, pour ce tournoi uniquement.
		$wpdb->delete( $games_table, array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		$wpdb->delete( $teams_table, array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		$wpdb->delete( $matches_table, array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.

		/*
		 * Les identifiants du fichier ne sont jamais réutilisés (ils peuvent
		 * entrer en collision avec un autre tournoi). On insère sans id et on
		 * mémorise la correspondance ancien id => nouvel id pour recâbler les
		 * références (équipes des matchs, match des manches).
		 */
		$team_map  = array();
		$match_map = array();

		foreach ( $data['teams'] as $team ) {
			$old_id = isset( $team['id'] ) ? (int) $team['id'] : 0;
			if ( false === $wpdb->insert( $teams_table, self::clean_team( $team, $tid ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
				return self::fail( $wpdb, __( 'Restore aborted: a team could not be saved. No data was kept.', 'wegame-tournoi' ) );
			}
			if ( $old_id > 0 ) {
				$team_map[ $old_id ] = (int) $wpdb->insert_id;
			}
		}

		foreach ( $data['matches'] as $match ) {
			$old_id = isset( $match['id'] ) ? (int) $match['id'] : 0;
			if ( false === $wpdb->insert( $matches_table, self::clean_match( $match, $tid, $team_map ) ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
				return self::fail( $wpdb, __( 'Restore aborted: a match could not be saved. No data was kept.', 'wegame-tournoi' ) );
			}
			if ( $old_id > 0 ) {
				$match_map[ $old_id ] = (int) $wpdb->insert_id;
			}
		}

		foreach ( $games as $game ) {
			$row = self::clean_game( $game, $tid, $team_map, $match_map );
			if ( ! $row['match_id'] ) {
				// Manche orpheline (match inconnu) : ignorée.
				continue;
			}
			if ( false === $wpdb->insert( $games_table, $row ) ) { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
				return self::fail( $wpdb, __( 'Restore aborted: a game could not be saved. No data was kept.', 'wegame-tournoi' ) );
			}
		}

		$wpdb->query( 'COMMIT' ); // phpcs:ignore WordPress.DB -- Ordre transactionnel littéral sur les tables propres à l'extension ; rien à préparer ni à mettre en cache.

		if ( $with_settings && isset( $data['tournament']['settings'] ) && is_array( $data['tournament']['settings'] ) ) {
			WGT_Tournament::save_settings( $tid, $data['tournament']['settings'] );
		}

		// Au cas où la sauvegarde viendrait d'un tableau incomplet.
		WGT_Data::ensure_bracket( $tid );
		WGT_Data::recalculate( $tid );

		return true;
	}

	/**
	 * Annule la transaction et retourne l'erreur.
	 *
	 * @param wpdb   $wpdb    Connexion.
	 * @param string $message Message.
	 * @return WP_Error
	 */
	protected static function fail( $wpdb, $message ) {
		$wpdb->query( 'ROLLBACK' ); // phpcs:ignore WordPress.DB -- Ordre transactionnel littéral sur les tables propres à l'extension ; rien à préparer ni à mettre en cache.
		$detail = ! empty( $wpdb->last_error ) ? ' (' . $wpdb->last_error . ')' : '';
		return new WP_Error( 'wgt_import_failed', $message . $detail );
	}

	/**
	 * Vérifie la cohérence des lignes avant toute écriture.
	 *
	 * @param array $teams   Équipes.
	 * @param array $matches Matchs.
	 * @param array $games   Manches.
	 * @return true|WP_Error
	 */
	protected static function validate_rows( $teams, $matches, $games ) {
		foreach ( $teams as $i => $team ) {
			if ( ! is_array( $team ) ) {
				return new WP_Error( 'wgt_bad_team', sprintf( /* translators: %d: index */ __( 'Team no. %d is unreadable.', 'wegame-tournoi' ), (int) $i + 1 ) );
			}
			if ( ! isset( $team['name'] ) || ! is_scalar( $team['name'] ) || '' === trim( (string) $team['name'] ) ) {
				return new WP_Error( 'wgt_bad_team', sprintf( /* translators: %d: index */ __( 'Team no. %d has no name.', 'wegame-tournoi' ), (int) $i + 1 ) );
			}
		}

		$codes = array();
		foreach ( $matches as $i => $match ) {
			if ( ! is_array( $match ) ) {
				return new WP_Error( 'wgt_bad_match', sprintf( /* translators: %d: index */ __( 'Match no. %d is unreadable.', 'wegame-tournoi' ), (int) $i + 1 ) );
			}
			$code = isset( $match['code'] ) && is_scalar( $match['code'] ) ? sanitize_text_field( (string) $match['code'] ) : '';
			if ( '' === $code ) {
				return new WP_Error( 'wgt_bad_match', sprintf( /* translators: %d: index */ __( 'Match no. %d has no code.', 'wegame-tournoi' ), (int) $i + 1 ) );
			}
			if ( isset( $codes[ $code ] ) ) {
				return new WP_Error( 'wgt_bad_match', sprintf( /* translators: %s: code du match */ __( 'Duplicate match code "%s".', 'wegame-tournoi' ), $code ) );
			}
			$codes[ $code ] = true;
		}

		foreach ( $games as $i => $game ) {
			if ( ! is_array( $game ) ) {
				return new WP_Error( 'wgt_bad_game', sprintf( /* translators: %d: index */ __( 'Game no. %d is unreadable.', 'wegame-tournoi' ), (int) $i + 1 ) );
			}
		}

		return true;
	}

	/**
	 * Valeur texte d'une ligne.
	 *
	 * @param array  $row     Ligne.
	 * @param string $key     Clé.
	 * @param string $default Valeur par défaut.
	 * @return string
	 */
	protected static function text_of( $row, $key, $default = '' ) {
		return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? sanitize_text_field( (string) $row[ $key ] ) : $default;
	}

	/**
	 * Valeur entière (positive ou nulle) d'une ligne.
	 *
	 * @param array  $row     Ligne.
	 * @param string $key     Clé.
	 * @param int    $default Valeur par défaut.
	 * @return int
	 */
	protected static function int_of( $row, $key, $default = 0 ) {
		return isset( $row[ $key ] ) && is_scalar( $row[ $key ] ) ? max( 0, (int) $row[ $key ] ) : $default;
	}

	/**
	 * Valeur datetime MySQL d'une ligne ; sinon date courante.
	 *
	 * @param array  $row Ligne.
	 * @param string $key Clé.
	 * @return string
	 */
	protected static function datetime( $row, $key ) {
		if ( isset( $row[ $key ] ) && is_string( $row[ $key ] ) && preg_match( '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $row[ $key ] ) ) {
			return $row[ $key ];
		}
		return current_time( 'mysql' );
	}

	/**
	 * Recâble un identifiant d'après la table de correspondance.
	 *
	 * @param int   $old_id Ancien identifiant.
	 * @param array $map    ancien => nouveau.
	 * @return int 0 si la référence est inconnue.
	 */
	protected static function remap( $old_id, $map ) {
		$old_id = (int) $old_id;
		return ( $old_id > 0 && isset( $map[ $old_id ] ) ) ? (int) $map[ $old_id ] : 0;
	}

	/**
	 * Nettoie une ligne d'équipe (colonnes de wgt_teams, sans id).
	 *
	 * @param array $row Ligne brute.
	 * @param int   $tid Tournoi de destination.
	 * @return array
	 */
	protected static function clean_team( $row, $tid ) {
		$status = sanitize_key( self::text_of( $row, 'status', 'pending' ) );

		return array(
			'tournament_id' => (int) $tid,
			'name'          => self::text_of( $row, 'name' ),
			'tag'           => self::text_of( $row, 'tag' ),
			'seed'          => self::int_of( $row, 'seed' ),
			'group_id'      => self::int_of( $row, 'group_id' ),
			'captain'       => self::text_of( $row, 'captain' ),
			'email'         => isset( $row['email'] ) && is_scalar( $row['email'] ) ? sanitize_email( (string) $row['email'] ) : '',
			'phone'         => self::text_of( $row, 'phone' ),
			'players'       => isset( $row['players'] ) && is_scalar( $row['players'] ) ? sanitize_textarea_field( (string) $row['players'] ) : '',
			'notes'         => isset( $row['notes'] ) && is_scalar( $row['notes'] ) ? sanitize_textarea_field( (string) $row['notes'] ) : '',
			'status'        => '' !== $status ? $status : 'pending',
			'created_at'    => self::datetime( $row, 'created_at' ),
		);
	}

	/**
	 * Nettoie une ligne de match (colonnes de wgt_matches, sans id).
	 *
	 * @param array $row      Ligne brute.
	 * @param int   $tid      Tournoi de destination.
	 * @param array $team_map Correspondance des identifiants d'équipes.
	 * @return array
	 */
	protected static function clean_match( $row, $tid, $team_map = array() ) {
		$phase   = sanitize_key( self::text_of( $row, 'phase', 'bracket' ) );
		$bracket = strtoupper( sanitize_key( self::text_of( $row, 'bracket', 'W' ) ) );
		$status  = sanitize_key( self::text_of( $row, 'status', 'pending' ) );

		return array(
			'tournament_id' => (int) $tid,
			'code'          => self::text_of( $row, 'code' ),
			'phase'         => '' !== $phase ? $phase : 'bracket',
			'bracket'       => '' !== $bracket ? $bracket : 'W',
			'group_id'      => self::int_of( $row, 'group_id' ),
			'round'         => sanitize_key( self::text_of( $row, 'round' ) ),
			'round_no'      => self::int_of( $row, 'round_no' ),
			'position'      => self::int_of( $row, 'position' ),
			'bo'            => self::int_of( $row, 'bo', 1 ),
			'team1_id'      => self::remap( self::int_of( $row, 'team1_id' ), $team_map ),
			'team2_id'      => self::remap( self::int_of( $row, 'team2_id' ), $team_map ),
			'src1'          => self::text_of( $row, 'src1' ),
			'src2'          => self::text_of( $row, 'src2' ),
			'score1'        => self::int_of( $row, 'score1' ),
			'score2'        => self::int_of( $row, 'score2' ),
			'winner_id'     => self::remap( self::int_of( $row, 'winner_id' ), $team_map ),
			'stations'      => self::text_of( $row, 'stations' ),
			'referee'       => self::text_of( $row, 'referee' ),
			'start_time'    => self::text_of( $row, 'start_time' ),
			'end_time'      => self::text_of( $row, 'end_time' ),
			'status'        => '' !== $status ? $status : 'pending',
			'next_code'     => self::text_of( $row, 'next_code' ),
			'next_slot'     => self::int_of( $row, 'next_slot' ),
			'updated_at'    => self::datetime( $row, 'updated_at' ),
		);
	}

	/**
	 * Nettoie une ligne de manche (colonnes de wgt_games, sans id).
	 *
	 * @param array $row       Ligne brute.
	 * @param int   $tid       Tournoi de destination.
	 * @param array $team_map  Correspondance des identifiants d'équipes.
	 * @param array $match_map Correspondance des identifiants de matchs.
	 * @return array
	 */
	protected static function clean_game( $row, $tid, $team_map = array(), $match_map = array() ) {
		return array(
			'tournament_id' => (int) $tid,
			'match_id'      => self::remap( self::int_of( $row, 'match_id' ), $match_map ),
			'game_no'       => max( 1, self::int_of( $row, 'game_no', 1 ) ),
			'score1'        => self::int_of( $row, 'score1' ),
			'score2'        => self::int_of( $row, 'score2' ),
			'winner_id'     => self::remap( self::int_of( $row, 'winner_id' ), $team_map ),
			'map'           => self::text_of( $row, 'map' ),
		);
	}
}
