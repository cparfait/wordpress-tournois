<?php
/**
 * Désinstallation.
 *
 * Par défaut, les données du tournoi sont CONSERVÉES : supprimer l'extension
 * par erreur ne doit pas effacer les équipes et les résultats. La suppression
 * n'a lieu que si l'option a été cochée dans les réglages.
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$wgt_settings = get_option( 'wgt_settings' );
$wgt_purge    = is_array( $wgt_settings ) && ! empty( $wgt_settings['delete_data_on_uninstall'] );

if ( ! $wgt_purge ) {
	return;
}

/*
 * Les tournois sont des contenus : ils doivent partir avec leurs données,
 * y compris ceux à la corbeille (« any » exclut la corbeille). Le tournoi
 * est lui-même sa page publique : il n'y a pas de page séparée à retirer.
 */
$wgt_posts = get_posts(
	array(
		'post_type'   => 'wgt_tournament',
		'post_status' => array( 'any', 'trash' ),
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

foreach ( $wgt_posts as $wgt_post_id ) {
	wp_delete_post( $wgt_post_id, true );
}

// Dernier tournoi choisi par chaque utilisateur de l'administration.
delete_metadata( 'user', 0, 'wgt_current_tournament', '', true );

$wgt_tables = array(
	$wpdb->prefix . 'wgt_games',
	$wpdb->prefix . 'wgt_matches',
	$wpdb->prefix . 'wgt_teams',
);

foreach ( $wgt_tables as $wgt_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$wgt_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL
}

delete_option( 'wgt_settings' );
delete_option( 'wgt_db_version' );
delete_option( 'wgt_default_tournament' );
delete_option( 'wgt_migrated_multi' );
delete_option( 'wgt_defaults_232' );
delete_transient( 'wgt_update_manifest' );
