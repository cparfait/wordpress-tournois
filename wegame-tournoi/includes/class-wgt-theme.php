<?php
/**
 * Intégration au thème du site.
 *
 * Masque, si l'option est activée, le bandeau de titre que le thème ajoute
 * au-dessus du contenu sur les pages qui affichent le tournoi.
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Theme {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'body_class', array( __CLASS__, 'body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'inline_css' ), 20 );
		add_filter( 'the_title', array( __CLASS__, 'blank_title' ), 10, 2 );
	}

	/**
	 * Vide le titre de la page de tournoi rendu par le thème.
	 *
	 * Le masquage CSS dépend des sélecteurs du thème ; ce filtre agit quel
	 * que soit le thème, puisque presque tous affichent le titre via
	 * the_title() ou get_the_title(). Le titre de l'onglet du navigateur, les
	 * menus et l'administration ne sont pas concernés.
	 *
	 * @param string $title Titre.
	 * @param int    $id    Contenu.
	 * @return string
	 */
	public static function blank_title( $title, $id = 0 ) {
		if ( is_admin() || ! $id || ! WGT_Settings::get( 'hide_page_title' ) ) {
			return $title;
		}
		if ( doing_action( 'wp_head' ) || (int) $id !== (int) get_queried_object_id() || ! self::is_tournament_page() ) {
			return $title;
		}
		return '';
	}

	/**
	 * La page courante affiche-t-elle le tournoi ?
	 *
	 * @return bool
	 */
	public static function is_tournament_page() {
		if ( ! is_singular() ) {
			return false;
		}

		$post = get_post();
		if ( ! $post ) {
			return false;
		}

		// La page propre d'un tournoi en fait évidemment partie.
		if ( WGT_Tournament::POST_TYPE === $post->post_type ) {
			return true;
		}

		if ( '' === $post->post_content ) {
			return false;
		}

		foreach ( array_keys( WGT_Shortcodes::map() ) as $tag ) {
			if ( has_shortcode( $post->post_content, $tag ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Classes ajoutées au body.
	 *
	 * @param array $classes Classes existantes.
	 * @return array
	 */
	public static function body_class( $classes ) {
		if ( ! self::is_tournament_page() ) {
			return $classes;
		}

		$classes[] = 'wgt-has-tournament';

		if ( WGT_Settings::get( 'hide_page_title' ) ) {
			$classes[] = 'wgt-hide-page-title';
		}

		return $classes;
	}

	/**
	 * Sélecteurs masqués.
	 *
	 * Couvre les conteneurs de titre les plus répandus, dont celui de Salient
	 * (#page-header-wrap), et accepte des sélecteurs supplémentaires réglables.
	 *
	 * @return array
	 */
	protected static function selectors() {
		$base = array(
			// Salient.
			'#page-header-wrap',
			'#page-header-bg',
			'.page-header-no-bg',
			// Thèmes courants.
			'.entry-header .entry-title',
			'.entry-header',
			'.page-header',
			'.page-title',
			'.post-title',
			'.wp-block-post-title',
			'.archive-title',
			'.title-bar',
			'.page-title-bar',
			'.hero-title',
			'.fusion-page-title-bar',
			'.elementor-page-title',
			'.ast-single-entry-banner',
			'.ast-archive-title',
			'.oceanwp-page-header',
			'.page-header-title',
		);

		$extra = (string) WGT_Settings::get( 'hide_title_selector' );
		if ( '' !== $extra ) {
			foreach ( explode( ',', $extra ) as $selector ) {
				$selector = trim( $selector );
				if ( '' !== $selector ) {
					$base[] = $selector;
				}
			}
		}

		return $base;
	}

	/**
	 * Injecte la règle de masquage.
	 */
	public static function inline_css() {
		if ( ! self::is_tournament_page() || ! WGT_Settings::get( 'hide_page_title' ) ) {
			return;
		}

		$parts = array();
		foreach ( self::selectors() as $selector ) {
			$parts[] = 'body.wgt-hide-page-title ' . $selector;
		}

		$css = implode( ",\n", $parts ) . " {\n\tdisplay: none !important;\n}\n";

		// La feuille publique n'est enregistrée qu'au rendu du shortcode :
		// on l'enfile ici pour disposer d'un support à la règle en ligne.
		wp_enqueue_style( 'wgt-public' );
		wp_add_inline_style( 'wgt-public', $css );
	}
}
