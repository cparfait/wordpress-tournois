<?php
/**
 * Shortcodes publics.
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Shortcodes {

	/**
	 * Correspondance shortcode => vue.
	 *
	 * @return array
	 */
	public static function map() {
		return array(
			'wegame_tournoi'      => 'full',
			'wegame_tableau'      => 'bracket',
			'wegame_planning'     => 'planning',
			'wegame_equipes'      => 'teams',
			'wegame_resultats'    => 'results',
			'wegame_reglement'    => 'rules',
			'wegame_organisation' => 'staff',
			'wegame_checklist'    => 'checklist',
			'wegame_inscription'  => 'registration',
			'wegame_tournois'     => 'list',
			'wegame_poules'       => 'groups',
			'wegame_classement'   => 'ranking',
		);
	}

	/**
	 * Enregistrement des shortcodes.
	 */
	public static function init() {
		foreach ( array_keys( self::map() ) as $tag ) {
			add_shortcode( $tag, array( __CLASS__, 'render' ) );
		}
	}

	/**
	 * Rendu d'un shortcode.
	 *
	 * @param array  $atts    Attributs.
	 * @param string $content Contenu.
	 * @param string $tag     Shortcode appelé.
	 * @return string
	 */
	public static function render( $atts, $content = '', $tag = '' ) {
		$map = self::map();
		if ( ! isset( $map[ $tag ] ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'header'     => 'yes',
				'players'    => 'no',
				'fit'        => 'screen',
				// Formulaire d'inscription réduit (Team, Pseudo, Mail, Téléphone).
				'simple'     => 'no',
				// Slug ou identifiant du tournoi ; vide = tournoi courant.
				'tournoi'    => '',
				'tournament' => '',
			),
			is_array( $atts ) ? $atts : array(),
			$tag
		);

		// Normalisation des options d'affichage (liste blanche).
		$atts['header']  = 'no' === strtolower( (string) $atts['header'] ) ? 'no' : 'yes';
		$atts['players'] = 'yes' === strtolower( (string) $atts['players'] ) ? 'yes' : 'no';
		$atts['fit']     = 'width' === strtolower( (string) $atts['fit'] ) ? 'width' : 'screen';

		$ref = '' !== $atts['tournoi'] ? $atts['tournoi'] : $atts['tournament'];
		$tid = '' !== $ref ? WGT_Tournament::resolve( $ref ) : 0;

		if ( '' !== $ref && ! $tid ) {
			return '<p class="wgt-empty">' . esc_html__( 'Tournoi introuvable.', 'wegame-tournoi' ) . '</p>';
		}

		// Sur la page d'un tournoi, c'est ce tournoi qui prime.
		if ( ! $tid && is_singular( WGT_Tournament::POST_TYPE ) ) {
			$tid = (int) get_the_ID();
		}

		$atts['tournament_id'] = $tid;

		wp_enqueue_style( 'wgt-public' );
		wp_enqueue_script( 'wgt-public' );

		return WGT_Render::view( $map[ $tag ], $atts );
	}
}
