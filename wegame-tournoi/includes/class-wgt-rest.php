<?php
/**
 * Routes REST : rafraîchissement du tableau côté public.
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Rest {

	/**
	 * Enregistrement des routes.
	 */
	public static function init() {
		add_action( 'rest_api_init', array( __CLASS__, 'register_routes' ) );
	}

	/**
	 * Routes.
	 */
	public static function register_routes() {
		register_rest_route(
			'wegame/v1',
			'/render',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'render' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'view'       => array(
						'required'          => true,
						'sanitize_callback' => 'sanitize_key',
					),
					'tournament' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
					// Options d'affichage renvoyées par le script public, pour
					// que le HTML rafraîchi soit identique à celui du code court.
					'header'     => array(
						'required'          => false,
						'default'           => 'yes',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( __CLASS__, 'validate_header' ),
					),
					'fit'        => array(
						'required'          => false,
						'default'           => 'screen',
						'sanitize_callback' => 'sanitize_key',
						'validate_callback' => array( __CLASS__, 'validate_fit' ),
					),
				),
			)
		);

		register_rest_route(
			'wegame/v1',
			'/state',
			array(
				'methods'             => 'GET',
				'callback'            => array( __CLASS__, 'state' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'tournament' => array(
						'required'          => false,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);
	}

	/**
	 * Validation du paramètre « header » (yes|no).
	 *
	 * @param mixed $value Valeur reçue.
	 * @return bool
	 */
	public static function validate_header( $value ) {
		return in_array( $value, array( 'yes', 'no' ), true );
	}

	/**
	 * Validation du paramètre « fit » (screen|width).
	 *
	 * @param mixed $value Valeur reçue.
	 * @return bool
	 */
	public static function validate_fit( $value ) {
		return in_array( $value, array( 'screen', 'width' ), true );
	}

	/**
	 * Renvoie le HTML d'une vue.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function render( $request ) {
		$view = $request->get_param( 'view' );

		if ( ! in_array( $view, WGT_Render::views(), true ) || 'registration' === $view ) {
			return new WP_Error( 'wgt_bad_view', __( 'Vue inconnue.', 'wegame-tournoi' ), array( 'status' => 400 ) );
		}

		$ref = (string) $request->get_param( 'tournament' );
		$tid = '' !== $ref ? WGT_Tournament::resolve( $ref ) : WGT_Tournament::current_public();

		if ( ! $tid && 'list' !== $view ) {
			return new WP_Error( 'wgt_no_tournament', __( 'Tournoi introuvable.', 'wegame-tournoi' ), array( 'status' => 404 ) );
		}

		// Liste blanche stricte, même si la validation REST est déjà passée.
		$header = 'no' === $request->get_param( 'header' ) ? 'no' : 'yes';
		$fit    = 'width' === $request->get_param( 'fit' ) ? 'width' : 'screen';

		return rest_ensure_response(
			array(
				'view'       => $view,
				'tournament' => $tid,
				'html'       => WGT_Render::view(
					$view,
					array(
						'header'        => $header,
						'fit'           => $fit,
						'tournament_id' => $tid,
					)
				),
				'timestamp'  => current_time( 'timestamp' ), // phpcs:ignore WordPress.DateTime.CurrentTimeTimestamp
			)
		);
	}

	/**
	 * État brut du tournoi (JSON), pour affichage externe ou régie.
	 *
	 * @param WP_REST_Request $request Requête.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function state( $request ) {
		$ref = (string) $request->get_param( 'tournament' );
		$tid = '' !== $ref ? WGT_Tournament::resolve( $ref ) : WGT_Tournament::current_public();

		if ( ! $tid ) {
			return new WP_Error( 'wgt_no_tournament', __( 'Tournoi introuvable.', 'wegame-tournoi' ), array( 'status' => 404 ) );
		}

		$teams   = WGT_Data::get_teams_map( $tid );
		$out     = array();
		$matches = WGT_Data::get_matches( $tid );

		foreach ( $matches as $match ) {
			$out[] = array(
				'code'     => $match['code'],
				'round'    => $match['round'],
				'bo'       => (int) $match['bo'],
				'stations' => $match['stations'],
				'start'    => $match['start_time'],
				'status'   => $match['status'],
				'team1'    => (int) $match['team1_id'] ? WGT_Data::team_name( (int) $match['team1_id'], $teams ) : WGT_Data::source_label( $match['src1'] ),
				'team2'    => (int) $match['team2_id'] ? WGT_Data::team_name( (int) $match['team2_id'], $teams ) : WGT_Data::source_label( $match['src2'] ),
				'score1'   => (int) $match['score1'],
				'score2'   => (int) $match['score2'],
				'winner'   => (int) $match['winner_id'] ? WGT_Data::team_name( (int) $match['winner_id'], $teams ) : '',
			);
		}

		$stats = WGT_Data::get_stats( $tid );

		return rest_ensure_response(
			array(
				'id'         => $tid,
				'tournament' => get_the_title( $tid ),
				'game'       => WGT_Tournament::get( $tid, 'game_name' ),
				'champion'   => $stats['champion_id'] ? WGT_Data::team_name( $stats['champion_id'], $teams ) : '',
				'third'      => $stats['third_id'] ? WGT_Data::team_name( $stats['third_id'], $teams ) : '',
				'stats'      => $stats,
				'matches'    => $out,
			)
		);
	}
}
