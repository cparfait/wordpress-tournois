<?php
/**
 * Plugin Name:       Brackethive Tournament Manager
 * Plugin URI:        https://github.com/cparfait/wordpress-tournois
 * Description:       Run an esports tournament from WordPress: 2 to 64 teams, single or double elimination, or a group stage, with a live public page.
 * Version:           2.6.0
 * Requires at least: 5.6
 * Requires PHP:      7.0
 * Author:            cparfait
 * Author URI:        https://github.com/cparfait
 * Text Domain:       brackethive
 * Domain Path:       /languages
 * License:           GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BRACKETHIVE_VERSION', '2.6.0' );
define( 'BRACKETHIVE_DB_VERSION', '2.6.0' );
define( 'BRACKETHIVE_FILE', __FILE__ );
define( 'BRACKETHIVE_PATH', plugin_dir_path( __FILE__ ) );
define( 'BRACKETHIVE_URL', plugin_dir_url( __FILE__ ) );

require_once BRACKETHIVE_PATH . 'includes/class-brackethive-tournament.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-install.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-bracket.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-qr.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-data.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-standings.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-settings.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-render.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-shortcodes.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-rest.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-registration.php';
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-io.php';
/*
 * Mises à jour auto-hébergées. Ce module est absent du paquet distribué sur
 * WordPress.org, où les mises à jour sont gérées par le répertoire officiel :
 * tout le code qui l'utilise est donc conditionné à sa présence.
 */
if ( file_exists( BRACKETHIVE_PATH . 'includes/class-brackethive-updater.php' ) ) {
	require_once BRACKETHIVE_PATH . 'includes/class-brackethive-updater.php';
}
require_once BRACKETHIVE_PATH . 'includes/class-brackethive-theme.php';

if ( is_admin() ) {
	require_once BRACKETHIVE_PATH . 'includes/class-brackethive-admin.php';
}

register_activation_hook( __FILE__, array( 'Brackethive_Install', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Brackethive_Install', 'deactivate' ) );

/**
 * Amorçage du plugin.
 */
function brackethive_bootstrap() {

	/*
	 * Reprise des données stockées sous l'ancien préfixe, avant toute
	 * lecture : réglages, tournois, équipes et résultats d'un site installé
	 * quand l'extension portait un autre nom. Ne fait rien ensuite.
	 */
	Brackethive_Install::migrate_legacy_prefix();

	// La mise à niveau (tables, migration, permaliens) attend « init » : le
	// type de contenu et le moteur de réécriture doivent exister avant.
	add_action( 'init', array( 'Brackethive_Install', 'maybe_upgrade' ), 20 );
	Brackethive_Tournament::init();
	Brackethive_Shortcodes::init();
	Brackethive_Rest::init();
	Brackethive_Registration::init();
	if ( class_exists( 'Brackethive_Updater' ) ) {
		Brackethive_Updater::init();
	}
	Brackethive_Theme::init();

	if ( is_admin() ) {
		Brackethive_Admin::init();
	}
}
add_action( 'plugins_loaded', 'brackethive_bootstrap' );

/**
 * Charge la traduction de l'extension.
 *
 * La langue suit celle du site, sauf si une langue a été choisie dans les
 * réglages de l'extension : on charge alors directement le catalogue
 * correspondant, ce qui fonctionne aussi bien pour une installation
 * auto-hébergée que pour une extension du répertoire WordPress.org.
 */
function brackethive_load_locale() {
	$chosen = Brackethive_Settings::get( 'plugin_locale' );
	$locale = $chosen ? $chosen : determine_locale();

	// L'anglais est la langue source : aucun catalogue à charger.
	if ( '' === $locale || 'en_US' === $locale ) {
		return;
	}

	/*
	 * Le catalogue peut venir de l'extension (installation auto-hébergée) ou
	 * du dossier des traductions du site, où WordPress.org dépose celles de
	 * translate.wordpress.org.
	 */
	$file = '';
	foreach ( array( BRACKETHIVE_PATH . 'languages', WP_LANG_DIR . '/plugins' ) as $dir ) {
		$candidate = $dir . '/brackethive-' . $locale . '.mo';
		if ( file_exists( $candidate ) ) {
			$file = $candidate;
			break;
		}
	}

	if ( '' === $file ) {
		return;
	}

	/*
	 * Le catalogue est enregistré sous la langue courante du site, et non
	 * sous celle du fichier : WordPress range les traductions par langue et
	 * ne consulte que celle en cours. C'est ce qui permet d'afficher
	 * l'extension dans une autre langue que le reste du site.
	 */
	unload_textdomain( 'brackethive' );
	load_textdomain( 'brackethive', $file );
}
add_action( 'init', 'brackethive_load_locale', 0 );

/**
 * Langues disponibles pour l'extension : les catalogues livrés avec elle et
 * ceux installés par le site.
 *
 * @return array Code de langue => nom affiché.
 */
function brackethive_available_locales() {
	$names = array(
		'fr_FR' => 'Français',
		'fr_BE' => 'Français de Belgique',
		'fr_CA' => 'Français du Canada',
		'es_ES' => 'Español',
		'de_DE' => 'Deutsch',
		'it_IT' => 'Italiano',
		'pt_PT' => 'Português',
		'pt_BR' => 'Português do Brasil',
		'nl_NL' => 'Nederlands',
		'pl_PL' => 'Polski',
		'ru_RU' => 'Русский',
		'en_GB' => 'English (UK)',
	);

	$found = array();
	$dirs  = array( BRACKETHIVE_PATH . 'languages', WP_LANG_DIR . '/plugins' );

	foreach ( $dirs as $dir ) {
		foreach ( (array) glob( $dir . '/brackethive-*.mo' ) as $file ) {
			$locale = substr( basename( $file, '.mo' ), strlen( 'brackethive-' ) );
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
function brackethive_enqueue_public_assets() {
	wp_register_style( 'brackethive-public', BRACKETHIVE_URL . 'assets/css/brackethive-public.css', array(), BRACKETHIVE_VERSION );
	wp_register_script( 'brackethive-public', BRACKETHIVE_URL . 'assets/js/brackethive-public.js', array(), BRACKETHIVE_VERSION, true );

	wp_localize_script(
		'brackethive-public',
		'BRACKETHIVE_CFG',
		array(
			'endpoint' => esc_url_raw( rest_url( 'brackethive/v1/render' ) ),
			'interval' => (int) Brackethive_Settings::get( 'refresh_interval' ),
			'i18n'     => array(
				'updated' => __( 'Updated', 'brackethive' ),
				'offline' => __( 'Offline — retrying', 'brackethive' ),
			),
		)
	);
}
add_action( 'wp_enqueue_scripts', 'brackethive_enqueue_public_assets' );

/**
 * Raccourci d'accès au préfixe des tables.
 *
 * @param string $table teams|matches|games.
 * @return string
 */
function brackethive_table( $table ) {
	global $wpdb;
	return $wpdb->prefix . 'brackethive_' . $table;
}
