<?php
/**
 * Plugin Name:       We Game Tournoi
 * Plugin URI:        https://github.com/cparfait/wordpress-tournois
 * Description:       Run an esports tournament from WordPress: 2 to 64 teams, single or double elimination, or a group stage, with a live public page.
 * Version:           2.5.0
 * Requires at least: 5.6
 * Requires PHP:      7.0
 * Author:            cparfait
 * Author URI:        https://github.com/cparfait
 * Text Domain:       wegame-tournoi
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WGT_VERSION', '2.5.0' );
define( 'WGT_DB_VERSION', '2.5.0' );
define( 'WGT_FILE', __FILE__ );
define( 'WGT_PATH', plugin_dir_path( __FILE__ ) );
define( 'WGT_URL', plugin_dir_url( __FILE__ ) );

require_once WGT_PATH . 'includes/class-wgt-tournament.php';
require_once WGT_PATH . 'includes/class-wgt-install.php';
require_once WGT_PATH . 'includes/class-wgt-bracket.php';
require_once WGT_PATH . 'includes/class-wgt-qr.php';
require_once WGT_PATH . 'includes/class-wgt-data.php';
require_once WGT_PATH . 'includes/class-wgt-standings.php';
require_once WGT_PATH . 'includes/class-wgt-settings.php';
require_once WGT_PATH . 'includes/class-wgt-render.php';
require_once WGT_PATH . 'includes/class-wgt-shortcodes.php';
require_once WGT_PATH . 'includes/class-wgt-rest.php';
require_once WGT_PATH . 'includes/class-wgt-registration.php';
require_once WGT_PATH . 'includes/class-wgt-io.php';
/*
 * Mises à jour auto-hébergées. Ce module est absent du paquet distribué sur
 * WordPress.org, où les mises à jour sont gérées par le répertoire officiel :
 * tout le code qui l'utilise est donc conditionné à sa présence.
 */
if ( file_exists( WGT_PATH . 'includes/class-wgt-updater.php' ) ) {
	require_once WGT_PATH . 'includes/class-wgt-updater.php';
}
require_once WGT_PATH . 'includes/class-wgt-theme.php';

if ( is_admin() ) {
	require_once WGT_PATH . 'includes/class-wgt-admin.php';
}

register_activation_hook( __FILE__, array( 'WGT_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'WGT_Install', 'deactivate' ) );

/**
 * Amorçage du plugin.
 */
function wgt_bootstrap() {

	// La mise à niveau (tables, migration, permaliens) attend « init » : le
	// type de contenu et le moteur de réécriture doivent exister avant.
	add_action( 'init', array( 'WGT_Install', 'maybe_upgrade' ), 20 );
	WGT_Tournament::init();
	WGT_Shortcodes::init();
	WGT_Rest::init();
	WGT_Registration::init();
	if ( class_exists( 'WGT_Updater' ) ) {
		WGT_Updater::init();
	}
	WGT_Theme::init();

	if ( is_admin() ) {
		WGT_Admin::init();
	}
}
add_action( 'plugins_loaded', 'wgt_bootstrap' );

/**
 * Charge la traduction de l'extension.
 *
 * La langue suit celle du site, sauf si une langue a été choisie dans les
 * réglages de l'extension : on charge alors directement le catalogue
 * correspondant, ce qui fonctionne aussi bien pour une installation
 * auto-hébergée que pour une extension du répertoire WordPress.org.
 */
function wgt_load_locale() {
	$chosen = WGT_Settings::get( 'plugin_locale' );
	$locale = $chosen ? $chosen : determine_locale();

	// L'anglais est la langue source : aucun catalogue à charger.
	if ( '' === $locale || 'en_US' === $locale ) {
		return;
	}

	$file = WGT_PATH . 'languages/wegame-tournoi-' . $locale . '.mo';
	if ( ! file_exists( $file ) ) {
		return;
	}

	/*
	 * Le catalogue est enregistré sous la langue courante du site, et non
	 * sous celle du fichier : WordPress range les traductions par langue et
	 * ne consulte que celle en cours. C'est ce qui permet d'afficher
	 * l'extension dans une autre langue que le reste du site.
	 */
	unload_textdomain( 'wegame-tournoi' );
	load_textdomain( 'wegame-tournoi', $file );
}
add_action( 'init', 'wgt_load_locale', 0 );

/**
 * Langues disponibles pour l'extension : les catalogues livrés avec elle et
 * ceux installés par le site.
 *
 * @return array Code de langue => nom affiché.
 */
function wgt_available_locales() {
	$names = array(
		'fr_FR'        => 'Français',
		'fr_BE'        => 'Français de Belgique',
		'fr_CA'        => 'Français du Canada',
		'es_ES'        => 'Español',
		'de_DE'        => 'Deutsch',
		'it_IT'        => 'Italiano',
		'pt_PT'        => 'Português',
		'pt_BR'        => 'Português do Brasil',
		'nl_NL'        => 'Nederlands',
		'pl_PL'        => 'Polski',
		'ru_RU'        => 'Русский',
		'en_GB'        => 'English (UK)',
	);

	$found = array();
	$dirs  = array( WGT_PATH . 'languages', WP_LANG_DIR . '/plugins' );

	foreach ( $dirs as $dir ) {
		foreach ( (array) glob( $dir . '/wegame-tournoi-*.mo' ) as $file ) {
			$locale = substr( basename( $file, '.mo' ), strlen( 'wegame-tournoi-' ) );
			if ( '' !== $locale ) {
				$found[ $locale ] = isset( $names[ $locale ] ) ? $names[ $locale ] : $locale;
			}
		}
	}

	ksort( $found );

	return $found;
}

/**
 * Feuilles de style / scripts du front.
 */
function wgt_enqueue_public_assets() {
	wp_register_style( 'wgt-public', WGT_URL . 'assets/css/wgt-public.css', array(), WGT_VERSION );
	wp_register_script( 'wgt-public', WGT_URL . 'assets/js/wgt-public.js', array(), WGT_VERSION, true );

	wp_localize_script(
		'wgt-public',
		'WGT_CFG',
		array(
			'endpoint' => esc_url_raw( rest_url( 'wegame/v1/render' ) ),
			'interval' => (int) WGT_Settings::get( 'refresh_interval' ),
			'i18n'     => array(
				'updated' => __( 'Updated', 'wegame-tournoi' ),
				'offline' => __( 'Offline — retrying', 'wegame-tournoi' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'wgt_enqueue_public_assets' );

/**
 * Raccourci d'accès au préfixe des tables.
 *
 * @param string $table teams|matches|games.
 * @return string
 */
function wgt_table( $table ) {
	global $wpdb;
	return $wpdb->prefix . 'wgt_' . $table;
}
