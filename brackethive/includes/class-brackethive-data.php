<?php
/**
 * Couche d'accès aux données + moteur de progression du tableau.
 *
 * Toutes les opérations sont rapportées à un tournoi.
 *
 * @package Brackethive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Brackethive_Data {

	/* ---------------------------------------------------------------------
	 * Équipes
	 * ------------------------------------------------------------------ */

	/**
	 * Liste des équipes d'un tournoi.
	 *
	 * @param array $args tournament_id, status, orderby.
	 * @return array
	 */
	public static function get_teams( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'tournament_id' => 0,
			'status'        => '',
			'orderby'       => 'seed',
		);
		$args  = array_merge( $defaults, $args );
		$table = brackethive_table( 'teams' );
		$tid   = (int) $args['tournament_id'];

		if ( ! $tid ) {
			return array();
		}

		$order = 'seed';
		if ( 'name' === $args['orderby'] ) {
			$order = 'name';
		} elseif ( 'created' === $args['orderby'] ) {
			$order = 'created_at';
		}

		if ( '' !== $args['status'] ) {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d AND status = %s ORDER BY (seed = 0), {$order} ASC, name ASC", // phpcs:ignore WordPress.DB.PreparedSQL -- Nom de table issu de brackethive_table() et clé de tri sur liste fermée ; les valeurs passent par $wpdb->prepare().
				$tid,
				$args['status']
			);
		} else {
			$sql = $wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d ORDER BY (seed = 0), {$order} ASC, name ASC", // phpcs:ignore WordPress.DB.PreparedSQL -- Nom de table issu de brackethive_table() et clé de tri sur liste fermée ; les valeurs passent par $wpdb->prepare().
				$tid
			);
		}

		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Équipes indexées par identifiant.
	 *
	 * @param int $tournament_id Tournoi.
	 * @return array
	 */
	public static function get_teams_map( $tournament_id ) {
		$map = array();
		foreach ( self::get_teams( array( 'tournament_id' => $tournament_id ) ) as $team ) {
			$map[ (int) $team['id'] ] = $team;
		}
		return $map;
	}

	/**
	 * Une équipe.
	 *
	 * @param int $id Identifiant.
	 * @return array|null
	 */
	public static function get_team( $id ) {
		global $wpdb;
		$table = brackethive_table( 'teams' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
		return $row ? $row : null;
	}

	/**
	 * Équipe positionnée sur une tête de série.
	 *
	 * @param int $tournament_id Tournoi.
	 * @param int $seed          1..n.
	 * @return array|null
	 */
	public static function get_team_by_seed( $tournament_id, $seed ) {
		global $wpdb;
		$table = brackethive_table( 'teams' );
		$row   = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d AND seed = %d AND status = 'active' LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL -- Nom de table issu de brackethive_table(), sans donnée utilisateur ; les valeurs passent par $wpdb->prepare().
				(int) $tournament_id,
				(int) $seed
			),
			ARRAY_A
		);
		return $row ? $row : null;
	}

	/**
	 * Création / mise à jour d'une équipe.
	 *
	 * @param array $data Données brutes, dont tournament_id.
	 * @return int|WP_Error
	 */
	public static function save_team( $data ) {
		global $wpdb;
		$table = brackethive_table( 'teams' );

		$id   = isset( $data['id'] ) ? (int) $data['id'] : 0;
		$name = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

		$tid = isset( $data['tournament_id'] ) ? (int) $data['tournament_id'] : 0;
		if ( ! $tid && $id ) {
			$existing_team = self::get_team( $id );
			$tid           = $existing_team ? (int) $existing_team['tournament_id'] : 0;
		}

		if ( ! $tid ) {
			return new WP_Error( 'brackethive_no_tournament', __( 'No tournament selected.', 'brackethive' ) );
		}

		if ( '' === $name ) {
			return new WP_Error( 'brackethive_no_name', __( 'The team name is required.', 'brackethive' ) );
		}

		$max  = (int) Brackethive_Tournament::get( $tid, 'team_count' );
		$max  = $max > 0 ? $max : 16;
		$seed = isset( $data['seed'] ) ? (int) $data['seed'] : 0;

		if ( $seed < 0 || $seed > $max ) {
			return new WP_Error(
				'brackethive_bad_seed',
				sprintf(
					/* translators: %d: nombre d'équipes du tournoi */
					__( 'The seed must be between 1 and %d (0 = unseeded).', 'brackethive' ),
					$max
				)
			);
		}

		// Une seule équipe par position, au sein du même tournoi.
		if ( $seed > 0 ) {
			$taken = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
				$wpdb->prepare(
					"SELECT id FROM {$table} WHERE tournament_id = %d AND seed = %d AND id <> %d", // phpcs:ignore WordPress.DB.PreparedSQL -- Nom de table issu de brackethive_table(), sans donnée utilisateur ; les valeurs passent par $wpdb->prepare().
					$tid,
					$seed,
					$id
				)
			);
			if ( $taken ) {
				return new WP_Error(
					'brackethive_seed_taken',
					sprintf(
						/* translators: %d: numéro de position */
						__( 'Seed #%d is already taken by another team.', 'brackethive' ),
						$seed
					)
				);
			}
		}

		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'pending';
		if ( ! in_array( $status, array( 'pending', 'active', 'rejected' ), true ) ) {
			$status = 'pending';
		}

		$row = array(
			'tournament_id' => $tid,
			'name'          => $name,
			'tag'           => isset( $data['tag'] ) ? sanitize_text_field( $data['tag'] ) : '',
			'seed'          => $seed,
			'group_id'      => isset( $data['group_id'] ) ? max( 0, (int) $data['group_id'] ) : 0,
			'captain'       => isset( $data['captain'] ) ? sanitize_text_field( $data['captain'] ) : '',
			'email'         => isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '',
			'phone'         => isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '',
			'players'       => isset( $data['players'] ) ? sanitize_textarea_field( $data['players'] ) : '',
			'notes'         => isset( $data['notes'] ) ? sanitize_textarea_field( $data['notes'] ) : '',
			'status'        => $status,
		);

		if ( $id > 0 ) {
			$wpdb->update( $table, $row, array( 'id' => $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		} else {
			$row['created_at'] = current_time( 'mysql' );
			$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
			$id = (int) $wpdb->insert_id;
		}

		// En composition manuelle des poules, les matchs de poule suivent
		// l'effectif de chaque poule.
		if ( self::structure_follows_teams( $tid ) ) {
			self::ensure_bracket( $tid );
		}

		self::recalculate( $tid );

		return $id;
	}

	/**
	 * Suppression d'une équipe.
	 *
	 * @param int $id Identifiant.
	 */
	public static function delete_team( $id ) {
		global $wpdb;

		$team = self::get_team( $id );
		if ( ! $team ) {
			return;
		}

		$wpdb->delete( brackethive_table( 'teams' ), array( 'id' => (int) $id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.

		$tid = (int) $team['tournament_id'];
		if ( self::structure_follows_teams( $tid ) ) {
			self::ensure_bracket( $tid );
		}
		self::recalculate( $tid );
	}

	/**
	 * Nombre d'équipes occupant une place : en attente ou validées.
	 * Les équipes refusées libèrent leur place.
	 *
	 * @param int $tournament_id Tournoi.
	 * @return int
	 */
	public static function count_registered( $tournament_id ) {
		global $wpdb;

		$tid = (int) $tournament_id;
		if ( ! $tid ) {
			return 0;
		}

		$table = brackethive_table( 'teams' );

		return (int) $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
			$wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE tournament_id = %d AND status <> 'rejected'", $tid ) // phpcs:ignore WordPress.DB.PreparedSQL -- Nom de table issu de brackethive_table(), sans donnée utilisateur ; les valeurs passent par $wpdb->prepare().
		);
	}

	/* ---------------------------------------------------------------------
	 * Matchs
	 * ------------------------------------------------------------------ */

	/**
	 * Crée ou met à jour les matchs d'un tournoi d'après sa configuration.
	 *
	 * Les résultats déjà saisis sont préservés ; seuls les paramètres de
	 * structure (nombre de manches, horaires, postes) sont resynchronisés.
	 *
	 * @param int $tournament_id Tournoi.
	 */
	public static function ensure_bracket( $tournament_id ) {
		global $wpdb;

		$tid = (int) $tournament_id;
		if ( ! $tid ) {
			return;
		}

		$table    = brackethive_table( 'matches' );
		$settings = self::structure_settings( $tid );
		$wanted   = array();

		foreach ( Brackethive_Bracket::structure( $settings ) as $def ) {
			$wanted[] = $def['code'];

			$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
				$wpdb->prepare( "SELECT id FROM {$table} WHERE tournament_id = %d AND code = %s", $tid, $def['code'] ) // phpcs:ignore WordPress.DB.PreparedSQL -- Nom de table issu de brackethive_table(), sans donnée utilisateur ; les valeurs passent par $wpdb->prepare().
			);

			$row = array(
				'tournament_id' => $tid,
				'code'          => $def['code'],
				'phase'         => isset( $def['phase'] ) ? $def['phase'] : 'bracket',
				'bracket'       => isset( $def['bracket'] ) ? $def['bracket'] : 'W',
				'group_id'      => isset( $def['group_id'] ) ? (int) $def['group_id'] : 0,
				'round'         => $def['round'],
				'round_no'      => $def['round_no'],
				'position'      => $def['position'],
				'bo'            => $def['bo'],
				'src1'          => $def['src1'],
				'src2'          => $def['src2'],
			);

			if ( $exists ) {
				/*
				 * Horaires et postes ne sont pas réécrits : l'organisation les
				 * ajuste souvent à la main, et le tournoi migré conserve ceux
				 * du dossier d'origine. Ils sont posés à la création, ou par
				 * l'outil « Recalculer le planning ».
				 */
				$wpdb->update( $table, $row, array( 'id' => (int) $exists ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
			} else {
				$row['stations']   = isset( $def['stations'] ) ? $def['stations'] : '';
				$row['start_time'] = isset( $def['start'] ) ? $def['start'] : '';
				$row['end_time']   = isset( $def['end'] ) ? $def['end'] : '';
				$row['status']     = 'pending';
				$row['updated_at'] = current_time( 'mysql' );
				$wpdb->insert( $table, $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
			}
		}

		// Matchs devenus inutiles (petite finale désactivée, par exemple).
		foreach ( self::get_matches( $tid ) as $match ) {
			if ( in_array( $match['code'], $wanted, true ) ) {
				continue;
			}
			$wpdb->delete( brackethive_table( 'games' ), array( 'match_id' => (int) $match['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
			$wpdb->delete( $table, array( 'id' => (int) $match['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		}
	}

	/**
	 * Réécrit horaires et postes de tous les matchs d'après les réglages.
	 *
	 * @param int $tournament_id Tournoi.
	 */
	public static function rebuild_schedule( $tournament_id ) {
		global $wpdb;

		$tid = (int) $tournament_id;
		if ( ! $tid ) {
			return;
		}

		$settings = self::structure_settings( $tid );

		foreach ( Brackethive_Bracket::structure( $settings ) as $def ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
				brackethive_table( 'matches' ),
				array(
					'stations'   => isset( $def['stations'] ) ? $def['stations'] : '',
					'start_time' => isset( $def['start'] ) ? $def['start'] : '',
					'end_time'   => isset( $def['end'] ) ? $def['end'] : '',
				),
				array(
					'tournament_id' => $tid,
					'code'          => $def['code'],
				)
			);
		}
	}

	/**
	 * Tous les matchs d'un tournoi, dans l'ordre de déroulement.
	 *
	 * @param int $tournament_id Tournoi.
	 * @return array
	 */
	public static function get_matches( $tournament_id ) {
		global $wpdb;

		$tid = (int) $tournament_id;
		if ( ! $tid ) {
			return array();
		}

		$table = brackethive_table( 'matches' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE tournament_id = %d ORDER BY round_no ASC, position ASC", // phpcs:ignore WordPress.DB.PreparedSQL -- Nom de table issu de brackethive_table(), sans donnée utilisateur ; les valeurs passent par $wpdb->prepare().
				$tid
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Matchs indexés par code.
	 *
	 * @param int $tournament_id Tournoi.
	 * @return array
	 */
	public static function get_matches_map( $tournament_id ) {
		$map = array();
		foreach ( self::get_matches( $tournament_id ) as $match ) {
			$map[ $match['code'] ] = $match;
		}
		return $map;
	}

	/**
	 * Matchs regroupés par tour.
	 *
	 * @param int $tournament_id Tournoi.
	 * @return array
	 */
	public static function get_matches_by_round( $tournament_id ) {
		$out = array();
		foreach ( self::get_matches( $tournament_id ) as $match ) {
			$out[ $match['round'] ][] = $match;
		}
		return $out;
	}

	/**
	 * Un match par identifiant.
	 *
	 * @param int $id Identifiant.
	 * @return array|null
	 */
	public static function get_match( $id ) {
		global $wpdb;
		$table = brackethive_table( 'matches' );
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", (int) $id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
		return $row ? $row : null;
	}

	/**
	 * Manches d'un match (BO3 et plus).
	 *
	 * @param int $match_id Identifiant du match.
	 * @return array
	 */
	public static function get_games( $match_id ) {
		global $wpdb;
		$table = brackethive_table( 'games' );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE match_id = %d ORDER BY game_no ASC", (int) $match_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Manches de tous les matchs d'un tournoi, indexées par match.
	 *
	 * @param int $tournament_id Tournoi.
	 * @return array
	 */
	public static function get_all_games( $tournament_id ) {
		global $wpdb;

		$table = brackethive_table( 'games' );
		$rows  = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
			$wpdb->prepare( "SELECT * FROM {$table} WHERE tournament_id = %d ORDER BY match_id ASC, game_no ASC", (int) $tournament_id ), // phpcs:ignore WordPress.DB.PreparedSQL -- Nom de table issu de brackethive_table(), sans donnée utilisateur ; les valeurs passent par $wpdb->prepare().
			ARRAY_A
		);

		$out = array();
		if ( is_array( $rows ) ) {
			foreach ( $rows as $row ) {
				$out[ (int) $row['match_id'] ][] = $row;
			}
		}
		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Résultats
	 * ------------------------------------------------------------------ */

	/**
	 * Enregistre le résultat d'un match.
	 *
	 * @param int   $match_id Identifiant du match.
	 * @param array $data     status, score1, score2, referee, games[].
	 * @return true|WP_Error
	 */
	public static function save_result( $match_id, $data ) {
		global $wpdb;

		$match = self::get_match( $match_id );
		if ( ! $match ) {
			return new WP_Error( 'brackethive_no_match', __( 'Match not found.', 'brackethive' ) );
		}

		$tid    = (int) $match['tournament_id'];
		$status = isset( $data['status'] ) ? sanitize_key( $data['status'] ) : 'pending';
		if ( ! in_array( $status, array( 'pending', 'live', 'done' ), true ) ) {
			$status = 'pending';
		}

		$team1 = (int) $match['team1_id'];
		$team2 = (int) $match['team2_id'];
		$bo    = (int) $match['bo'];

		$games  = isset( $data['games'] ) && is_array( $data['games'] ) ? $data['games'] : array();
		$rows   = array();
		$won1   = 0;
		$won2   = 0;
		$needed = (int) floor( $bo / 2 ) + 1;

		if ( $bo > 1 ) {
			// Les manches sont validées avant toute écriture : une saisie
			// refusée ne doit pas effacer les manches déjà enregistrées.
			$game_no = 0;
			foreach ( $games as $game ) {
				$s1 = isset( $game['score1'] ) && '' !== $game['score1'] ? (int) $game['score1'] : null;
				$s2 = isset( $game['score2'] ) && '' !== $game['score2'] ? (int) $game['score2'] : null;
				if ( null === $s1 && null === $s2 ) {
					continue;
				}

				if ( $won1 >= $needed || $won2 >= $needed ) {
					return new WP_Error(
						'brackethive_bo_extra',
						sprintf(
							/* translators: 1: format BO, 2: nombre de manches à gagner */
							__( 'In BO%1$d, the match ends as soon as %2$d games are won: remove the extra games.', 'brackethive' ),
							$bo,
							$needed
						)
					);
				}

				$game_no++;
				$s1 = max( 0, (int) $s1 );
				$s2 = max( 0, (int) $s2 );

				if ( $s1 === $s2 ) {
					return new WP_Error(
						'brackethive_game_draw',
						sprintf(
							/* translators: %d: numéro de manche */
							__( 'Game %d is tied: a game must have a winner.', 'brackethive' ),
							$game_no
						)
					);
				}

				$gw = 0;
				if ( $s1 > $s2 ) {
					$gw = $team1;
					$won1++;
				} else {
					$gw = $team2;
					$won2++;
				}

				$rows[] = array(
					'tournament_id' => $tid,
					'match_id'      => (int) $match['id'],
					'game_no'       => $game_no,
					'score1'        => $s1,
					'score2'        => $s2,
					'winner_id'     => $gw,
					'map'           => isset( $game['map'] ) ? sanitize_text_field( $game['map'] ) : '',
				);
			}
			$score1 = $won1;
			$score2 = $won2;
		} else {
			$score1 = isset( $data['score1'] ) ? max( 0, (int) $data['score1'] ) : 0;
			$score2 = isset( $data['score2'] ) ? max( 0, (int) $data['score2'] ) : 0;
		}

		$winner = 0;
		if ( 'done' === $status ) {
			if ( ! $team1 || ! $team2 ) {
				return new WP_Error( 'brackethive_incomplete', __( 'Both teams must be known before the match can be approved.', 'brackethive' ) );
			}
			if ( $score1 === $score2 ) {
				return new WP_Error( 'brackethive_draw', __( 'A single-elimination match cannot end in a tie.', 'brackethive' ) );
			}
			if ( $bo > 1 && max( $score1, $score2 ) < $needed ) {
				return new WP_Error(
					'brackethive_bo',
					sprintf(
						/* translators: 1: format BO, 2: nombre de manches à gagner */
						__( 'In BO%1$d, the winner must win %2$d games.', 'brackethive' ),
						$bo,
						$needed
					)
				);
			}
			$winner = $score1 > $score2 ? $team1 : $team2;
		}

		if ( $bo > 1 ) {
			$wpdb->delete( brackethive_table( 'games' ), array( 'match_id' => (int) $match['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
			foreach ( $rows as $row ) {
				$wpdb->insert( brackethive_table( 'games' ), $row ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
			}
		}

		$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
			brackethive_table( 'matches' ),
			array(
				'score1'     => $score1,
				'score2'     => $score2,
				'winner_id'  => $winner,
				'status'     => $status,
				'referee'    => isset( $data['referee'] ) ? sanitize_text_field( $data['referee'] ) : $match['referee'],
				'stations'   => isset( $data['stations'] ) ? sanitize_text_field( $data['stations'] ) : $match['stations'],
				'start_time' => isset( $data['start_time'] ) ? sanitize_text_field( $data['start_time'] ) : $match['start_time'],
				'end_time'   => isset( $data['end_time'] ) ? sanitize_text_field( $data['end_time'] ) : $match['end_time'],
				'updated_at' => current_time( 'mysql' ),
			),
			array( 'id' => (int) $match['id'] )
		);

		self::recalculate( $tid );

		return true;
	}

	/**
	 * Recalcule le tableau d'un tournoi : placement des têtes de série,
	 * propagation des vainqueurs et des perdants, invalidation des résultats
	 * devenus incohérents.
	 *
	 * @param int $tournament_id Tournoi.
	 */
	public static function recalculate( $tournament_id ) {
		global $wpdb;

		$tid = (int) $tournament_id;
		if ( ! $tid ) {
			return;
		}

		$table   = brackethive_table( 'matches' );
		$matches = self::get_matches_map( $tid );
		if ( empty( $matches ) ) {
			return;
		}

		// Caches partagés par tout le recalcul : équipes, têtes de série,
		// membres des poules, classements de poules.
		$teams = self::get_teams_map( $tid );
		$seeds = array();
		foreach ( $teams as $id => $team ) {
			if ( 'active' === $team['status'] && (int) $team['seed'] > 0 ) {
				$seeds[ (int) $team['seed'] ] = (int) $id;
			}
		}
		$ctx = array(
			'teams'     => $teams,
			'seeds'     => $seeds,
			'members'   => array(),
			'standings' => array(),
		);

		// Ordre de traitement : chaque match après ceux dont il dépend.
		foreach ( Brackethive_Bracket::processing_order( $matches ) as $code ) {
			$match = $matches[ $code ];

			$old1 = (int) $match['team1_id'];
			$old2 = (int) $match['team2_id'];

			$t1 = self::resolve_source( $tid, $match['src1'], $matches, $ctx );
			$t2 = self::resolve_source( $tid, $match['src2'], $matches, $ctx );

			$matches[ $code ]['team1_id'] = $t1;
			$matches[ $code ]['team2_id'] = $t2;

			// Exemption : une source vide (« BYE », perdant d'un match
			// d'exemption, vainqueur d'un match sans objet) laisse passer
			// l'équipe présente sans jouer.
			$bye1 = Brackethive_Bracket::source_is_empty( $match['src1'], $matches );
			$bye2 = Brackethive_Bracket::source_is_empty( $match['src2'], $matches );

			if ( $bye1 && $bye2 ) {
				// Match sans objet.
				self::clear_result( $matches[ $code ], $match, $wpdb );
				continue;
			}

			if ( $bye1 || $bye2 ) {
				$present = $bye1 ? $t2 : $t1;
				if ( $present ) {
					$matches[ $code ]['winner_id'] = $present;
					$matches[ $code ]['status']    = 'done';
					$matches[ $code ]['score1']    = $t1 ? 1 : 0;
					$matches[ $code ]['score2']    = $t1 ? 0 : 1;
				} else {
					self::clear_result( $matches[ $code ], $match, $wpdb );
				}
				continue;
			}

			$winner  = (int) $match['winner_id'];
			$has_res = 'pending' !== $match['status'] || (int) $match['score1'] || (int) $match['score2'] || $winner;

			// Le résultat est invalidé si le vainqueur n'est plus dans le
			// match, s'il manque une équipe, ou si l'une des deux équipes a
			// changé (correction d'un résultat en amont).
			$orphan  = $winner && $winner !== $t1 && $winner !== $t2;
			$partial = ( ! $t1 || ! $t2 ) && $has_res;
			$changed = $has_res && ( $old1 !== $t1 || $old2 !== $t2 );

			if ( $orphan || $partial || $changed ) {
				self::clear_result( $matches[ $code ], $match, $wpdb );
			}

			// Un classement de poule change dès qu'un résultat de poule change.
			if ( 'group' === $match['phase'] ) {
				unset( $ctx['standings'][ (int) $match['group_id'] ] );
			}
		}

		foreach ( $matches as $match ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
				$table,
				array(
					'team1_id'  => (int) $match['team1_id'],
					'team2_id'  => (int) $match['team2_id'],
					'winner_id' => (int) $match['winner_id'],
					'status'    => $match['status'],
					'score1'    => (int) $match['score1'],
					'score2'    => (int) $match['score2'],
				),
				array( 'id' => (int) $match['id'] )
			);
		}
	}

	/**
	 * Remet un match à l'état « à jouer » (manches supprimées).
	 *
	 * @param array  $target Match en cours de calcul (modifié).
	 * @param array  $stored Match tel qu'en base.
	 * @param object $wpdb   Accès base.
	 */
	protected static function clear_result( &$target, $stored, $wpdb ) {
		$target['winner_id'] = 0;
		$target['status']    = 'pending';
		$target['score1']    = 0;
		$target['score2']    = 0;

		if ( 'pending' !== $stored['status'] || (int) $stored['score1'] || (int) $stored['score2'] || (int) $stored['winner_id'] ) {
			$wpdb->delete( brackethive_table( 'games' ), array( 'match_id' => (int) $stored['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		}
	}

	/**
	 * Résout un slot.
	 *
	 * « S<n> » tête de série, « W:<code> » vainqueur, « L:<code> » perdant.
	 *
	 * @param int    $tournament_id Tournoi.
	 * @param string $src           Source.
	 * @param array  $matches       Matchs en cours de calcul, indexés par code.
	 * @param array  $ctx           Caches du recalcul (teams, seeds, members, standings), passés par référence.
	 * @return int Identifiant d'équipe ou 0.
	 */
	protected static function resolve_source( $tournament_id, $src, $matches, &$ctx = null ) {
		if ( '' === $src || 'BYE' === $src ) {
			return 0;
		}

		if ( ! is_array( $ctx ) ) {
			$ctx = array(
				'teams'     => null,
				'seeds'     => null,
				'members'   => array(),
				'standings' => array(),
			);
		}

		// Emplacement dans une poule : « GS:<poule>:<index> ».
		if ( 'GS:' === substr( $src, 0, 3 ) ) {
			$parts = explode( ':', $src );
			if ( 3 === count( $parts ) ) {
				$g = (int) $parts[1];
				if ( ! isset( $ctx['members'][ $g ] ) ) {
					$ctx['members'][ $g ] = self::group_members( $tournament_id, $g );
				}
				$index = (int) $parts[2] - 1;
				return isset( $ctx['members'][ $g ][ $index ] ) ? (int) $ctx['members'][ $g ][ $index ] : 0;
			}
			return 0;
		}

		// Qualifié d'une poule : « G:<poule>:<rang> ».
		if ( 'G:' === substr( $src, 0, 2 ) ) {
			$parts = explode( ':', $src );
			if ( 3 === count( $parts ) ) {
				$g = (int) $parts[1];
				if ( ! isset( $ctx['standings'][ $g ] ) ) {
					$ctx['standings'][ $g ] = Brackethive_Standings::qualified_table( $tournament_id, $g, array_values( $matches ), $ctx['teams'] );
				}
				$rank = (int) $parts[2] - 1;
				return isset( $ctx['standings'][ $g ][ $rank ] ) ? (int) $ctx['standings'][ $g ][ $rank ] : 0;
			}
			return 0;
		}

		if ( 'S' === substr( $src, 0, 1 ) && ctype_digit( substr( $src, 1 ) ) ) {
			$seed = (int) substr( $src, 1 );
			if ( is_array( $ctx['seeds'] ) ) {
				return isset( $ctx['seeds'][ $seed ] ) ? (int) $ctx['seeds'][ $seed ] : 0;
			}
			$team = self::get_team_by_seed( $tournament_id, $seed );
			return $team ? (int) $team['id'] : 0;
		}

		if ( 'W:' === substr( $src, 0, 2 ) ) {
			$code = substr( $src, 2 );
			if ( isset( $matches[ $code ] ) && 'done' === $matches[ $code ]['status'] ) {
				return (int) $matches[ $code ]['winner_id'];
			}
			return 0;
		}

		if ( 'L:' === substr( $src, 0, 2 ) ) {
			$code = substr( $src, 2 );
			if ( isset( $matches[ $code ] ) && 'done' === $matches[ $code ]['status'] ) {
				$winner = (int) $matches[ $code ]['winner_id'];
				$t1     = (int) $matches[ $code ]['team1_id'];
				$t2     = (int) $matches[ $code ]['team2_id'];
				if ( $winner && $t1 && $t2 ) {
					return $winner === $t1 ? $t2 : $t1;
				}
			}
			return 0;
		}

		return 0;
	}

	/**
	 * Réglages d'un tournoi complétés pour la génération de structure.
	 *
	 * En composition manuelle des poules, la taille de chaque poule est lue
	 * sur les équipes validées : la structure génère alors exactement les
	 * matchs de chaque poule.
	 *
	 * @param int $tournament_id Tournoi.
	 * @return array
	 */
	public static function structure_settings( $tournament_id ) {
		$tid      = (int) $tournament_id;
		$settings = Brackethive_Tournament::settings( $tid );

		if ( 'groups' === $settings['format'] && 'manual' === $settings['group_mode'] ) {
			$layout = Brackethive_Bracket::group_layout( $settings );
			$sizes  = array();
			for ( $g = 1; $g <= $layout['groups']; $g++ ) {
				$sizes[ $g ] = 0;
			}
			foreach ( self::get_teams( array( 'tournament_id' => $tid, 'status' => 'active' ) ) as $team ) {
				$g = (int) $team['group_id'];
				if ( isset( $sizes[ $g ] ) ) {
					$sizes[ $g ]++;
				}
			}
			$settings['group_sizes'] = $sizes;
		}

		return $settings;
	}

	/**
	 * La structure d'un tournoi dépend-elle de la composition des équipes ?
	 *
	 * @param int $tournament_id Tournoi.
	 * @return bool
	 */
	protected static function structure_follows_teams( $tournament_id ) {
		$tid = (int) $tournament_id;
		return 'groups' === Brackethive_Tournament::get( $tid, 'format' ) && 'manual' === Brackethive_Tournament::get( $tid, 'group_mode' );
	}

	/**
	 * Libellé d'un slot non encore attribué.
	 *
	 * @param string $src Source.
	 * @return string
	 */
	public static function source_label( $src ) {
		if ( 'W:' === substr( $src, 0, 2 ) ) {
			/* translators: %s: code du match */
			return sprintf( __( 'Winner of %s', 'brackethive' ), substr( $src, 2 ) );
		}
		if ( 'L:' === substr( $src, 0, 2 ) ) {
			/* translators: %s: code du match */
			return sprintf( __( 'Loser of %s', 'brackethive' ), substr( $src, 2 ) );
		}
		if ( 'GS:' === substr( $src, 0, 3 ) ) {
			$parts = explode( ':', $src );
			if ( 3 === count( $parts ) ) {
				return sprintf(
					/* translators: 1: numéro d'équipe, 2: lettre de poule */
					__( 'Team %1$d of group %2$s', 'brackethive' ),
					(int) $parts[2],
					self::group_name( (int) $parts[1] )
				);
			}
		}
		if ( 'G:' === substr( $src, 0, 2 ) ) {
			$parts = explode( ':', $src );
			if ( 3 === count( $parts ) ) {
				return sprintf(
					/* translators: 1: rang, 2: numéro de poule */
					__( '%1$d%2$s in group %3$s', 'brackethive' ),
					(int) $parts[2],
					1 === (int) $parts[2] ? __( 'st', 'brackethive' ) : __( 'th', 'brackethive' ),
					self::group_name( (int) $parts[1] )
				);
			}
		}
		if ( 'BYE' === $src ) {
			return __( 'Bye', 'brackethive' );
		}
		if ( 'S' === substr( $src, 0, 1 ) ) {
			/* translators: %d: numéro de position */
			return sprintf( __( 'Team #%d', 'brackethive' ), (int) substr( $src, 1 ) );
		}
		return __( 'To be determined', 'brackethive' );
	}

	/**
	 * Équipes composant une poule.
	 *
	 * En mode « auto », la répartition découle des positions, en serpentin.
	 * En mode « manuel », elle suit la poule attribuée à chaque équipe.
	 *
	 * @param int $tournament_id Tournoi.
	 * @param int $group_id      Poule.
	 * @return array Identifiants d'équipes, ordonnés.
	 */
	public static function group_members( $tournament_id, $group_id ) {
		$tid   = (int) $tournament_id;
		$group = (int) $group_id;

		if ( ! $tid || ! $group ) {
			return array();
		}

		$mode  = Brackethive_Tournament::get( $tid, 'group_mode' );
		$teams = self::get_teams( array( 'tournament_id' => $tid, 'status' => 'active' ) );
		$out   = array();

		if ( 'manual' === $mode ) {
			foreach ( $teams as $team ) {
				if ( (int) $team['group_id'] === $group ) {
					$out[] = (int) $team['id'];
				}
			}
			return $out;
		}

		// Mêmes bornes que la structure : nombre de poules limité par
		// l'effectif, effectif minimal de 4.
		$layout = Brackethive_Bracket::group_layout( Brackethive_Tournament::settings( $tid ) );
		$seeds  = Brackethive_Bracket::group_seeds( $layout['count'], $layout['groups'] );

		if ( ! isset( $seeds[ $group ] ) ) {
			return array();
		}

		$by_seed = array();
		foreach ( $teams as $team ) {
			if ( (int) $team['seed'] > 0 ) {
				$by_seed[ (int) $team['seed'] ] = (int) $team['id'];
			}
		}

		foreach ( $seeds[ $group ] as $seed ) {
			if ( isset( $by_seed[ $seed ] ) ) {
				$out[] = $by_seed[ $seed ];
			}
		}

		return $out;
	}

	/**
	 * Un match est-il utile, compte tenu des résultats ?
	 *
	 * Seule la seconde grande finale est conditionnelle : elle n'a lieu que
	 * si l'équipe issue du repêchage a remporté la première.
	 *
	 * @param array $match   Match.
	 * @param array $matches Tous les matchs, indexés par code.
	 * @return bool
	 */
	public static function is_match_needed( $match, $matches ) {
		// Match sans objet : ses deux sources sont structurellement vides
		// (deux exemptions se rejoignant au repêchage, par exemple).
		if ( isset( $match['src1'], $match['src2'] ) ) {
			if ( Brackethive_Bracket::source_is_empty( $match['src1'], $matches ) && Brackethive_Bracket::source_is_empty( $match['src2'], $matches ) ) {
				return false;
			}
		}

		if ( 'GF2' !== $match['code'] ) {
			return true;
		}

		if ( ! isset( $matches['GF'] ) ) {
			return false;
		}

		$gf = $matches['GF'];

		if ( 'done' !== $gf['status'] ) {
			return true;
		}

		// L'équipe 2 de la grande finale vient du repêchage.
		return (int) $gf['winner_id'] === (int) $gf['team2_id'];
	}

	/**
	 * Nom lisible d'une poule (1 => A, 2 => B…).
	 *
	 * @param int $group_id Numéro de poule.
	 * @return string
	 */
	public static function group_name( $group_id ) {
		$group_id = (int) $group_id;
		if ( $group_id < 1 ) {
			return '';
		}
		return chr( 64 + min( 26, $group_id ) );
	}

	/* ---------------------------------------------------------------------
	 * Utilitaires
	 * ------------------------------------------------------------------ */

	/**
	 * Place automatiquement les équipes validées sans position.
	 *
	 * @param int  $tournament_id Tournoi.
	 * @param bool $shuffle       Mélanger avant placement.
	 * @return int Nombre d'équipes placées.
	 */
	public static function autoseed( $tournament_id, $shuffle = true ) {
		global $wpdb;

		$tid = (int) $tournament_id;
		if ( ! $tid ) {
			return 0;
		}

		$max = (int) Brackethive_Tournament::get( $tid, 'team_count' );
		$max = $max > 0 ? $max : 16;

		$teams = self::get_teams( array( 'tournament_id' => $tid, 'status' => 'active' ) );
		$taken = array();
		$free  = array();

		foreach ( $teams as $team ) {
			if ( (int) $team['seed'] > 0 ) {
				$taken[] = (int) $team['seed'];
			} else {
				$free[] = $team;
			}
		}

		if ( empty( $free ) ) {
			return 0;
		}

		if ( $shuffle ) {
			shuffle( $free );
		}

		$placed = 0;
		for ( $seed = 1; $seed <= $max; $seed++ ) {
			if ( in_array( $seed, $taken, true ) ) {
				continue;
			}
			$team = array_shift( $free );
			if ( ! $team ) {
				break;
			}
			$wpdb->update( brackethive_table( 'teams' ), array( 'seed' => $seed ), array( 'id' => (int) $team['id'] ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
			$placed++;
		}

		self::recalculate( $tid );

		return $placed;
	}

	/**
	 * Remet à zéro les résultats d'un tournoi (les équipes sont conservées).
	 *
	 * @param int $tournament_id Tournoi.
	 */
	public static function reset_results( $tournament_id ) {
		global $wpdb;

		$tid = (int) $tournament_id;
		if ( ! $tid ) {
			return;
		}

		$wpdb->delete( brackethive_table( 'games' ), array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Table propre à l'extension, nom issu de brackethive_table() ; valeurs passées par $wpdb->prepare() ; données transactionnelles non mises en cache.
			$wpdb->prepare(
				'UPDATE ' . brackethive_table( 'matches' ) . " SET score1 = 0, score2 = 0, winner_id = 0, status = 'pending' WHERE tournament_id = %d", // phpcs:ignore WordPress.DB.PreparedSQL -- Nom de table issu de brackethive_table(), sans donnée utilisateur ; les valeurs passent par $wpdb->prepare().
				$tid
			)
		);

		self::recalculate( $tid );
	}

	/**
	 * Réinitialisation totale d'un tournoi : équipes et résultats supprimés,
	 * tableau recréé vierge. Les réglages sont conservés.
	 *
	 * @param int $tournament_id Tournoi.
	 */
	public static function reset_all( $tournament_id ) {
		global $wpdb;

		$tid = (int) $tournament_id;
		if ( ! $tid ) {
			return;
		}

		$wpdb->delete( brackethive_table( 'games' ), array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		$wpdb->delete( brackethive_table( 'teams' ), array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.
		$wpdb->delete( brackethive_table( 'matches' ), array( 'tournament_id' => $tid ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Table propre à l'extension ; données transactionnelles non mises en cache.

		self::ensure_bracket( $tid );
		self::recalculate( $tid );
	}

	/**
	 * Statistiques d'un tournoi.
	 *
	 * @param int $tournament_id Tournoi.
	 * @return array
	 */
	public static function get_stats( $tournament_id ) {
		$tid     = (int) $tournament_id;
		$matches = self::get_matches( $tid );
		$done    = 0;
		$live    = 0;
		$champ   = 0;
		$third   = 0;
		$skipped = 0;

		$final_code = '';
		$final_rank = -1;

		$map = array();
		foreach ( $matches as $match ) {
			$map[ $match['code'] ] = $match;
		}

		foreach ( $matches as $match ) {
			// Exemptions et matchs sans objet ne comptent ni dans le total
			// ni parmi les matchs joués.
			$playable = Brackethive_Bracket::is_playable( $match, $map ) && self::is_match_needed( $match, $map );
			if ( ! $playable ) {
				$skipped++;
			} elseif ( 'done' === $match['status'] ) {
				$done++;
			} elseif ( 'live' === $match['status'] ) {
				$live++;
			}

			// Le dernier match du tournoi désigne le vainqueur : « F » en
			// élimination directe, « GF » en double élimination.
			if ( in_array( $match['round'], array( 'final', 'gf' ), true ) && (int) $match['round_no'] > $final_rank ) {
				$final_rank = (int) $match['round_no'];
				$final_code = $match['code'];
			}

			if ( 'P3' === $match['code'] && 'done' === $match['status'] ) {
				$third = (int) $match['winner_id'];
			}
		}

		// La seconde grande finale, si elle a lieu, prime sur la première.
		if ( isset( $map['GF2'] ) && self::is_match_needed( $map['GF2'], $map ) ) {
			$final_code = 'GF2';
		}

		foreach ( $matches as $match ) {
			if ( $match['code'] === $final_code && 'done' === $match['status'] ) {
				$champ = (int) $match['winner_id'];
			}
		}

		$teams  = self::get_teams( array( 'tournament_id' => $tid ) );
		$active = 0;
		$seeded = 0;
		foreach ( $teams as $team ) {
			if ( 'active' === $team['status'] ) {
				$active++;
				if ( (int) $team['seed'] > 0 ) {
					$seeded++;
				}
			}
		}

		$max = (int) Brackethive_Tournament::get( $tid, 'team_count' );

		return array(
			'teams_total'   => count( $teams ),
			'teams_active'  => $active,
			'teams_seeded'  => $seeded,
			'teams_max'     => $max > 0 ? $max : 16,
			'matches_total' => count( $matches ) - $skipped,
			'matches_done'  => $done,
			'matches_live'  => $live,
			'champion_id'   => $champ,
			'third_id'      => $third,
		);
	}

	/**
	 * Nom affichable d'une équipe.
	 *
	 * @param int    $team_id  Identifiant.
	 * @param array  $map      Cache d'équipes.
	 * @param string $fallback Valeur de repli.
	 * @return string
	 */
	public static function team_name( $team_id, $map = array(), $fallback = '' ) {
		$team_id = (int) $team_id;

		if ( ! $team_id ) {
			return '' !== $fallback ? $fallback : __( 'To be determined', 'brackethive' );
		}

		if ( is_array( $map ) && isset( $map[ $team_id ] ) ) {
			return $map[ $team_id ]['name'];
		}

		$team = self::get_team( $team_id );

		return $team ? $team['name'] : ( '' !== $fallback ? $fallback : __( 'To be determined', 'brackethive' ) );
	}
}
