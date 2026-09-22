<?php
/**
 * Classements de la phase de poules.
 *
 * Départage : victoires, puis différence de score, puis score marqué, puis
 * confrontation directe, puis numéro de tête de série.
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Standings {

	/**
	 * Classement d'une poule.
	 *
	 * @param int   $tournament_id Tournoi.
	 * @param int   $group_id      Poule.
	 * @param array $matches       Matchs déjà chargés (optionnel).
	 * @param array $teams         Équipes déjà chargées (optionnel).
	 * @return array Lignes de classement, de la 1re à la dernière place.
	 */
	public static function group( $tournament_id, $group_id, $matches = null, $teams = null ) {
		$tid      = (int) $tournament_id;
		$group_id = (int) $group_id;

		if ( null === $matches ) {
			$matches = WGT_Data::get_matches( $tid );
		}
		if ( null === $teams ) {
			$teams = WGT_Data::get_teams_map( $tid );
		}

		$rows   = array();
		$direct = array();

		foreach ( $matches as $match ) {
			if ( 'group' !== $match['phase'] || (int) $match['group_id'] !== $group_id ) {
				continue;
			}

			$t1 = (int) $match['team1_id'];
			$t2 = (int) $match['team2_id'];

			foreach ( array( $t1, $t2 ) as $id ) {
				if ( $id && ! isset( $rows[ $id ] ) ) {
					$rows[ $id ] = self::blank( $id, $teams );
				}
			}

			if ( 'done' !== $match['status'] || ! $t1 || ! $t2 ) {
				continue;
			}

			$s1 = (int) $match['score1'];
			$s2 = (int) $match['score2'];

			$rows[ $t1 ]['played']++;
			$rows[ $t2 ]['played']++;
			$rows[ $t1 ]['for']     += $s1;
			$rows[ $t1 ]['against'] += $s2;
			$rows[ $t2 ]['for']     += $s2;
			$rows[ $t2 ]['against'] += $s1;

			$winner = (int) $match['winner_id'];
			if ( $winner !== $t1 && $winner !== $t2 ) {
				// Donnée incohérente (import corrompu) : le match compte
				// comme joué, sans vainqueur.
				continue;
			}
			$loser = $winner === $t1 ? $t2 : $t1;

			$rows[ $winner ]['wins']++;
			$rows[ $loser ]['losses']++;

			$direct[ $winner . '-' . $loser ] = true;
		}

		// Poule sans aucun match (une seule équipe) : ses membres sont tout
		// de même classés, pour que le qualifié existe.
		if ( empty( $rows ) ) {
			foreach ( WGT_Data::group_members( $tid, $group_id ) as $id ) {
				$rows[ (int) $id ] = self::blank( $id, $teams );
			}
		}

		foreach ( $rows as $id => $row ) {
			$rows[ $id ]['diff'] = $row['for'] - $row['against'];
		}

		uasort(
			$rows,
			function ( $a, $b ) use ( $direct ) {
				if ( $a['wins'] !== $b['wins'] ) {
					return $b['wins'] - $a['wins'];
				}
				if ( $a['diff'] !== $b['diff'] ) {
					return $b['diff'] - $a['diff'];
				}
				if ( $a['for'] !== $b['for'] ) {
					return $b['for'] - $a['for'];
				}
				// Confrontation directe.
				if ( isset( $direct[ $a['team_id'] . '-' . $b['team_id'] ] ) ) {
					return -1;
				}
				if ( isset( $direct[ $b['team_id'] . '-' . $a['team_id'] ] ) ) {
					return 1;
				}
				return $a['seed'] - $b['seed'];
			}
		);

		$out  = array();
		$rank = 0;
		foreach ( $rows as $row ) {
			$rank++;
			$row['rank'] = $rank;
			$out[]       = $row;
		}

		return $out;
	}

	/**
	 * Ligne vierge.
	 *
	 * @param int   $team_id Équipe.
	 * @param array $teams   Équipes indexées.
	 * @return array
	 */
	protected static function blank( $team_id, $teams ) {
		return array(
			'team_id' => (int) $team_id,
			'name'    => WGT_Data::team_name( $team_id, $teams ),
			'seed'    => isset( $teams[ $team_id ] ) ? (int) $teams[ $team_id ]['seed'] : 999,
			'played'  => 0,
			'wins'    => 0,
			'losses'  => 0,
			'for'     => 0,
			'against' => 0,
			'diff'    => 0,
		);
	}

	/**
	 * Équipe classée à une position donnée d'une poule.
	 *
	 * Ne renvoie un résultat que si tous les matchs de la poule sont joués :
	 * un classement provisoire ne doit pas qualifier quelqu'un.
	 *
	 * @param int   $tournament_id Tournoi.
	 * @param int   $group_id      Poule.
	 * @param int   $rank          Rang recherché.
	 * @param array $matches       Matchs déjà chargés.
	 * @return int Identifiant d'équipe, ou 0.
	 */
	public static function qualified( $tournament_id, $group_id, $rank, $matches = null ) {
		$table = self::qualified_table( $tournament_id, $group_id, $matches );

		return isset( $table[ $rank - 1 ] ) ? (int) $table[ $rank - 1 ] : 0;
	}

	/**
	 * Identifiants des équipes d'une poule, dans l'ordre du classement, si
	 * tous les matchs de la poule sont joués ; liste vide sinon.
	 *
	 * @param int   $tournament_id Tournoi.
	 * @param int   $group_id      Poule.
	 * @param array $matches       Matchs déjà chargés.
	 * @param array $teams         Équipes déjà chargées.
	 * @return array
	 */
	public static function qualified_table( $tournament_id, $group_id, $matches = null, $teams = null ) {
		$tid = (int) $tournament_id;

		if ( null === $matches ) {
			$matches = WGT_Data::get_matches( $tid );
		}

		foreach ( $matches as $match ) {
			if ( 'group' !== $match['phase'] || (int) $match['group_id'] !== (int) $group_id ) {
				continue;
			}
			if ( 'done' !== $match['status'] ) {
				return array();
			}
		}

		$out = array();
		foreach ( self::group( $tid, $group_id, $matches, $teams ) as $row ) {
			$out[] = (int) $row['team_id'];
		}

		return $out;
	}

	/**
	 * Liste des poules d'un tournoi.
	 *
	 * @param int   $tournament_id Tournoi.
	 * @param array $matches       Matchs déjà chargés.
	 * @return array
	 */
	public static function groups( $tournament_id, $matches = null ) {
		if ( null === $matches ) {
			$matches = WGT_Data::get_matches( (int) $tournament_id );
		}

		$out = array();
		foreach ( $matches as $match ) {
			if ( 'group' === $match['phase'] && (int) $match['group_id'] ) {
				$out[ (int) $match['group_id'] ] = true;
			}
		}

		$out = array_keys( $out );
		sort( $out );

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Classement général du tournoi
	 * ------------------------------------------------------------------ */

	/**
	 * Classement final, de la 1re place à la dernière.
	 *
	 * Le podium est lu sur les matchs décisifs ; les autres équipes sont
	 * départagées par la profondeur atteinte, à égalité de rang lorsqu'elles
	 * ont été éliminées au même tour.
	 *
	 * @param int $tournament_id Tournoi.
	 * @return array Lignes { rank, team_id, name, seed, status }.
	 */
	public static function final_ranking( $tournament_id ) {
		$tid     = (int) $tournament_id;
		$matches = WGT_Data::get_matches( $tid );
		$teams   = WGT_Data::get_teams_map( $tid );

		if ( empty( $matches ) || empty( $teams ) ) {
			return array();
		}

		$by_code = array();
		foreach ( $matches as $match ) {
			$by_code[ $match['code'] ] = $match;
		}

		$placed = array();

		// Podium : finale, puis match de classement s'il existe. En double
		// élimination, la seconde grande finale prime lorsqu'elle a lieu.
		$final = isset( $by_code['GF'] ) ? $by_code['GF'] : ( isset( $by_code['F'] ) ? $by_code['F'] : null );

		if ( isset( $by_code['GF2'] ) && WGT_Data::is_match_needed( $by_code['GF2'], $by_code ) ) {
			$final = 'done' === $by_code['GF2']['status'] ? $by_code['GF2'] : null;
		}

		if ( $final && 'done' === $final['status'] ) {
			$winner        = (int) $final['winner_id'];
			$loser         = $winner === (int) $final['team1_id'] ? (int) $final['team2_id'] : (int) $final['team1_id'];
			$placed[ $winner ] = 1;
			if ( $loser ) {
				$placed[ $loser ] = 2;
			}
		}

		if ( isset( $by_code['P3'] ) && 'done' === $by_code['P3']['status'] ) {
			$third  = (int) $by_code['P3']['winner_id'];
			$fourth = $third === (int) $by_code['P3']['team1_id'] ? (int) $by_code['P3']['team2_id'] : (int) $by_code['P3']['team1_id'];
			if ( $third ) {
				$placed[ $third ] = 3;
			}
			if ( $fourth ) {
				$placed[ $fourth ] = 4;
			}
		} else {
			// Double élimination : le perdant de la finale du repêchage est 3e.
			$lb_final = null;
			foreach ( $matches as $match ) {
				if ( 0 === strpos( $match['round'], 'lb' ) ) {
					if ( null === $lb_final || (int) $match['round_no'] > (int) $lb_final['round_no'] ) {
						$lb_final = $match;
					}
				}
			}
			if ( $lb_final && 'done' === $lb_final['status'] ) {
				$w     = (int) $lb_final['winner_id'];
				$loser = $w === (int) $lb_final['team1_id'] ? (int) $lb_final['team2_id'] : (int) $lb_final['team1_id'];
				if ( $loser && ! isset( $placed[ $loser ] ) ) {
					$placed[ $loser ] = 3;
				}
			}
		}

		// Profondeur atteinte par les autres.
		$depth = array();
		foreach ( $teams as $id => $team ) {
			if ( 'active' !== $team['status'] ) {
				continue;
			}
			$depth[ (int) $id ] = 0;
		}

		foreach ( $matches as $match ) {
			// Les matchs de classement ne comptent pas dans la profondeur.
			if ( in_array( $match['code'], array( 'P3' ), true ) ) {
				continue;
			}
			foreach ( array( (int) $match['team1_id'], (int) $match['team2_id'] ) as $id ) {
				if ( $id && isset( $depth[ $id ] ) ) {
					$depth[ $id ] = max( $depth[ $id ], (int) $match['round_no'] );
				}
			}
		}

		// Phase de poules : les équipes éliminées en poule sont départagées
		// par leur place dans leur poule, puis victoires et différence.
		$group_pos = array();
		foreach ( self::groups( $tid, $matches ) as $g ) {
			foreach ( self::group( $tid, $g, $matches, $teams ) as $row ) {
				$group_pos[ (int) $row['team_id'] ] = $row;
			}
		}

		$rest = array();
		foreach ( $depth as $id => $level ) {
			if ( isset( $placed[ $id ] ) ) {
				continue;
			}
			$rest[] = array(
				'team_id' => $id,
				'depth'   => $level,
				'grank'   => isset( $group_pos[ $id ] ) ? (int) $group_pos[ $id ]['rank'] : 0,
				'wins'    => isset( $group_pos[ $id ] ) ? (int) $group_pos[ $id ]['wins'] : 0,
				'diff'    => isset( $group_pos[ $id ] ) ? (int) $group_pos[ $id ]['diff'] : 0,
				'seed'    => isset( $teams[ $id ] ) ? (int) $teams[ $id ]['seed'] : 999,
			);
		}

		usort(
			$rest,
			function ( $a, $b ) {
				if ( $a['depth'] !== $b['depth'] ) {
					return $b['depth'] - $a['depth'];
				}
				if ( $a['grank'] !== $b['grank'] ) {
					return $a['grank'] - $b['grank'];
				}
				if ( $a['wins'] !== $b['wins'] ) {
					return $b['wins'] - $a['wins'];
				}
				if ( $a['diff'] !== $b['diff'] ) {
					return $b['diff'] - $a['diff'];
				}
				return $a['seed'] - $b['seed'];
			}
		);

		// Rangs partagés à profondeur (et place de poule) égales.
		$out  = array();
		$next = count( $placed ) + 1;

		asort( $placed );
		foreach ( $placed as $id => $rank ) {
			$out[] = self::ranking_row( $rank, $id, $teams, true );
		}

		$index     = 0;
		$last_key  = null;
		$last_rank = $next;

		foreach ( $rest as $row ) {
			$index++;
			$key = $row['depth'] . ':' . $row['grank'];
			if ( null === $last_key || $key !== $last_key ) {
				$last_rank = $next + $index - 1;
				$last_key  = $key;
			}
			$out[] = self::ranking_row( $last_rank, $row['team_id'], $teams, false );
		}

		return $out;
	}

	/**
	 * Une ligne de classement général.
	 *
	 * @param int   $rank    Rang.
	 * @param int   $team_id Équipe.
	 * @param array $teams   Équipes indexées.
	 * @param bool  $exact   Rang exact (podium) ou partagé.
	 * @return array
	 */
	protected static function ranking_row( $rank, $team_id, $teams, $exact ) {
		return array(
			'rank'    => (int) $rank,
			'team_id' => (int) $team_id,
			'name'    => WGT_Data::team_name( $team_id, $teams ),
			'seed'    => isset( $teams[ $team_id ] ) ? (int) $teams[ $team_id ]['seed'] : 0,
			'exact'   => (bool) $exact,
		);
	}
}
