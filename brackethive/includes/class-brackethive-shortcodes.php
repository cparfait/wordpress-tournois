<?php
/**
 * Shortcodes publics.
 *
 * @package Brackethive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Brackethive_Shortcodes {

	/**
	 * Correspondance shortcode => vue.
	 *
	 * @return array
	 */
	public static function map() {
		return array(
			'brackethive_tournoi'      => 'full',
			'brackethive_tableau'      => 'bracket',
			'brackethive_planning'     => 'planning',
			'brackethive_equipes'      => 'teams',
			'brackethive_resultats'    => 'results',
			'brackethive_reglement'    => 'rules',
			'brackethive_organisation' => 'staff',
			'brackethive_checklist'    => 'checklist',
			'brackethive_inscription'  => 'registration',
			'brackethive_tournois'     => 'list',
			'brackethive_poules'       => 'groups',
			'brackethive_classement'   => 'ranking',
		);
	}

	/**
	 * Shortcodes de l'ancien nom de l'extension, conservés pour que les pages
	 * déjà publiées continuent de s'afficher après la mise à jour.
	 *
	 * @return array Ancien shortcode => vue.
	 */
	public static function legacy_map() {
		$legacy = array();
		foreach ( self::map() as $tag => $view ) {
			$legacy[ 'wegame_' . substr( $tag, strlen( 'brackethive_' ) ) ] = $view;
		}
		return $legacy;
	}

	/**
	 * Enregistrement des shortcodes.
	 */
	public static function init() {
		$tags = array_merge( array_keys( self::map() ), array_keys( self::legacy_map() ) );
		foreach ( $tags as $tag ) {
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
		$map = self::map() + self::legacy_map();
		if ( ! isset( $map[ $tag ] ) ) {
			return '';
		}

		$atts = shortcode_atts(
			array(
				'header'  => 'yes',
				'players' => 'no',
				'fit'     => 'screen',
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
		$tid = '' !== $ref ? Brackethive_Tournament::resolve( $ref ) : 0;

		if ( '' !== $ref && ! $tid ) {
			return '<p class="brackethive-empty">' . esc_html__( 'Tournament not found.', 'brackethive' ) . '</p>';
		}

		// Sur la page d'un tournoi, c'est ce tournoi qui prime.
		if ( ! $tid && is_singular( Brackethive_Tournament::POST_TYPE ) ) {
			$tid = (int) get_the_ID();
		}

		$atts['tournament_id'] = $tid;

		wp_enqueue_style( 'brackethive-public' );
		wp_enqueue_script( 'brackethive-public' );

		return Brackethive_Render::view( $map[ $tag ], $atts );
	}
}
