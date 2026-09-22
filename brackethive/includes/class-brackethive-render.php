<?php
/**
 * Rendu HTML des vues publiques.
 *
 * Chaque vue est rendue pour un tournoi donné, transmis via
 * $atts['tournament_id'].
 *
 * @package Brackethive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Brackethive_Render {

	/**
	 * Vues disponibles.
	 *
	 * @return array
	 */
	public static function views() {
		return array( 'bracket', 'groups', 'ranking', 'planning', 'teams', 'results', 'rules', 'staff', 'checklist', 'registration', 'list', 'full' );
	}

	/**
	 * Point d'entrée : rend une vue.
	 *
	 * @param string $view Nom de la vue.
	 * @param array  $atts Attributs, dont tournament_id.
	 * @return string
	 */
	public static function view( $view, $atts = array() ) {
		if ( ! in_array( $view, self::views(), true ) ) {
			return '';
		}

		$atts = is_array( $atts ) ? $atts : array();

		if ( 'list' !== $view ) {
			$tid = isset( $atts['tournament_id'] ) ? (int) $atts['tournament_id'] : 0;
			if ( ! $tid ) {
				$tid                   = Brackethive_Tournament::current_public();
				$atts['tournament_id'] = $tid;
			}
			if ( ! $tid ) {
				return self::wrap( $view, '<p class="brackethive-empty">' . esc_html__( 'No tournament has been created yet.', 'brackethive' ) . '</p>', false, 0 );
			}
		}

		return call_user_func( array( __CLASS__, 'render_' . $view ), $atts );
	}

	/**
	 * Identifiant du tournoi porté par les attributs.
	 *
	 * @param array $atts Attributs.
	 * @return int
	 */
	protected static function tid( $atts ) {
		return isset( $atts['tournament_id'] ) ? (int) $atts['tournament_id'] : 0;
	}

	/**
	 * Attribut « header » normalisé (yes|no).
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function header_mode( $atts ) {
		return ( isset( $atts['header'] ) && 'no' === $atts['header'] ) ? 'no' : 'yes';
	}

	/**
	 * Attribut « fit » normalisé (screen|width).
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function fit_mode( $atts ) {
		return ( isset( $atts['fit'] ) && 'width' === $atts['fit'] ) ? 'width' : 'screen';
	}

	/**
	 * Enveloppe commune.
	 *
	 * Les vues rafraîchies automatiquement portent leurs options d'affichage
	 * (en-tête, ajustement) en attributs data-* : le script les renvoie à la
	 * route REST, qui reproduit ainsi exactement le rendu du code court.
	 *
	 * @param string $view Nom de la vue.
	 * @param string $html Contenu.
	 * @param bool   $live Rafraîchissement automatique.
	 * @param int    $tid  Tournoi.
	 * @param array  $atts Attributs de la vue (header, fit).
	 * @return string
	 */
	protected static function wrap( $view, $html, $live = false, $tid = 0, $atts = array() ) {
		$accent = $tid ? Brackethive_Tournament::get( $tid, 'accent_color' ) : '#e63946';

		$attrs  = 'class="brackethive brackethive-view brackethive-view--' . esc_attr( $view ) . '"';
		$attrs .= ' style="--brackethive-accent:' . esc_attr( $accent ) . '"';

		if ( $live && (int) Brackethive_Settings::get( 'refresh_interval' ) > 0 ) {
			$attrs .= ' data-brackethive-live="' . esc_attr( $view ) . '"';
			$attrs .= ' data-brackethive-tournament="' . (int) $tid . '"';
			$attrs .= ' data-brackethive-header="' . esc_attr( self::header_mode( $atts ) ) . '"';
			$attrs .= ' data-brackethive-fit="' . esc_attr( self::fit_mode( $atts ) ) . '"';
		}

		return '<div ' . $attrs . '>' . $html . '</div>';
	}

	/* ---------------------------------------------------------------------
	 * En-tête
	 * ------------------------------------------------------------------ */

	/**
	 * Bandeau d'en-tête du tournoi.
	 *
	 * @param int $tid Tournoi.
	 * @return string
	 */
	public static function header_html( $tid ) {
		$s = Brackethive_Tournament::settings( $tid );

		$out  = '<header class="brackethive-header">';
		$out .= '<h2 class="brackethive-header__title">' . esc_html( $s['tournament_name'] );
		if ( '' !== $s['game_name'] ) {
			$out .= ' <span class="brackethive-header__game">' . esc_html( $s['game_name'] ) . '</span>';
		}
		$out .= '</h2>';

		if ( '' !== $s['subtitle'] ) {
			$out .= '<p class="brackethive-header__subtitle">' . esc_html( $s['subtitle'] ) . '</p>';
		}

		$meta = array();
		if ( '' !== $s['event_date'] ) {
			$ts     = strtotime( $s['event_date'] );
			$meta[] = $ts ? date_i18n( get_option( 'date_format' ), $ts ) : $s['event_date'];
		}
		if ( '' !== $s['start_time'] ) {
			/* translators: %s: heure de début */
			$meta[] = sprintf( __( 'Starts at %s', 'brackethive' ), str_replace( ':', 'h', $s['start_time'] ) );
		}
		if ( '' !== $s['venue'] ) {
			$meta[] = $s['venue'];
		}
		$meta[] = sprintf(
			/* translators: %d: nombre de postes */
			__( '%d gaming stations', 'brackethive' ),
			(int) $s['stations']
		);

		$out .= '<p class="brackethive-header__meta">' . esc_html( implode( ' · ', $meta ) ) . '</p>';
		$out .= '</header>';

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Tableau
	 * ------------------------------------------------------------------ */

	/**
	 * Le tableau à élimination directe.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_bracket( $atts = array() ) {
		$tid      = self::tid( $atts );
		$settings = Brackethive_Tournament::settings( $tid );
		$rounds   = Brackethive_Data::get_matches_by_round( $tid );
		$teams    = Brackethive_Data::get_teams_map( $tid );
		$labels   = Brackethive_Bracket::round_order( $settings );
		$stats    = Brackethive_Data::get_stats( $tid );
		$by_code  = Brackethive_Data::get_matches_map( $tid );
		$show_hd  = 'no' !== self::header_mode( $atts );

		$out = '';
		if ( $show_hd ) {
			$out .= self::header_html( $tid );
		}

		if ( $stats['champion_id'] ) {
			$out .= '<p class="brackethive-champion"><span class="brackethive-champion__label">' . esc_html__( 'Tournament winner', 'brackethive' ) . '</span><strong>' . esc_html( Brackethive_Data::team_name( $stats['champion_id'], $teams ) ) . '</strong>';
			if ( $stats['third_id'] ) {
				$out .= '<span class="brackethive-champion__third">' . esc_html(
					sprintf(
						/* translators: %s: nom de l'équipe */
						__( 'Third place: %s', 'brackethive' ),
						Brackethive_Data::team_name( $stats['third_id'], $teams )
					)
				) . '</span>';
			}
			$out .= '</p>';
		}

		// Phase de poules : classements avant le tableau.
		if ( isset( $rounds['group'] ) ) {
			$out .= self::groups_html( $tid, $teams );
		}

		// Sections : tableau principal d'un côté, repêchage de l'autre.
		$sections = array(
			'main'   => array( 'title' => '', 'rounds' => array() ),
			'losers' => array( 'title' => __( 'Losers bracket', 'brackethive' ), 'rounds' => array() ),
		);

		foreach ( $labels as $key => $label ) {
			if ( 'group' === $key || empty( $rounds[ $key ] ) ) {
				continue;
			}
			$target = ( 0 === strpos( $key, 'lb' ) ) ? 'losers' : 'main';
			$sections[ $target ]['rounds'][ $key ] = $label;
		}

		$fit_mode = self::fit_mode( $atts );

		foreach ( $sections as $section ) {
			if ( empty( $section['rounds'] ) ) {
				continue;
			}

			if ( '' !== $section['title'] ) {
				$out .= '<h3 class="brackethive-section-title">' . esc_html( $section['title'] ) . '</h3>';
			}

			$out .= '<div class="brackethive-bracket-scroll is-fit" data-brackethive-fit data-brackethive-fit-mode="' . esc_attr( $fit_mode ) . '">';
			$out .= '<div class="brackethive-bracket-fit"><div class="brackethive-bracket">';

			foreach ( $section['rounds'] as $key => $label ) {
				$out .= '<div class="brackethive-round brackethive-round--' . esc_attr( $key ) . '">';
				$out .= '<h3 class="brackethive-round__title">' . esc_html( $label ) . '</h3>';
				$out .= '<div class="brackethive-round__matches">';
				foreach ( $rounds[ $key ] as $match ) {
					// La seconde grande finale n'apparaît que si elle a lieu.
					if ( ! Brackethive_Data::is_match_needed( $match, $by_code ) ) {
						continue;
					}
					$out .= self::match_card( $match, $teams );
				}
				$out .= '</div></div>';
			}

			$out .= '</div></div></div>';
		}

		$out .= '<p class="brackethive-legend">' . esc_html( self::format_legend( $settings ) ) . '</p>';

		return self::wrap( 'bracket', $out, true, $tid, $atts );
	}

	/**
	 * Classements des poules.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_groups( $atts = array() ) {
		$tid   = self::tid( $atts );
		$teams = Brackethive_Data::get_teams_map( $tid );
		$html  = self::groups_html( $tid, $teams );

		if ( '' === $html ) {
			$html = '<p class="brackethive-empty">' . esc_html__( 'This tournament has no group stage.', 'brackethive' ) . '</p>';
		}

		return self::wrap( 'groups', $html, true, $tid );
	}

	/**
	 * Tables de classement de chaque poule.
	 *
	 * @param int   $tid   Tournoi.
	 * @param array $teams Équipes indexées.
	 * @return string
	 */
	protected static function groups_html( $tid, $teams ) {
		$matches = Brackethive_Data::get_matches( $tid );
		$groups  = Brackethive_Standings::groups( $tid, $matches );

		if ( empty( $groups ) ) {
			return '';
		}

		$qualifiers = (int) Brackethive_Tournament::get( $tid, 'qualifiers_per_group' );

		$out  = '<h3 class="brackethive-section-title">' . esc_html__( 'Group stage', 'brackethive' ) . '</h3>';
		$out .= '<div class="brackethive-groups">';

		foreach ( $groups as $group_id ) {
			$table = Brackethive_Standings::group( $tid, $group_id, $matches, $teams );

			$out .= '<div class="brackethive-group">';
			$out .= '<h4 class="brackethive-group__title">' . esc_html(
				sprintf(
					/* translators: %s: lettre de la poule */
					__( 'Group %s', 'brackethive' ),
					Brackethive_Data::group_name( $group_id )
				)
			) . '</h4>';

			$out .= '<table class="brackethive-table brackethive-table--standings"><thead><tr>';
			$out .= '<th>#</th><th>' . esc_html__( 'Team', 'brackethive' ) . '</th>';
			$out .= '<th>' . esc_html__( 'P', 'brackethive' ) . '</th>';
			$out .= '<th>' . esc_html__( 'W', 'brackethive' ) . '</th>';
			$out .= '<th>' . esc_html__( 'L', 'brackethive' ) . '</th>';
			$out .= '<th>' . esc_html__( 'Diff.', 'brackethive' ) . '</th>';
			$out .= '</tr></thead><tbody>';

			foreach ( $table as $row ) {
				$cls  = $row['rank'] <= $qualifiers ? ' class="is-qualified"' : '';
				$out .= '<tr' . $cls . '>';
				$out .= '<td>' . (int) $row['rank'] . '</td>';
				$out .= '<td class="brackethive-cell__teams">' . esc_html( $row['name'] ) . '</td>';
				$out .= '<td>' . (int) $row['played'] . '</td>';
				$out .= '<td>' . (int) $row['wins'] . '</td>';
				$out .= '<td>' . (int) $row['losses'] . '</td>';
				$out .= '<td>' . ( $row['diff'] > 0 ? '+' : '' ) . (int) $row['diff'] . '</td>';
				$out .= '</tr>';
			}

			$out .= '</tbody></table></div>';
		}

		$out .= '</div>';

		return $out;
	}

	/**
	 * Résumé du format, pour la légende.
	 *
	 * @param array $settings Réglages.
	 * @return string
	 */
	protected static function format_legend( $settings ) {
		$names = array(
			'single' => __( 'Single elimination', 'brackethive' ),
			'double' => __( 'Double elimination', 'brackethive' ),
			'groups' => __( 'Group stage then playoffs', 'brackethive' ),
		);

		$format = isset( $settings['format'] ) ? $settings['format'] : 'single';
		$parts  = array( isset( $names[ $format ] ) ? $names[ $format ] : $names['single'] );

		$parts[] = sprintf(
			/* translators: %d: nombre d'équipes */
			__( '%d teams', 'brackethive' ),
			(int) $settings['team_count']
		);

		foreach ( Brackethive_Bracket::round_order( $settings ) as $key => $label ) {
			$bo = self::round_bo( $key, $settings );
			if ( $bo ) {
				$parts[] = $label . ' BO' . $bo;
			}
		}

		return implode( ' · ', $parts );
	}

	/**
	 * Nombre de manches d'un tour, d'après les réglages.
	 *
	 * @param string $key      Clé de tour.
	 * @param array  $settings Réglages.
	 * @return int
	 */
	protected static function round_bo( $key, $settings ) {
		if ( 0 === strpos( $key, 'lb' ) ) {
			$key = 'lb';
		}
		if ( 'gf' === $key ) {
			$key = 'final';
		}
		if ( 'third' === $key ) {
			return 1;
		}
		return isset( $settings[ 'bo_' . $key ] ) ? (int) $settings[ 'bo_' . $key ] : 0;
	}

	/**
	 * Une carte de match.
	 *
	 * @param array $match Match.
	 * @param array $teams Équipes indexées.
	 * @return string
	 */
	protected static function match_card( $match, $teams ) {
		$t1     = (int) $match['team1_id'];
		$t2     = (int) $match['team2_id'];
		$winner = (int) $match['winner_id'];
		$done   = 'done' === $match['status'];
		$live   = 'live' === $match['status'];

		$name1 = $t1 ? Brackethive_Data::team_name( $t1, $teams ) : Brackethive_Data::source_label( $match['src1'] );
		$name2 = $t2 ? Brackethive_Data::team_name( $t2, $teams ) : Brackethive_Data::source_label( $match['src2'] );

		$classes = array( 'brackethive-match', 'brackethive-match--' . $match['status'] );

		$out  = '<article class="' . esc_attr( implode( ' ', $classes ) ) . '" id="brackethive-match-' . esc_attr( $match['code'] ) . '">';
		$out .= '<div class="brackethive-match__head">';
		$out .= '<span class="brackethive-match__code">' . esc_html( $match['code'] ) . '</span>';
		$out .= '<span class="brackethive-match__bo">BO' . (int) $match['bo'] . '</span>';
		if ( '' !== $match['start_time'] ) {
			$out .= '<span class="brackethive-match__time">' . esc_html( str_replace( ':', 'h', $match['start_time'] ) ) . '</span>';
		}
		if ( '' !== $match['stations'] ) {
			/* translators: %s: numéros de postes */
			$out .= '<span class="brackethive-match__stations">' . esc_html( sprintf( __( 'Stations %s', 'brackethive' ), $match['stations'] ) ) . '</span>';
		}
		if ( $live ) {
			$out .= '<span class="brackethive-badge brackethive-badge--live">' . esc_html__( 'In progress', 'brackethive' ) . '</span>';
		}
		$out .= '</div>';

		foreach ( array( array( $t1, $name1, (int) $match['score1'] ), array( $t2, $name2, (int) $match['score2'] ) ) as $side ) {
			$is_win  = $done && $winner && $winner === $side[0];
			$is_lose = $done && $winner && $side[0] && $winner !== $side[0];
			$cls     = 'brackethive-slot';
			if ( $is_win ) {
				$cls .= ' is-winner';
			}
			if ( $is_lose ) {
				$cls .= ' is-loser';
			}
			if ( ! $side[0] ) {
				$cls .= ' is-tbd';
			}
			$out .= '<div class="' . esc_attr( $cls ) . '">';
			$out .= '<span class="brackethive-slot__name">' . esc_html( $side[1] ) . '</span>';
			$out .= '<span class="brackethive-slot__score">' . ( ( $done || $live ) ? esc_html( $side[2] ) : '–' ) . '</span>';
			$out .= '</div>';
		}

		if ( (int) $match['bo'] > 1 ) {
			$games = Brackethive_Data::get_games( (int) $match['id'] );
			if ( ! empty( $games ) ) {
				$parts = array();
				foreach ( $games as $game ) {
					$parts[] = (int) $game['score1'] . '-' . (int) $game['score2'];
				}
				$out .= '<div class="brackethive-match__games">' . esc_html__( 'Games:', 'brackethive' ) . ' ' . esc_html( implode( ' | ', $parts ) ) . '</div>';
			}
		}

		$out .= '</article>';

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Planning
	 * ------------------------------------------------------------------ */

	/**
	 * Planning horaire.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_planning( $atts = array() ) {
		$tid     = self::tid( $atts );
		$matches = Brackethive_Data::get_matches( $tid );
		$teams   = Brackethive_Data::get_teams_map( $tid );

		$out = '<h3 class="brackethive-section-title">' . esc_html__( 'Time schedule', 'brackethive' ) . '</h3>';

		if ( empty( $matches ) ) {
			return self::wrap( 'planning', $out . '<p class="brackethive-empty">' . esc_html__( 'No match scheduled.', 'brackethive' ) . '</p>', false, $tid );
		}

		// Regroupement par créneau horaire.
		$slots = array();
		foreach ( $matches as $match ) {
			$key = '' !== $match['start_time'] ? $match['start_time'] : '~';
			$slots[ $key ][] = $match;
		}
		ksort( $slots );

		$columns = 1;
		foreach ( $slots as $list ) {
			$columns = max( $columns, count( $list ) );
		}
		$columns = min( $columns, 4 );

		// En-têtes : les postes du premier créneau complet, sinon un numéro.
		$headers = array();
		foreach ( $slots as $list ) {
			if ( count( $list ) === $columns ) {
				foreach ( $list as $match ) {
					$headers[] = '' !== $match['stations']
						? sprintf( /* translators: %s: numéros de postes */ __( 'Stations %s', 'brackethive' ), $match['stations'] )
						: '';
				}
				break;
			}
		}

		$out .= '<div class="brackethive-table-scroll"><table class="brackethive-table brackethive-table--planning">';
		$out .= '<thead><tr><th>' . esc_html__( 'Time', 'brackethive' ) . '</th>';
		for ( $i = 0; $i < $columns; $i++ ) {
			$label = isset( $headers[ $i ] ) && '' !== $headers[ $i ]
				? $headers[ $i ]
				: sprintf( /* translators: %d: numéro de colonne */ __( 'Match %d', 'brackethive' ), $i + 1 );
			$out  .= '<th>' . esc_html( $label ) . '</th>';
		}
		$out .= '</tr></thead><tbody>';

		foreach ( $slots as $time => $list ) {
			$end = '';
			foreach ( $list as $match ) {
				if ( '' !== $match['end_time'] ) {
					$end = $match['end_time'];
					break;
				}
			}

			$label = '~' === $time
				? __( 'Not scheduled', 'brackethive' )
				: str_replace( ':', 'h', $time ) . ( '' !== $end ? ' - ' . str_replace( ':', 'h', $end ) : '' );

			$out .= '<tr><td class="brackethive-table__slot">' . esc_html( $label ) . '</td>';

			for ( $i = 0; $i < $columns; $i++ ) {
				if ( isset( $list[ $i ] ) ) {
					$out .= '<td>' . self::planning_cell( $list[ $i ], $teams ) . '</td>';
				} else {
					$out .= '<td class="brackethive-table__note"></td>';
				}
			}

			$out .= '</tr>';
		}

		$out .= '</tbody></table></div>';
		$out .= '<p class="brackethive-note">' . esc_html__( 'Times are indicative: they assume matches without significant delay. A match played over several games may push back the rest of the schedule.', 'brackethive' ) . '</p>';

		return self::wrap( 'planning', $out, true, $tid );
	}

	/**
	 * Cellule de planning.
	 *
	 * @param array $match Match.
	 * @param array $teams Équipes.
	 * @return string
	 */
	protected static function planning_cell( $match, $teams ) {
		$n1 = (int) $match['team1_id'] ? Brackethive_Data::team_name( (int) $match['team1_id'], $teams ) : Brackethive_Data::source_label( $match['src1'] );
		$n2 = (int) $match['team2_id'] ? Brackethive_Data::team_name( (int) $match['team2_id'], $teams ) : Brackethive_Data::source_label( $match['src2'] );

		$out  = '<span class="brackethive-cell__code">' . esc_html( $match['code'] ) . '</span> ';
		$out .= '<span class="brackethive-cell__teams">' . esc_html( $n1 ) . ' <em>' . esc_html__( 'vs', 'brackethive' ) . '</em> ' . esc_html( $n2 ) . '</span>';

		if ( 'done' === $match['status'] ) {
			$out .= ' <span class="brackethive-cell__score">' . (int) $match['score1'] . ' - ' . (int) $match['score2'] . '</span>';
		} elseif ( 'live' === $match['status'] ) {
			$out .= ' <span class="brackethive-badge brackethive-badge--live">' . esc_html__( 'In progress', 'brackethive' ) . '</span>';
		}

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Équipes
	 * ------------------------------------------------------------------ */

	/**
	 * Liste des équipes engagées.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_teams( $atts = array() ) {
		$tid   = self::tid( $atts );
		$teams = Brackethive_Data::get_teams( array( 'tournament_id' => $tid, 'status' => 'active' ) );

		$out = '<h3 class="brackethive-section-title">' . esc_html__( 'Participating teams', 'brackethive' ) . '</h3>';

		if ( empty( $teams ) ) {
			return self::wrap( 'teams', $out . '<p class="brackethive-empty">' . esc_html__( 'No team approved yet.', 'brackethive' ) . '</p>', false, $tid );
		}

		$open = isset( $atts['players'] ) && 'yes' === $atts['players'];

		$out .= '<ul class="brackethive-teams">';
		foreach ( $teams as $team ) {
			$players    = self::split_players( $team['players'] );
			$has_detail = ! empty( $players );

			$head  = '<span class="brackethive-team__seed">' . ( (int) $team['seed'] ? esc_html( (int) $team['seed'] ) : '–' ) . '</span>';
			$head .= '<span class="brackethive-team__name">' . esc_html( $team['name'] );
			if ( '' !== $team['tag'] ) {
				$head .= ' <span class="brackethive-team__tag">[' . esc_html( $team['tag'] ) . ']</span>';
			}
			$head .= '</span>';
			if ( '' !== $team['captain'] ) {
				/* translators: %s: nom du capitaine */
				$head .= '<span class="brackethive-team__captain">' . esc_html( sprintf( __( 'Captain: %s', 'brackethive' ), $team['captain'] ) ) . '</span>';
			}

			if ( ! $has_detail ) {
				$out .= '<li class="brackethive-team brackethive-team--plain">' . $head . '</li>';
				continue;
			}

			$count = count( $players );

			$out .= '<li class="brackethive-team brackethive-team--expandable">';
			$out .= '<details class="brackethive-team__details"' . ( $open ? ' open' : '' ) . '>';
			$out .= '<summary class="brackethive-team__summary">' . $head;
			$out .= '<span class="brackethive-team__count">' . esc_html(
				sprintf(
					/* translators: %d: nombre de joueurs */
					_n( '%d player', '%d players', $count, 'brackethive' ),
					$count
				)
			) . '</span>';
			$out .= '</summary>';
			$out .= '<ul class="brackethive-team__players">';
			foreach ( $players as $player ) {
				$out .= '<li>' . esc_html( $player ) . '</li>';
			}
			$out .= '</ul>';
			$out .= '</details>';
			$out .= '</li>';
		}
		$out .= '</ul>';

		// Pas de rafraîchissement : il refermerait les fiches ouvertes.
		return self::wrap( 'teams', $out, false, $tid );
	}

	/**
	 * Découpe la saisie « un joueur par ligne ».
	 *
	 * @param string $raw Contenu brut.
	 * @return array
	 */
	protected static function split_players( $raw ) {
		if ( ! is_string( $raw ) || '' === trim( $raw ) ) {
			return array();
		}

		$out = array();
		foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
			$line = trim( $line );
			if ( '' !== $line ) {
				$out[] = $line;
			}
		}

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Résultats
	 * ------------------------------------------------------------------ */

	/**
	 * Feuille de résultats.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_results( $atts = array() ) {
		$tid      = self::tid( $atts );
		$settings = Brackethive_Tournament::settings( $tid );
		$matches  = Brackethive_Data::get_matches( $tid );
		$teams    = Brackethive_Data::get_teams_map( $tid );
		$labels   = Brackethive_Bracket::round_order( $settings );

		$out  = '<h3 class="brackethive-section-title">' . esc_html__( 'Results', 'brackethive' ) . '</h3>';
		$out .= '<div class="brackethive-table-scroll"><table class="brackethive-table brackethive-table--results">';
		$out .= '<thead><tr>';
		$out .= '<th>' . esc_html__( 'Match', 'brackethive' ) . '</th>';
		$out .= '<th>' . esc_html__( 'Round', 'brackethive' ) . '</th>';
		$out .= '<th>' . esc_html__( 'Team 1', 'brackethive' ) . '</th>';
		$out .= '<th>' . esc_html__( 'Score', 'brackethive' ) . '</th>';
		$out .= '<th>' . esc_html__( 'Team 2', 'brackethive' ) . '</th>';
		$out .= '<th>' . esc_html__( 'Stations', 'brackethive' ) . '</th>';
		$out .= '<th>' . esc_html__( 'Status', 'brackethive' ) . '</th>';
		$out .= '</tr></thead><tbody>';

		$by_code = Brackethive_Data::get_matches_map( $tid );

		foreach ( $matches as $match ) {
			if ( ! Brackethive_Data::is_match_needed( $match, $by_code ) ) {
				continue;
			}
			$t1     = (int) $match['team1_id'];
			$t2     = (int) $match['team2_id'];
			$winner = (int) $match['winner_id'];
			$n1     = $t1 ? Brackethive_Data::team_name( $t1, $teams ) : Brackethive_Data::source_label( $match['src1'] );
			$n2     = $t2 ? Brackethive_Data::team_name( $t2, $teams ) : Brackethive_Data::source_label( $match['src2'] );
			$done   = 'done' === $match['status'];
			$label  = isset( $labels[ $match['round'] ] ) ? $labels[ $match['round'] ] : $match['round'];

			$win1 = '';
			$win2 = '';
			if ( $done && $winner ) {
				$win1 = $winner === $t1 ? ' class="is-winner"' : ( $t1 ? ' class="is-loser"' : '' );
				$win2 = $winner === $t2 ? ' class="is-winner"' : ( $t2 ? ' class="is-loser"' : '' );
			}

			$out .= '<tr class="brackethive-row--' . esc_attr( $match['status'] ) . '">';
			$out .= '<td><strong>' . esc_html( $match['code'] ) . '</strong></td>';
			$out .= '<td>' . esc_html( $label ) . ' <small>BO' . (int) $match['bo'] . '</small></td>';
			$out .= '<td' . $win1 . '>' . esc_html( $n1 ) . '</td>';
			$out .= '<td class="brackethive-cell__score">' . ( $done || 'live' === $match['status'] ? (int) $match['score1'] . ' - ' . (int) $match['score2'] : '–' ) . '</td>';
			$out .= '<td' . $win2 . '>' . esc_html( $n2 ) . '</td>';
			$out .= '<td>' . esc_html( $match['stations'] ) . '</td>';
			$out .= '<td>' . esc_html( self::status_label( $match['status'] ) ) . '</td>';
			$out .= '</tr>';
		}

		$out .= '</tbody></table></div>';

		return self::wrap( 'results', $out, true, $tid );
	}

	/**
	 * Libellé d'un statut.
	 *
	 * @param string $status Statut.
	 * @return string
	 */
	public static function status_label( $status ) {
		$map = array(
			'pending' => __( 'To play', 'brackethive' ),
			'live'    => __( 'In progress', 'brackethive' ),
			'done'    => __( 'Finished', 'brackethive' ),
		);
		return isset( $map[ $status ] ) ? $map[ $status ] : $status;
	}

	/* ---------------------------------------------------------------------
	 * Règlement, staff, checklist
	 * ------------------------------------------------------------------ */

	/**
	 * Règlement.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_rules( $atts = array() ) {
		$tid = self::tid( $atts );
		$s   = Brackethive_Tournament::settings( $tid );

		$out  = '<h3 class="brackethive-section-title">' . esc_html__( 'Rules', 'brackethive' ) . '</h3>';
		$out .= '<ul class="brackethive-list">';
		// Les réglages sont transmis pour que le règlement reflète l'effectif
		// réel des équipes (players_per_team) plutôt qu'une valeur figée.
		foreach ( Brackethive_Bracket::rules( $s ) as $rule ) {
			$out .= '<li>' . esc_html( $rule ) . '</li>';
		}
		$out .= '</ul>';

		$out .= '<h4 class="brackethive-subsection-title">' . esc_html__( 'Match format', 'brackethive' ) . '</h4>';
		$out .= '<ul class="brackethive-list">';
		$out .= '<li>' . esc_html__( 'BO1: a single game, the team that wins it qualifies.', 'brackethive' ) . '</li>';
		$out .= '<li>' . esc_html__( 'BO3: up to three games, the first team to win two takes the match.', 'brackethive' ) . '</li>';
		$out .= '<li>' . esc_html( self::format_legend( $s ) ) . '</li>';
		$out .= '<li>' . esc_html(
			sprintf(
				/* translators: %d: nombre de joueurs */
				__( 'Teams of %d starting players, one substitute recommended.', 'brackethive' ),
				(int) $s['players_per_team']
			)
		) . '</li>';
		if ( ! empty( $s['third_place'] ) ) {
			$out .= '<li>' . esc_html__( 'A third place match pits the two semi-final losers against each other.', 'brackethive' ) . '</li>';
		}
		$out .= '</ul>';

		return self::wrap( 'rules', $out, false, $tid );
	}

	/**
	 * Personnel nécessaire.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_staff( $atts = array() ) {
		$tid = self::tid( $atts );

		$out  = '<h3 class="brackethive-section-title">' . esc_html__( 'Staff needed', 'brackethive' ) . '</h3>';
		$out .= '<div class="brackethive-table-scroll"><table class="brackethive-table">';
		$out .= '<thead><tr><th>' . esc_html__( 'Role', 'brackethive' ) . '</th><th>' . esc_html__( 'Qty.', 'brackethive' ) . '</th><th>' . esc_html__( 'Main duties', 'brackethive' ) . '</th></tr></thead><tbody>';
		foreach ( Brackethive_Bracket::staff() as $line ) {
			$out .= '<tr><td>' . esc_html( $line[0] ) . '</td><td>' . (int) $line[1] . '</td><td>' . esc_html( $line[2] ) . '</td></tr>';
		}
		$out .= '</tbody></table></div>';

		return self::wrap( 'staff', $out, false, $tid );
	}

	/**
	 * Checklist avant ouverture.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_checklist( $atts = array() ) {
		$tid = self::tid( $atts );

		$out  = '<h3 class="brackethive-section-title">' . esc_html__( 'Pre-opening checklist', 'brackethive' ) . '</h3>';
		$out .= '<ul class="brackethive-list brackethive-list--check">';
		foreach ( Brackethive_Bracket::checklist() as $item ) {
			$out .= '<li>' . esc_html( $item ) . '</li>';
		}
		$out .= '</ul>';

		return self::wrap( 'checklist', $out, false, $tid );
	}

	/* ---------------------------------------------------------------------
	 * Liste des tournois
	 * ------------------------------------------------------------------ */

	/**
	 * Index des tournois du site.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_list( $atts = array() ) {
		$posts = Brackethive_Tournament::all( array( 'post_status' => 'publish' ) );

		$out = '<h3 class="brackethive-section-title">' . esc_html__( 'Tournaments', 'brackethive' ) . '</h3>';

		if ( empty( $posts ) ) {
			return self::wrap( 'list', $out . '<p class="brackethive-empty">' . esc_html__( 'No published tournament.', 'brackethive' ) . '</p>', false, 0 );
		}

		$out .= '<ul class="brackethive-tournaments">';
		foreach ( $posts as $post ) {
			if ( ! Brackethive_Tournament::has_public_page( $post->ID ) ) {
				continue;
			}
			$s     = Brackethive_Tournament::settings( $post->ID );
			$stats = Brackethive_Data::get_stats( $post->ID );

			$out .= '<li class="brackethive-tournament">';
			$out .= '<a class="brackethive-tournament__link" href="' . esc_url( get_permalink( $post ) ) . '">';
			$out .= '<span class="brackethive-tournament__name">' . esc_html( get_the_title( $post ) ) . '</span>';

			$meta = array();
			if ( '' !== $s['game_name'] ) {
				$meta[] = $s['game_name'];
			}
			if ( '' !== $s['event_date'] ) {
				$ts     = strtotime( $s['event_date'] );
				$meta[] = $ts ? date_i18n( get_option( 'date_format' ), $ts ) : $s['event_date'];
			}
			if ( $meta ) {
				$out .= '<span class="brackethive-tournament__meta">' . esc_html( implode( ' · ', $meta ) ) . '</span>';
			}

			if ( $stats['champion_id'] ) {
				$out .= '<span class="brackethive-tournament__winner">' . esc_html(
					sprintf(
						/* translators: %s: nom de l'équipe */
						__( 'Winner: %s', 'brackethive' ),
						Brackethive_Data::team_name( $stats['champion_id'], Brackethive_Data::get_teams_map( $post->ID ) )
					)
				) . '</span>';
			}

			$out .= '</a></li>';
		}
		$out .= '</ul>';

		return self::wrap( 'list', $out, false, 0 );
	}

	/**
	 * Classement général du tournoi.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_ranking( $atts = array() ) {
		$tid  = self::tid( $atts );
		$rows = Brackethive_Standings::final_ranking( $tid );

		$out = '<h3 class="brackethive-section-title">' . esc_html__( 'Overall ranking', 'brackethive' ) . '</h3>';

		if ( empty( $rows ) ) {
			return self::wrap( 'ranking', $out . '<p class="brackethive-empty">' . esc_html__( 'The ranking will build up as matches are played.', 'brackethive' ) . '</p>', true, $tid );
		}

		$out .= '<div class="brackethive-table-scroll"><table class="brackethive-table brackethive-table--ranking"><thead><tr>';
		$out .= '<th>' . esc_html__( 'Rank', 'brackethive' ) . '</th>';
		$out .= '<th>' . esc_html__( 'Team', 'brackethive' ) . '</th>';
		$out .= '<th>' . esc_html__( 'Starting seed', 'brackethive' ) . '</th>';
		$out .= '</tr></thead><tbody>';

		foreach ( $rows as $row ) {
			$cls = $row['rank'] <= 3 && $row['exact'] ? ' class="is-podium is-rank-' . (int) $row['rank'] . '"' : '';
			$out .= '<tr' . $cls . '>';
			$out .= '<td class="brackethive-rank">' . ( $row['exact'] ? (int) $row['rank'] : (int) $row['rank'] . '<sup>+</sup>' ) . '</td>';
			$out .= '<td class="brackethive-cell__teams">' . esc_html( $row['name'] ) . '</td>';
			$out .= '<td>' . ( $row['seed'] ? (int) $row['seed'] : '—' ) . '</td>';
			$out .= '</tr>';
		}

		$out .= '</tbody></table></div>';
		$out .= '<p class="brackethive-note">' . esc_html__( 'Teams eliminated in the same round share the same rank, marked with a "+". The ranking becomes more precise as matches are approved.', 'brackethive' ) . '</p>';

		return self::wrap( 'ranking', $out, true, $tid );
	}

	/* ---------------------------------------------------------------------
	 * Inscription
	 * ------------------------------------------------------------------ */

	/**
	 * Formulaire d'inscription d'équipe.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_registration( $atts = array() ) {
		$tid = self::tid( $atts );
		$s   = Brackethive_Tournament::settings( $tid );

		$out  = '<div id="brackethive-registration" class="brackethive-anchor"></div>';
		$out .= '<h3 class="brackethive-section-title">' . esc_html__( 'Team sign-up', 'brackethive' ) . '</h3>';

		$feedback = Brackethive_Registration::get_feedback();
		if ( $feedback ) {
			$out .= '<div class="brackethive-notice brackethive-notice--' . esc_attr( $feedback['type'] ) . '">' . esc_html( $feedback['message'] ) . '</div>';
		}

		if ( empty( $s['registration_open'] ) ) {
			$out .= '<p class="brackethive-empty">' . esc_html__( 'Sign-ups are currently closed.', 'brackethive' ) . '</p>';
			return self::wrap( 'registration', $out, false, $tid );
		}

		$count = Brackethive_Data::count_registered( $tid );
		if ( (int) $s['registration_max'] > 0 && $count >= (int) $s['registration_max'] ) {
			$out .= '<p class="brackethive-empty">' . esc_html__( 'The maximum number of teams has been reached.', 'brackethive' ) . '</p>';
			return self::wrap( 'registration', $out, false, $tid );
		}

		$out .= '<form class="brackethive-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		$out .= '<input type="hidden" name="action" value="brackethive_register_team" />';
		$out .= '<input type="hidden" name="tournament_id" value="' . (int) $tid . '" />';
		$out .= '<input type="hidden" name="brackethive_redirect" value="' . esc_url( Brackethive_Registration::current_url() ) . '" />';
		$out .= wp_nonce_field( 'brackethive_register_team', 'brackethive_nonce', true, false );
		$out .= '<p class="brackethive-hp"><label>' . esc_html__( 'Do not fill in', 'brackethive' ) . ' <input type="text" name="brackethive_website" value="" tabindex="-1" autocomplete="off" /></label></p>';

		/*
		 * Formulaire simplifié (attribut simple="yes") : équipe, pseudo,
		 * e-mail et téléphone. Le pseudo est enregistré comme capitaine ;
		 * tag et liste des joueurs sont saisis par l'organisation.
		 */
		$simple = isset( $atts['simple'] ) && in_array( strtolower( (string) $atts['simple'] ), array( 'yes', '1', 'true', 'oui' ), true );

		$out .= '<p class="brackethive-field"><label for="brackethive-name">' . esc_html( $simple ? _x( 'Team', 'short sign-up form label', 'brackethive' ) : __( 'Team name', 'brackethive' ) ) . ' *</label><input type="text" id="brackethive-name" name="name" required maxlength="120" /></p>';
		if ( ! $simple ) {
			$out .= '<p class="brackethive-field"><label for="brackethive-tag">' . esc_html__( 'Tag (optional)', 'brackethive' ) . '</label><input type="text" id="brackethive-tag" name="tag" maxlength="20" /></p>';
		}
		$out .= '<p class="brackethive-field"><label for="brackethive-captain">' . esc_html( $simple ? __( 'Nickname', 'brackethive' ) : __( 'Captain', 'brackethive' ) ) . ' *</label><input type="text" id="brackethive-captain" name="captain" required maxlength="120" /></p>';
		$out .= '<p class="brackethive-field"><label for="brackethive-email">' . esc_html( $simple ? __( 'Mail', 'brackethive' ) : __( 'Captain\'s email', 'brackethive' ) ) . ' *</label><input type="email" id="brackethive-email" name="email" required maxlength="190" /></p>';
		$out .= '<p class="brackethive-field"><label for="brackethive-phone">' . esc_html__( 'Phone', 'brackethive' ) . ( $simple ? ' *' : '' ) . '</label><input type="tel" id="brackethive-phone" name="phone" maxlength="40"' . ( $simple ? ' required' : '' ) . ' /></p>';
		if ( ! $simple ) {
			$out .= '<p class="brackethive-field"><label for="brackethive-players">' . esc_html(
				sprintf(
					/* translators: %d: nombre de joueurs */
					__( 'Players (%d starters + substitute, one per line)', 'brackethive' ),
					(int) $s['players_per_team']
				)
			) . '</label><textarea id="brackethive-players" name="players" rows="5"></textarea></p>';
		}
		$out .= '<p class="brackethive-field brackethive-field--consent"><label><input type="checkbox" name="consent" value="1" required /> ' . esc_html__( 'I accept the tournament rules.', 'brackethive' ) . '</label></p>';
		$out .= '<p class="brackethive-submit"><button type="submit" class="brackethive-button">' . esc_html__( 'Send sign-up', 'brackethive' ) . '</button></p>';
		$out .= '</form>';

		return self::wrap( 'registration', $out, false, $tid );
	}

	/* ---------------------------------------------------------------------
	 * Vue complète
	 * ------------------------------------------------------------------ */

	/**
	 * Page complète à onglets.
	 *
	 * @param array $atts Attributs.
	 * @return string
	 */
	public static function render_full( $atts = array() ) {
		$tid = self::tid( $atts );
		$s   = Brackethive_Tournament::settings( $tid );

		$tabs = array();
		if ( 'groups' === $s['format'] ) {
			$tabs['groups'] = __( 'Groups', 'brackethive' );
		}
		$tabs += array(
			'bracket'  => __( 'Bracket', 'brackethive' ),
			'planning' => __( 'Schedule', 'brackethive' ),
			'results'  => __( 'Results', 'brackethive' ),
			'ranking'  => __( 'Ranking', 'brackethive' ),
			'teams'    => __( 'Teams', 'brackethive' ),
		);
		if ( ! empty( $s['show_rules'] ) ) {
			$tabs['rules'] = __( 'Rules', 'brackethive' );
		}
		if ( ! empty( $s['show_staff'] ) ) {
			$tabs['staff'] = __( 'Organizers', 'brackethive' );
		}
		if ( ! empty( $s['registration_open'] ) ) {
			$tabs['registration'] = __( 'Sign-up', 'brackethive' );
		}

		$out  = self::header_html( $tid );
		$out .= '<div class="brackethive-tabs" data-brackethive-tabs>';
		$out .= '<div class="brackethive-tabs__nav" role="tablist">';

		$first = true;
		foreach ( $tabs as $key => $label ) {
			$out  .= '<button type="button" class="brackethive-tabs__btn' . ( $first ? ' is-active' : '' ) . '" role="tab" aria-selected="' . ( $first ? 'true' : 'false' ) . '" data-brackethive-tab="' . esc_attr( $key ) . '">' . esc_html( $label ) . '</button>';
			$first = false;
		}
		$out .= '</div>';

		$first = true;
		foreach ( $tabs as $key => $label ) {
			// data-brackethive-label : titre de l'onglet, repris à l'impression où
			// tous les panneaux sont visibles à la suite.
			$out  .= '<div class="brackethive-tabs__panel' . ( $first ? ' is-active' : '' ) . '" role="tabpanel" data-brackethive-panel="' . esc_attr( $key ) . '" data-brackethive-label="' . esc_attr( $label ) . '">';
			$sub   = array( 'tournament_id' => $tid );
			if ( 'bracket' === $key ) {
				// L'en-tête est déjà affiché au-dessus des onglets.
				$sub['header'] = 'no';
				$sub['fit']    = self::fit_mode( $atts );
			}
			$out  .= self::view( $key, $sub );
			$out  .= '</div>';
			$first = false;
		}

		$out .= '</div>';

		return self::wrap( 'full', $out, false, $tid );
	}
}
