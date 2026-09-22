<?php
/**
 * Désinstallation.
 *
 * Par défaut, les données du tournoi sont CONSERVÉES : supprimer l'extension
 * par erreur ne doit pas effacer les équipes et les résultats. La suppression
 * n'a lieu que si l'option a été cochée dans les réglages.
 *
 * @package Brackethive
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

$brackethive_settings = get_option( 'brackethive_settings' );
$brackethive_purge    = is_array( $brackethive_settings ) && ! empty( $brackethive_settings['delete_data_on_uninstall'] );

if ( ! $brackethive_purge ) {
	return;
}

/*
 * Les tournois sont des contenus : ils doivent partir avec leurs données,
 * y compris ceux à la corbeille (« any » exclut la corbeille). Le tournoi
 * est lui-même sa page publique : il n'y a pas de page séparée à retirer.
 */
$brackethive_posts = get_posts(
	array(
		'post_type'   => 'brackethive_tourney',
		'post_status' => array( 'any', 'trash' ),
		'numberposts' => -1,
		'fields'      => 'ids',
	)
);

foreach ( $brackethive_posts as $brackethive_post_id ) {
	wp_delete_post( $brackethive_post_id, true );
}

// Dernier tournoi choisi par chaque utilisateur de l'administration.
delete_metadata( 'user', 0, 'brackethive_current_tournament', '', true );

$brackethive_tables = array(
	$wpdb->prefix . 'brackethive_games',
	$wpdb->prefix . 'brackethive_matches',
	$wpdb->prefix . 'brackethive_teams',
);

foreach ( $brackethive_tables as $brackethive_table ) {
	$wpdb->query( "DROP TABLE IF EXISTS {$brackethive_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange, PluginCheck.Security.DirectDB.UnescapedDBParameter -- Suppression volontaire des tables propres à l'extension à la désinstallation ; nom de table construit depuis $wpdb->prefix, sans donnée utilisateur ni mise en cache.
}

delete_option( 'brackethive_settings' );
delete_option( 'brackethive_db_version' );
delete_option( 'brackethive_default_tournament' );
delete_option( 'brackethive_migrated_multi' );
delete_option( 'brackethive_defaults_232' );
delete_option( 'brackethive_welcome' );
delete_transient( 'brackethive_update_manifest' );
