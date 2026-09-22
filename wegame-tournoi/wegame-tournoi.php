<?php
/**
 * Plugin Name:       We Game Tournoi
 * Plugin URI:        https://github.com/cparfait/wordpress-tournois
 * Description:       Gestion et affichage de tournois e-sport (2 à 64 équipes) : élimination directe, double élimination ou phase de poules. Conçu pour We Game 2026, Tournoi Call of Duty.
 * Version:           2.4.3
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

define( 'WGT_VERSION', '2.4.3' );
define( 'WGT_DB_VERSION', '2.4.3' );
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
require_once WGT_PATH . 'includes/class-wgt-updater.php';
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
	load_plugin_textdomain( 'wegame-tournoi', false, dirname( plugin_basename( WGT_FILE ) ) . '/languages' );

	// La mise à niveau (tables, migration, permaliens) attend « init » : le
	// type de contenu et le moteur de réécriture doivent exister avant.
	add_action( 'init', array( 'WGT_Install', 'maybe_upgrade' ), 20 );
	WGT_Tournament::init();
	WGT_Shortcodes::init();
	WGT_Rest::init();
	WGT_Registration::init();
	WGT_Updater::init();
	WGT_Theme::init();

	if ( is_admin() ) {
		WGT_Admin::init();
	}
}
add_action( 'plugins_loaded', 'wgt_bootstrap' );

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
				'updated' => __( 'Mis à jour', 'wegame-tournoi' ),
				'offline' => __( 'Hors ligne — nouvelle tentative en cours', 'wegame-tournoi' ),
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
