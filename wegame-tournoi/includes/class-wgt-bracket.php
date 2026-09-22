<?php
/**
 * Générateur de structure de tournoi.
 *
 * Trois formats :
 *  - « single » : élimination directe, 2 à 64 équipes, exemptions gérées ;
 *  - « double » : double élimination (repêchage, seconde finale optionnelle) ;
 *  - « groups » : phase de poules puis phase finale.
 *
 * Le placement des têtes de série suit l'algorithme classique : pour 16
 * équipes il reproduit exactement le tableau du dossier We Game 2026
 * (1v16, 8v9, 4v13, 5v12, 2v15, 7v10, 3v14, 6v11).
 *
 * Vocabulaire des sources :
 *  « S<n> »        tête de série n
 *  « BYE »         place vide (exemption)
 *  « W:<code> »    vainqueur d'un match
 *  « L:<code> »    perdant d'un match
 *  « GS:<g>:<i> »  i-ème équipe de la poule g
 *  « G:<g>:<r> »   équipe classée r de la poule g
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Bracket {

	/* ---------------------------------------------------------------------
	 * Outils de placement
	 * ------------------------------------------------------------------ */

	/**
	 * Puissance de deux immédiatement supérieure ou égale.
	 *
	 * @param int $n Nombre.
	 * @return int
	 */
	public static function bracket_size( $n ) {
		$size = 2;
		while ( $size < $n ) {
			$size *= 2;
		}
		return max( 2, $size );
	}

	/**
	 * Ordre de placement des têtes de série dans un tableau de taille $size.
	 *
	 * @param int $size Taille du tableau (puissance de deux).
	 * @return array Liste de numéros de série, par position.
	 */
	public static function seed_order( $size ) {
		$order = array( 1, 2 );

		while ( count( $order ) < $size ) {
			$next  = array();
			$total = count( $order ) * 2;
			foreach ( $order as $seed ) {
				$next[] = $seed;
				$next[] = $total + 1 - $seed;
			}
			$order = $next;
		}

		return $order;
	}

	/**
	 * Clé de tour en fonction du nombre d'équipes encore en lice.
	 *
	 * @param int $remaining Équipes restantes.
	 * @return string
	 */
	public static function round_key( $remaining ) {
		$map = array(
			2  => 'final',
			4  => 'sf',
			8  => 'qf',
			16 => 'r16',
			32 => 'r32',
			64 => 'r64',
		);
		return isset( $map[ $remaining ] ) ? $map[ $remaining ] : 'r' . (int) $remaining;
	}

	/**
	 * Préfixe de code des matchs d'un tour.
	 *
	 * Le premier tour utilise toujours « M », ce qui préserve les codes
	 * historiques M1 à M8 des tournois à 16 équipes.
	 *
	 * @param string $key     Clé de tour.
	 * @param bool   $is_first Premier tour du tableau.
	 * @return string
	 */
	protected static function code_prefix( $key, $is_first ) {
		if ( $is_first ) {
			return 'M';
		}

		$map = array(
			'final' => 'F',
			'sf'    => 'D',
			'qf'    => 'Q',
			'r16'   => 'H',
			'r32'   => 'T',
			'r64'   => 'X',
		);

		return isset( $map[ $key ] ) ? $map[ $key ] : 'R';
	}

	/**
	 * Libellé d'un tour.
	 *
	 * @param string $key Clé de tour.
	 * @param int    $bo  Nombre de manches.
	 * @return string
	 */
	public static function round_label( $key, $bo = 0 ) {
		$labels = array(
			'final' => __( 'Final', 'wegame-tournoi' ),
			'sf'    => __( 'Semi-finals', 'wegame-tournoi' ),
			'qf'    => __( 'Quarter-finals', 'wegame-tournoi' ),
			'r16'   => __( 'Round of 16', 'wegame-tournoi' ),
			'r32'   => __( 'Round of 32', 'wegame-tournoi' ),
			'r64'   => __( 'Round of 64', 'wegame-tournoi' ),
			'third' => __( 'Third place match', 'wegame-tournoi' ),
			'group' => __( 'Group stage', 'wegame-tournoi' ),
			'gf'    => __( 'Grand final', 'wegame-tournoi' ),
		);

		if ( isset( $labels[ $key ] ) ) {
			$label = $labels[ $key ];
		} elseif ( 0 === strpos( $key, 'lb' ) ) {
			/* translators: %d: numéro de tour de repêchage */
			$label = sprintf( __( 'Losers bracket — round %d', 'wegame-tournoi' ), (int) substr( $key, 2 ) );
		} else {
			$label = $key;
		}

		return $bo ? $label . ' — BO' . (int) $bo : $label;
	}

	/**
	 * Libellé court d'un tour.
	 *
	 * @param string $key Clé de tour.
	 * @return string
	 */
	public static function round_short( $key ) {
		$labels = array(
			'final' => __( 'Final', 'wegame-tournoi' ),
			'sf'    => __( 'Semis', 'wegame-tournoi' ),
			'qf'    => __( 'Quarters', 'wegame-tournoi' ),
			'r16'   => __( 'R16', 'wegame-tournoi' ),
			'r32'   => __( 'R32', 'wegame-tournoi' ),
			'r64'   => __( 'R64', 'wegame-tournoi' ),
			'third' => __( 'Third place', 'wegame-tournoi' ),
			'group' => __( 'Groups', 'wegame-tournoi' ),
			'gf'    => __( 'Grand final', 'wegame-tournoi' ),
		);

		if ( isset( $labels[ $key ] ) ) {
			return $labels[ $key ];
		}
		if ( 0 === strpos( $key, 'lb' ) ) {
			/* translators: %d: numéro de tour de repêchage */
			return sprintf( __( 'Losers round %d', 'wegame-tournoi' ), (int) substr( $key, 2 ) );
		}
		return $key;
	}

	/**
	 * Nombre de manches configuré pour un tour.
	 *
	 * @param string $key      Clé de tour.
	 * @param array  $settings Réglages.
	 * @return int
	 */
	protected static function bo_for( $key, $settings ) {
		if ( 0 === strpos( $key, 'lb' ) ) {
			$key = 'lb';
		}
		if ( 'gf' === $key ) {
			$key = 'final';
		}

		$option = 'bo_' . $key;
		$bo     = isset( $settings[ $option ] ) ? (int) $settings[ $option ] : 1;

		return in_array( $bo, array( 1, 3, 5 ), true ) ? $bo : 1;
	}

	/* ---------------------------------------------------------------------
	 * Structure
	 * ------------------------------------------------------------------ */

	/**
	 * Définition complète des matchs d'un tournoi.
	 *
	 * @param array $settings Réglages du tournoi.
	 * @return array
	 */
	public static function structure( $settings = array() ) {
		$settings = array_merge( WGT_Tournament::defaults(), is_array( $settings ) ? $settings : array() );
		$format   = isset( $settings['format'] ) ? $settings['format'] : 'single';

		if ( 'groups' === $format ) {
			$matches = self::structure_groups( $settings );
		} elseif ( 'double' === $format ) {
			$matches = self::structure_double( $settings );
		} else {
			$matches = self::structure_single( $settings );
		}

		return self::schedule_matches( $matches, $settings );
	}

	/**
	 * Élimination directe.
	 *
	 * @param array $settings Réglages.
	 * @return array
	 */
	protected static function structure_single( $settings ) {
		$count = max( 2, (int) $settings['team_count'] );
		$size  = self::bracket_size( $count );

		$matches = self::knockout( $size, $count, self::seed_sources( $size, $count ), '', 'bracket', 'W' );

		if ( ! empty( $settings['third_place'] ) && $size >= 4 ) {
			$sf = self::round_codes( $matches, 'sf' );
			if ( 2 === count( $sf ) ) {
				$matches[] = array(
					'code'     => 'P3',
					'phase'    => 'consolation',
					'bracket'  => 'W',
					'group_id' => 0,
					'round'    => 'third',
					'round_no' => 99,
					'position' => 1,
					'bo'       => 1,
					'src1'     => 'L:' . $sf[0],
					'src2'     => 'L:' . $sf[1],
				);
			}
		}

		return self::apply_bo( $matches, $settings );
	}

	/**
	 * Sources du premier tour : têtes de série et exemptions.
	 *
	 * @param int $size  Taille du tableau.
	 * @param int $count Nombre d'équipes réel.
	 * @return array
	 */
	protected static function seed_sources( $size, $count ) {
		$out = array();
		foreach ( self::seed_order( $size ) as $seed ) {
			$out[] = $seed <= $count ? 'S' . $seed : 'BYE';
		}
		return $out;
	}

	/**
	 * Construit un tableau à élimination directe à partir d'une liste de
	 * sources ordonnées par position.
	 *
	 * @param int    $size    Taille du tableau.
	 * @param int    $count   Nombre d'équipes réel (pour les exemptions).
	 * @param array  $sources Sources, 2 par match du premier tour.
	 * @param string $prefix  Préfixe de code supplémentaire.
	 * @param string $phase   Phase.
	 * @param string $bracket Tableau (W ou L).
	 * @return array
	 */
	protected static function knockout( $size, $count, $sources, $prefix = '', $phase = 'bracket', $bracket = 'W' ) {
		$matches   = array();
		$remaining = $size;
		$round_no  = 0;
		$previous  = array();

		while ( $remaining >= 2 ) {
			$round_no++;
			$key      = self::round_key( $remaining );
			$is_first = 1 === $round_no;
			$code_pre = $prefix . self::code_prefix( $key, $is_first );
			$total    = (int) ( $remaining / 2 );
			$current  = array();

			for ( $i = 1; $i <= $total; $i++ ) {
				$code = ( 'final' === $key && 1 === $total ) ? $prefix . 'F' : $code_pre . $i;

				if ( $is_first ) {
					$src1 = isset( $sources[ ( $i - 1 ) * 2 ] ) ? $sources[ ( $i - 1 ) * 2 ] : 'BYE';
					$src2 = isset( $sources[ ( $i - 1 ) * 2 + 1 ] ) ? $sources[ ( $i - 1 ) * 2 + 1 ] : 'BYE';
				} else {
					$src1 = 'W:' . $previous[ ( $i - 1 ) * 2 ];
					$src2 = 'W:' . $previous[ ( $i - 1 ) * 2 + 1 ];
				}

				$matches[] = array(
					'code'     => $code,
					'phase'    => $phase,
					'bracket'  => $bracket,
					'group_id' => 0,
					'round'    => $key,
					'round_no' => $round_no,
					'position' => $i,
					'bo'       => 1,
					'src1'     => $src1,
					'src2'     => $src2,
				);

				$current[] = $code;
			}

			$previous  = $current;
			$remaining = (int) ( $remaining / 2 );
		}

		return $matches;
	}

	/**
	 * Codes des matchs d'un tour donné.
	 *
	 * @param array  $matches Matchs.
	 * @param string $round   Clé de tour.
	 * @return array
	 */
	protected static function round_codes( $matches, $round ) {
		$out = array();
		foreach ( $matches as $match ) {
			if ( $match['round'] === $round ) {
				$out[] = $match['code'];
			}
		}
		return $out;
	}

	/**
	 * Applique le nombre de manches configuré à chaque match.
	 *
	 * @param array $matches  Matchs.
	 * @param array $settings Réglages.
	 * @return array
	 */
	protected static function apply_bo( $matches, $settings ) {
		foreach ( $matches as $i => $match ) {
			if ( 'consolation' === $match['phase'] ) {
				$matches[ $i ]['bo'] = 1;
				continue;
			}
			$matches[ $i ]['bo'] = self::bo_for( $match['round'], $settings );
		}
		return $matches;
	}

	/* ---------------------------------------------------------------------
	 * Double élimination
	 * ------------------------------------------------------------------ */

	/**
	 * Tableau à double élimination.
	 *
	 * Le tableau principal est classique ; le repêchage alterne des tours
	 * « mineurs » (entre repêchés) et « majeurs » (repêchés contre les
	 * éliminés du tour suivant du tableau principal).
	 *
	 * @param array $settings Réglages.
	 * @return array
	 */
	protected static function structure_double( $settings ) {
		$count = max( 4, (int) $settings['team_count'] );
		$size  = self::bracket_size( $count );

		$winners = self::knockout( $size, $count, self::seed_sources( $size, $count ), '', 'bracket', 'W' );

		// Codes du tableau principal, par tour.
		$wb_rounds = array();
		foreach ( $winners as $match ) {
			$wb_rounds[ $match['round_no'] ][] = $match['code'];
		}
		$total_rounds = count( $wb_rounds );

		$losers   = array();
		$previous = array();
		$lb_round = 0;

		for ( $j = 1; $j < $total_rounds; $j++ ) {
			// Tour mineur.
			$lb_round++;
			$current = array();

			if ( 1 === $j ) {
				$feed  = $wb_rounds[1];
				$total = (int) ( count( $feed ) / 2 );
				for ( $i = 1; $i <= $total; $i++ ) {
					$code      = 'L' . $lb_round . '-' . $i;
					$losers[]  = self::lb_match( $code, $lb_round, $i, 'L:' . $feed[ ( $i - 1 ) * 2 ], 'L:' . $feed[ ( $i - 1 ) * 2 + 1 ] );
					$current[] = $code;
				}
			} else {
				$total = (int) ( count( $previous ) / 2 );
				for ( $i = 1; $i <= $total; $i++ ) {
					$code      = 'L' . $lb_round . '-' . $i;
					$losers[]  = self::lb_match( $code, $lb_round, $i, 'W:' . $previous[ ( $i - 1 ) * 2 ], 'W:' . $previous[ ( $i - 1 ) * 2 + 1 ] );
					$current[] = $code;
				}
			}

			$previous = $current;

			// Tour majeur : les repêchés affrontent les éliminés du tableau
			// principal, dans l'ordre inverse pour éviter une revanche
			// immédiate.
			$lb_round++;
			$feed    = array_reverse( $wb_rounds[ $j + 1 ] );
			$current = array();
			$total   = count( $previous );

			for ( $i = 1; $i <= $total; $i++ ) {
				$code      = 'L' . $lb_round . '-' . $i;
				$opponent  = isset( $feed[ $i - 1 ] ) ? 'L:' . $feed[ $i - 1 ] : 'BYE';
				$losers[]  = self::lb_match( $code, $lb_round, $i, 'W:' . $previous[ $i - 1 ], $opponent );
				$current[] = $code;
			}

			$previous = $current;
		}

		// Grande finale.
		$wb_final = end( $wb_rounds[ $total_rounds ] );
		$lb_final = end( $previous );

		$grand = array(
			'code'     => 'GF',
			'phase'    => 'final',
			'bracket'  => 'W',
			'group_id' => 0,
			'round'    => 'gf',
			'round_no' => 98,
			'position' => 1,
			'bo'       => 1,
			'src1'     => 'W:' . $wb_final,
			'src2'     => 'W:' . $lb_final,
		);

		$matches = array_merge( $winners, $losers, array( $grand ) );

		/*
		 * « Bracket reset » : l'équipe venue du repêchage a déjà une défaite.
		 * Si elle remporte la grande finale, une seconde est jouée pour
		 * départager à égalité de défaites.
		 */
		if ( ! empty( $settings['bracket_reset'] ) ) {
			$matches[] = array(
				'code'     => 'GF2',
				'phase'    => 'final',
				'bracket'  => 'W',
				'group_id' => 0,
				'round'    => 'gf',
				'round_no' => 98,
				'position' => 2,
				'bo'       => 1,
				'src1'     => 'W:GF',
				'src2'     => 'L:GF',
			);
		}

		return self::apply_bo( $matches, $settings );
	}

	/**
	 * Fabrique un match de repêchage.
	 *
	 * @param string $code     Code.
	 * @param int    $round    Numéro de tour de repêchage.
	 * @param int    $position Position.
	 * @param string $src1     Source 1.
	 * @param string $src2     Source 2.
	 * @return array
	 */
	protected static function lb_match( $code, $round, $position, $src1, $src2 ) {
		return array(
			'code'     => $code,
			'phase'    => 'losers',
			'bracket'  => 'L',
			'group_id' => 0,
			'round'    => 'lb' . $round,
			'round_no' => 50 + $round,
			'position' => $position,
			'bo'       => 1,
			'src1'     => $src1,
			'src2'     => $src2,
		);
	}

	/* ---------------------------------------------------------------------
	 * Poules
	 * ------------------------------------------------------------------ */

	/**
	 * Répartition des têtes de série en poules, en serpentin.
	 *
	 * @param int $count  Nombre d'équipes.
	 * @param int $groups Nombre de poules.
	 * @return array Poule => liste de numéros de série.
	 */
	public static function group_seeds( $count, $groups ) {
		$out = array();
		for ( $g = 1; $g <= $groups; $g++ ) {
			$out[ $g ] = array();
		}

		$seed = 1;
		$row  = 0;
		while ( $seed <= $count ) {
			$order = range( 1, $groups );
			if ( $row % 2 ) {
				$order = array_reverse( $order );
			}
			foreach ( $order as $g ) {
				if ( $seed > $count ) {
					break;
				}
				$out[ $g ][] = $seed;
				$seed++;
			}
			$row++;
		}

		return $out;
	}

	/**
	 * Phase de poules puis phase finale.
	 *
	 * @param array $settings Réglages.
	 * @return array
	 */
	protected static function structure_groups( $settings ) {
		$layout     = self::group_layout( $settings );
		$groups     = $layout['groups'];
		$qualifiers = $layout['qualifiers'];
		$sizes      = $layout['sizes'];

		$matches = array();
		$bo      = self::bo_for( 'group', $settings );

		for ( $g = 1; $g <= $groups; $g++ ) {
			// Chaque poule joue ses propres matchs, selon son effectif réel :
			// une poule plus petite n'a pas d'emplacement vide.
			$list = array();
			$size = isset( $sizes[ $g ] ) ? (int) $sizes[ $g ] : 0;
			for ( $k = 1; $k <= $size; $k++ ) {
				$list[] = $k;
			}
			$n = count( $list );
			$i = 0;
			for ( $a = 0; $a < $n; $a++ ) {
				for ( $b = $a + 1; $b < $n; $b++ ) {
					$i++;
					$matches[] = array(
						'code'     => 'G' . $g . 'M' . $i,
						'phase'    => 'group',
						'bracket'  => 'G',
						'group_id' => $g,
						'round'    => 'group',
						'round_no' => 1,
						'position' => ( $g - 1 ) * 100 + $i,
						'bo'       => $bo,
						'src1'     => 'GS:' . $g . ':' . $list[ $a ],
						'src2'     => 'GS:' . $g . ':' . $list[ $b ],
					);
				}
			}
		}

		// Phase finale : qualifiés croisés entre poules.
		$slots = self::qualifier_slots( $groups, $qualifiers );
		$size  = self::bracket_size( count( $slots ) );

		$sources = array();
		foreach ( self::seed_order( $size ) as $seed ) {
			$sources[] = isset( $slots[ $seed - 1 ] ) ? $slots[ $seed - 1 ] : 'BYE';
		}

		$sources = self::avoid_same_group( $sources );

		$knockout = self::knockout( $size, count( $slots ), $sources, '', 'bracket', 'W' );

		// Les tours de la phase finale démarrent après les poules.
		foreach ( $knockout as $i => $match ) {
			$knockout[ $i ]['round_no'] = $match['round_no'] + 1;
		}

		$matches = array_merge( $matches, $knockout );

		if ( ! empty( $settings['third_place'] ) ) {
			$sf = self::round_codes( $knockout, 'sf' );
			if ( 2 === count( $sf ) ) {
				$matches[] = array(
					'code'     => 'P3',
					'phase'    => 'consolation',
					'bracket'  => 'W',
					'group_id' => 0,
					'round'    => 'third',
					'round_no' => 99,
					'position' => 1,
					'bo'       => 1,
					'src1'     => 'L:' . $sf[0],
					'src2'     => 'L:' . $sf[1],
				);
			}
		}

		return self::apply_bo( $matches, $settings );
	}

	/**
	 * Paramètres effectifs de la phase de poules.
	 *
	 * Le nombre de poules et de qualifiés est borné par l'effectif, et la
	 * taille de chaque poule est connue : en mode automatique elle découle du
	 * serpentin, en mode manuel des poules attribuées aux équipes (transmises
	 * par la couche de données dans « group_sizes »).
	 *
	 * @param array $settings Réglages (avec, éventuellement, « group_sizes »).
	 * @return array { count, groups, qualifiers, sizes (poule => effectif) }.
	 */
	public static function group_layout( $settings ) {
		$count      = max( 4, (int) $settings['team_count'] );
		$groups     = max( 2, (int) $settings['group_count'] );
		$qualifiers = max( 1, (int) $settings['qualifiers_per_group'] );

		$groups = min( $groups, (int) floor( $count / 2 ) );

		$sizes = array();
		if ( isset( $settings['group_sizes'] ) && is_array( $settings['group_sizes'] ) ) {
			for ( $g = 1; $g <= $groups; $g++ ) {
				$sizes[ $g ] = isset( $settings['group_sizes'][ $g ] ) ? max( 0, (int) $settings['group_sizes'][ $g ] ) : 0;
			}
		} else {
			foreach ( self::group_seeds( $count, $groups ) as $g => $seeds ) {
				$sizes[ $g ] = count( $seeds );
			}
		}

		// On ne peut pas qualifier plus d'équipes qu'en compte la plus petite
		// poule pourvue.
		$smallest = 0;
		foreach ( $sizes as $size ) {
			if ( $size > 0 && ( 0 === $smallest || $size < $smallest ) ) {
				$smallest = $size;
			}
		}
		if ( $smallest > 0 ) {
			$qualifiers = min( $qualifiers, $smallest );
		}

		return array(
			'count'      => $count,
			'groups'     => $groups,
			'qualifiers' => $qualifiers,
			'sizes'      => $sizes,
		);
	}

	/**
	 * Ordre des qualifiés, croisé pour qu'une même poule ne se retrouve pas
	 * face à elle-même dès le premier tour.
	 *
	 * Les premiers de poule occupent les meilleures places du tableau. Pour
	 * chaque rang suivant, on cherche la rotation des poules qui, une fois
	 * les places distribuées par l'ordre classique des têtes de série, ne
	 * provoque aucune rencontre entre équipes d'une même poule. La rotation
	 * « moitié » est essayée en premier : elle éloigne le plus longtemps les
	 * équipes d'une même poule.
	 *
	 * @param int $groups     Nombre de poules.
	 * @param int $qualifiers Qualifiés par poule.
	 * @return array Liste de sources « G:<poule>:<rang> ».
	 */
	protected static function qualifier_slots( $groups, $qualifiers ) {
		$groups     = max( 1, (int) $groups );
		$qualifiers = max( 1, (int) $qualifiers );
		$size       = self::bracket_size( $groups * $qualifiers );
		$order      = self::seed_order( $size );

		$preferred = array( (int) floor( $groups / 2 ) );
		for ( $s = 0; $s < $groups; $s++ ) {
			if ( ! in_array( $s, $preferred, true ) ) {
				$preferred[] = $s;
			}
		}

		$shifts = array( 1 => 0 );

		for ( $rank = 2; $rank <= $qualifiers; $rank++ ) {
			$chosen = null;
			foreach ( $preferred as $shift ) {
				$shifts[ $rank ] = $shift;
				$slots           = self::slots_from_shifts( $groups, $rank, $shifts );
				if ( 0 === self::first_round_conflicts( $order, $slots ) ) {
					$chosen = $shift;
					break;
				}
			}
			$shifts[ $rank ] = null === $chosen ? $preferred[0] : $chosen;
		}

		return self::slots_from_shifts( $groups, $qualifiers, $shifts );
	}

	/**
	 * Construit la liste des qualifiés d'après une rotation par rang.
	 *
	 * @param int   $groups     Nombre de poules.
	 * @param int   $qualifiers Rangs à inclure.
	 * @param array $shifts     Rang => rotation.
	 * @return array
	 */
	protected static function slots_from_shifts( $groups, $qualifiers, $shifts ) {
		$slots = array();
		for ( $rank = 1; $rank <= $qualifiers; $rank++ ) {
			$shift = isset( $shifts[ $rank ] ) ? (int) $shifts[ $rank ] : 0;
			for ( $i = 0; $i < $groups; $i++ ) {
				$g       = ( ( $i + $shift ) % $groups ) + 1;
				$slots[] = 'G:' . $g . ':' . $rank;
			}
		}
		return $slots;
	}

	/**
	 * Nombre de rencontres du premier tour opposant deux équipes d'une même
	 * poule, pour une liste de qualifiés placée selon un ordre de série.
	 *
	 * @param array $order Ordre des têtes de série (positions du tableau).
	 * @param array $slots Qualifiés, indexés par numéro de série - 1.
	 * @return int
	 */
	protected static function first_round_conflicts( $order, $slots ) {
		$conflicts = 0;
		$total     = count( $order );

		for ( $k = 0; $k + 1 < $total; $k += 2 ) {
			$a  = isset( $slots[ $order[ $k ] - 1 ] ) ? $slots[ $order[ $k ] - 1 ] : 'BYE';
			$b  = isset( $slots[ $order[ $k + 1 ] - 1 ] ) ? $slots[ $order[ $k + 1 ] - 1 ] : 'BYE';
			$g1 = self::source_group( $a );
			$g2 = self::source_group( $b );
			if ( $g1 && $g1 === $g2 ) {
				$conflicts++;
			}
		}

		return $conflicts;
	}

	/**
	 * Poule d'où provient une source « G:<poule>:<rang> ».
	 *
	 * @param string $src Source.
	 * @return int 0 si la source ne vient pas d'une poule.
	 */
	protected static function source_group( $src ) {
		return preg_match( '/^G:(\d+):/', (string) $src, $m ) ? (int) $m[1] : 0;
	}

	/**
	 * Évite qu'un premier tour de phase finale oppose deux équipes issues de
	 * la même poule.
	 *
	 * L'ordre de départ croise déjà les poules ; ce filet de sécurité corrige
	 * les rencontres fautives restantes par échange, en conservant les
	 * emplacements du tableau. Les exemptions ne sont jamais déplacées : elles
	 * reviennent aux mieux classés.
	 *
	 * @param array $sources Sources du premier tour, deux par match.
	 * @return array
	 */
	protected static function avoid_same_group( $sources ) {
		$total = count( $sources );

		for ( $k = 0; $k + 1 < $total; $k += 2 ) {
			$g1 = self::source_group( $sources[ $k ] );
			$g2 = self::source_group( $sources[ $k + 1 ] );

			if ( ! $g1 || $g1 !== $g2 ) {
				continue;
			}

			// On cherche un emplacement avec lequel échanger sans créer de
			// nouveau conflit.
			for ( $j = 0; $j < $total; $j++ ) {
				if ( $j === $k || $j === $k + 1 ) {
					continue;
				}

				$partner = ( 0 === $j % 2 ) ? $j + 1 : $j - 1;
				if ( ! isset( $sources[ $partner ] ) ) {
					continue;
				}

				$candidate  = $sources[ $j ];
				$g_candidate = self::source_group( $candidate );
				$g_partner   = self::source_group( $sources[ $partner ] );

				// Ni l'emplacement ni son adversaire ne doivent être exempts.
				if ( ! $g_candidate || ! $g_partner ) {
					continue;
				}

				// Après échange : k+1 reçoit $candidate, $j reçoit l'ancien.
				if ( $g_candidate === $g1 ) {
					continue;
				}
				if ( $g_partner === $g2 ) {
					continue;
				}

				$tmp                  = $sources[ $k + 1 ];
				$sources[ $k + 1 ]    = $candidate;
				$sources[ $j ]        = $tmp;
				break;
			}
		}

		return $sources;
	}

	/* ---------------------------------------------------------------------
	 * Dépendances entre matchs
	 * ------------------------------------------------------------------ */

	/**
	 * Une source est-elle structurellement vide ?
	 *
	 * Une exemption est vide. Le perdant d'un match dont l'une des sources
	 * est vide n'existe pas (l'équipe présente passe sans jouer). Le
	 * vainqueur d'un match dont les deux sources sont vides n'existe pas non
	 * plus : ce match est sans objet.
	 *
	 * @param string $src     Source.
	 * @param array  $by_code Matchs indexés par code (avec src1/src2).
	 * @param int    $depth   Garde-fou contre les références circulaires.
	 * @return bool
	 */
	public static function source_is_empty( $src, $by_code, $depth = 0 ) {
		$src = (string) $src;

		if ( '' === $src || 'BYE' === $src ) {
			return true;
		}
		if ( $depth > 32 ) {
			return false;
		}

		$type = substr( $src, 0, 2 );
		if ( 'W:' !== $type && 'L:' !== $type ) {
			return false;
		}

		$code = substr( $src, 2 );
		if ( ! isset( $by_code[ $code ] ) ) {
			return true;
		}

		$e1 = self::source_is_empty( $by_code[ $code ]['src1'], $by_code, $depth + 1 );
		$e2 = self::source_is_empty( $by_code[ $code ]['src2'], $by_code, $depth + 1 );

		return 'W:' === $type ? ( $e1 && $e2 ) : ( $e1 || $e2 );
	}

	/**
	 * Un match sera-t-il réellement joué ?
	 *
	 * Faux pour les exemptions (une seule équipe) et les matchs sans objet.
	 *
	 * @param array $match   Match (avec src1/src2).
	 * @param array $by_code Matchs indexés par code.
	 * @return bool
	 */
	public static function is_playable( $match, $by_code ) {
		return ! self::source_is_empty( $match['src1'], $by_code ) && ! self::source_is_empty( $match['src2'], $by_code );
	}

	/**
	 * Étape de chaque match : 1 pour un match sans dépendance, sinon 1 de
	 * plus que la dernière étape dont il dépend. Sert à ordonner le calcul
	 * du tableau et le planning quel que soit le format (le repêchage se
	 * joue en parallèle du tableau principal, la seconde grande finale
	 * après la première, la petite finale après les demi-finales).
	 *
	 * @param array $by_code Matchs indexés par code (avec src1/src2).
	 * @return array Code => étape.
	 */
	public static function stages( $by_code ) {
		$stages = array();

		// Les matchs de poule d'une poule donnée sont tous à l'étape 1 ; un
		// qualifié « G:<poule>:<rang> » dépend de tous.
		$group_stage = array();
		foreach ( $by_code as $code => $match ) {
			if ( isset( $match['phase'] ) && 'group' === $match['phase'] ) {
				$stages[ $code ]                          = 1;
				$group_stage[ (int) $match['group_id'] ] = 1;
			}
		}

		$pending = array_keys( $by_code );
		$guard   = 0;

		while ( $pending && $guard < 256 ) {
			$guard++;
			$next = array();

			foreach ( $pending as $code ) {
				if ( isset( $stages[ $code ] ) ) {
					continue;
				}

				$deps  = 0;
				$ready = true;

				foreach ( array( $by_code[ $code ]['src1'], $by_code[ $code ]['src2'] ) as $src ) {
					$src  = (string) $src;
					$type = substr( $src, 0, 2 );

					if ( 'W:' === $type || 'L:' === $type ) {
						$ref = substr( $src, 2 );
						if ( ! isset( $by_code[ $ref ] ) ) {
							continue;
						}
						if ( ! isset( $stages[ $ref ] ) ) {
							$ready = false;
							break;
						}
						$deps = max( $deps, $stages[ $ref ] );
					} elseif ( 'G:' === $type ) {
						$parts = explode( ':', $src );
						$g     = isset( $parts[1] ) ? (int) $parts[1] : 0;
						$deps  = max( $deps, isset( $group_stage[ $g ] ) ? $group_stage[ $g ] : 1 );
					}
				}

				if ( $ready ) {
					$stages[ $code ] = $deps + 1;
				} else {
					$next[] = $code;
				}
			}

			if ( count( $next ) === count( $pending ) ) {
				// Référence circulaire ou inconnue : on classe le reste par
				// numéro de tour pour ne jamais boucler.
				foreach ( $next as $code ) {
					$stages[ $code ] = 1000 + (int) $by_code[ $code ]['round_no'];
				}
				break;
			}

			$pending = $next;
		}

		return $stages;
	}

	/**
	 * Ordre de traitement des matchs : par étape, puis petite finale avant
	 * la finale, puis tableau principal avant repêchage, puis tour et
	 * position.
	 *
	 * @param array $by_code Matchs indexés par code.
	 * @return array Codes ordonnés.
	 */
	public static function processing_order( $by_code ) {
		$stages = self::stages( $by_code );
		$keys   = array();

		foreach ( $by_code as $code => $match ) {
			$phase   = isset( $match['phase'] ) ? $match['phase'] : '';
			$bracket = isset( $match['bracket'] ) ? $match['bracket'] : 'W';
			$keys[]  = array(
				'code'  => $code,
				'stage' => isset( $stages[ $code ] ) ? $stages[ $code ] : 9999,
				'sub'   => 'consolation' === $phase ? 0 : ( 'L' === $bracket ? 2 : 1 ),
				'round' => (int) $match['round_no'],
				'pos'   => (int) $match['position'],
			);
		}

		usort(
			$keys,
			function ( $a, $b ) {
				foreach ( array( 'stage', 'sub', 'round', 'pos' ) as $k ) {
					if ( $a[ $k ] !== $b[ $k ] ) {
						return $a[ $k ] < $b[ $k ] ? -1 : 1;
					}
				}
				return strcmp( $a['code'], $b['code'] );
			}
		);

		$out = array();
		foreach ( $keys as $key ) {
			$out[] = $key['code'];
		}

		return $out;
	}

	/* ---------------------------------------------------------------------
	 * Horaires
	 * ------------------------------------------------------------------ */

	/**
	 * Attribue horaires et postes aux matchs.
	 *
	 * Les matchs sont planifiés par étape de dépendance : le repêchage
	 * s'intercale entre les tours du tableau principal, la petite finale
	 * précède la finale, la seconde grande finale suit la première. Les
	 * exemptions n'occupent ni créneau ni poste.
	 *
	 * @param array $matches  Matchs.
	 * @param array $settings Réglages.
	 * @return array
	 */
	protected static function schedule_matches( $matches, $settings ) {
		$parallel = max( 1, (int) $settings['matches_parallel'] );
		$duration = max( 5, (int) $settings['match_duration'] );
		$stations = max( 1, (int) $settings['stations'] );
		$warmup   = max( 0, (int) $settings['warmup_minutes'] );
		$pause    = max( 0, (int) $settings['break_minutes'] );

		$clock = self::to_minutes( $settings['start_time'] ) + $warmup;

		$by_code = array();
		foreach ( $matches as $i => $match ) {
			$by_code[ $match['code'] ] = $match;
		}
		$index_of = array();
		foreach ( $matches as $i => $match ) {
			$index_of[ $match['code'] ] = $i;
		}

		$stages = self::stages( $by_code );

		// Regroupement par étape, dans l'ordre de déroulement.
		$by_round = array();
		foreach ( self::processing_order( $by_code ) as $code ) {
			$i = $index_of[ $code ];

			$matches[ $i ]['start']    = '';
			$matches[ $i ]['end']      = '';
			$matches[ $i ]['stations'] = '';

			if ( ! self::is_playable( $matches[ $i ], $by_code ) ) {
				continue;
			}

			$stage              = isset( $stages[ $code ] ) ? $stages[ $code ] : 9999;
			$by_round[ $stage ][] = $i;
		}
		ksort( $by_round );

		$first_round = true;

		foreach ( $by_round as $indexes ) {
			if ( ! $first_round && $pause ) {
				$clock += $pause;
			}
			$first_round = false;

			$wave = 0;
			foreach ( array_chunk( $indexes, $parallel ) as $chunk ) {
				$longest = 0;

				foreach ( $chunk as $slot => $index ) {
					$bo   = (int) $matches[ $index ]['bo'];
					$need = (int) floor( $bo / 2 ) + 1;
					$len  = $duration * $need;

					$matches[ $index ]['start']    = self::to_time( $clock );
					$matches[ $index ]['end']      = self::to_time( $clock + $len );
					$matches[ $index ]['stations'] = self::station_label( $slot, $parallel, $stations );

					$longest = max( $longest, $len );
				}

				$clock += $longest;
				$wave++;
			}
		}

		return $matches;
	}

	/**
	 * Libellé des postes attribués à un match d'une vague.
	 *
	 * @param int $slot     Index du match dans la vague.
	 * @param int $parallel Matchs simultanés.
	 * @param int $stations Postes disponibles.
	 * @return string
	 */
	protected static function station_label( $slot, $parallel, $stations ) {
		if ( $parallel <= 1 || $stations < $parallel ) {
			return '1-' . $stations;
		}

		$from = (int) floor( $slot * $stations / $parallel ) + 1;
		$to   = (int) floor( ( $slot + 1 ) * $stations / $parallel );

		return $from === $to ? (string) $from : $from . '-' . $to;
	}

	/**
	 * « HH:MM » vers minutes.
	 *
	 * @param string $time Horaire.
	 * @return int
	 */
	protected static function to_minutes( $time ) {
		if ( ! preg_match( '/^(\d{1,2}):(\d{2})$/', (string) $time, $m ) ) {
			return 19 * 60;
		}
		return (int) $m[1] * 60 + (int) $m[2];
	}

	/**
	 * Minutes vers « HH:MM ».
	 *
	 * @param int $minutes Minutes.
	 * @return string
	 */
	protected static function to_time( $minutes ) {
		$total = ( ( (int) $minutes % 1440 ) + 1440 ) % 1440;
		return sprintf( '%02d:%02d', (int) floor( $total / 60 ), $total % 60 );
	}

	/* ---------------------------------------------------------------------
	 * Description des tours, pour l'affichage
	 * ------------------------------------------------------------------ */

	/**
	 * Tours présents dans la structure, dans l'ordre, avec leurs libellés.
	 *
	 * @param array $settings Réglages.
	 * @return array Clé de tour => libellé court.
	 */
	public static function round_order( $settings = array() ) {
		$out = array();

		foreach ( self::structure( $settings ) as $match ) {
			if ( ! isset( $out[ $match['round'] ] ) ) {
				$out[ $match['round'] ] = $match['round_no'];
			}
		}

		asort( $out );

		$labels = array();
		foreach ( array_keys( $out ) as $key ) {
			$labels[ $key ] = self::round_short( $key );
		}

		return $labels;
	}

	/* ---------------------------------------------------------------------
	 * Contenus fixes
	 * ------------------------------------------------------------------ */

	/**
	 * Personnel nécessaire.
	 *
	 * @return array
	 */
	public static function staff() {
		return array(
			array( __( 'Tournament manager', 'wegame-tournoi' ), 1, __( 'Supervision, rules, decisions and coordination', 'wegame-tournoi' ) ),
			array( __( 'Front desk / management', 'wegame-tournoi' ), 1, __( 'Sign-ups, results, bracket and times', 'wegame-tournoi' ) ),
			array( __( 'Referees', 'wegame-tournoi' ), 2, __( 'Match follow-up, score approval, disputes', 'wegame-tournoi' ) ),
			array( __( 'Technicians', 'wegame-tournoi' ), 2, __( 'Stations, network, accounts, peripherals, incidents', 'wegame-tournoi' ) ),
			array( __( 'Host / communication', 'wegame-tournoi' ), 1, __( 'Announcements, atmosphere, hosting, prize ceremony', 'wegame-tournoi' ) ),
		);
	}

	/**
	 * Règlement de base.
	 *
	 * @param array $settings Réglages du tournoi (nombre de joueurs par équipe).
	 * @return array
	 */
	public static function rules( $settings = array() ) {
		$players = isset( $settings['players_per_team'] ) ? (int) $settings['players_per_team'] : 0;
		if ( $players < 1 ) {
			$players = 4;
		}

		return array(
			sprintf(
				/* translators: %d: nombre de joueurs titulaires */
				_n(
					'Each team is made up of %d starting player; a substitute is recommended.',
					'Each team is made up of %d starting players; a substitute is recommended.',
					$players,
					'wegame-tournoi'
				),
				$players
			),
			__( 'The captain is the team\'s official contact.', 'wegame-tournoi' ),
			__( 'Players must follow the instructions of the referees and the organizers.', 'wegame-tournoi' ),
			__( 'Any cheat, deliberate exploit or unsportsmanlike behavior may lead to a penalty or a disqualification.', 'wegame-tournoi' ),
			__( 'Technical problems must be reported to the referee immediately.', 'wegame-tournoi' ),
			__( 'Results are approved by the referee before moving on to the next round.', 'wegame-tournoi' ),
			__( 'The organizers may adjust the schedule in the event of a technical problem or a significant delay.', 'wegame-tournoi' ),
		);
	}

	/**
	 * Checklist avant ouverture.
	 *
	 * @return array
	 */
	public static function checklist() {
		return array(
			__( 'Check the stations and peripherals', 'wegame-tournoi' ),
			__( 'Test the network and the connection to game services', 'wegame-tournoi' ),
			__( 'Check the accounts / profiles used', 'wegame-tournoi' ),
			__( 'Prepare the tournament bracket and the score sheets', 'wegame-tournoi' ),
			__( 'Identify the team captains', 'wegame-tournoi' ),
			__( 'Brief the players and referees', 'wegame-tournoi' ),
			__( 'Prepare prizes / trophy / rewards', 'wegame-tournoi' ),
			__( 'Plan for water, breaks and a waiting area', 'wegame-tournoi' ),
		);
	}
}
