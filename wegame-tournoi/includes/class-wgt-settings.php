<?php
/**
 * Réglages globaux du site.
 *
 * Tout ce qui concerne un tournoi en particulier (nom, date, format,
 * inscriptions…) vit dans WGT_Tournament. Cette classe ne conserve que les
 * réglages valables pour l'ensemble du site.
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Settings {

	const OPTION = 'wgt_settings';

	/**
	 * Valeurs par défaut.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'plugin_locale'            => '',
			'refresh_interval'         => 30,
			'hide_page_title'          => 1,
			'hide_title_selector'      => '',
			'update_manifest_url'      => '',
			'delete_data_on_uninstall' => 0,
		);
	}

	/**
	 * Enregistre les valeurs par défaut manquantes.
	 */
	public static function install_defaults() {
		$current = get_option( self::OPTION );
		if ( ! is_array( $current ) ) {
			$current = array();
		}
		update_option( self::OPTION, array_merge( self::defaults(), $current ) );
	}

	/**
	 * Tous les réglages globaux.
	 *
	 * @return array
	 */
	public static function all() {
		$saved = get_option( self::OPTION );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}
		return array_merge( self::defaults(), $saved );
	}

	/**
	 * Un réglage.
	 *
	 * @param string $key Clé.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Sauvegarde après nettoyage.
	 *
	 * @param array $input Données brutes.
	 */
	public static function save( $input ) {
		$out = self::all();

		if ( isset( $input['refresh_interval'] ) ) {
			$out['refresh_interval'] = max( 0, (int) $input['refresh_interval'] );
			if ( $out['refresh_interval'] > 0 && $out['refresh_interval'] < 10 ) {
				$out['refresh_interval'] = 10;
			}
		}

		if ( isset( $input['plugin_locale'] ) ) {
			// Langue de l'extension : vide (celle du site) ou un code de
			// langue réellement disponible.
			$locale = sanitize_text_field( $input['plugin_locale'] );
			$known  = array_keys( wgt_available_locales() );
			$known[] = 'en_US';
			$out['plugin_locale'] = in_array( $locale, $known, true ) ? $locale : '';
		}

		if ( isset( $input['update_manifest_url'] ) ) {
			// Le manifeste pilote l'installation de code PHP : HTTPS obligatoire.
			// Une URL non sécurisée est ignorée et l'ancienne valeur conservée.
			$url = esc_url_raw( trim( (string) $input['update_manifest_url'] ), array( 'https' ) );
			if ( '' === $url ) {
				if ( '' === trim( (string) $input['update_manifest_url'] ) ) {
					$out['update_manifest_url'] = '';
				}
			} elseif ( 'https' === wp_parse_url( $url, PHP_URL_SCHEME ) ) {
				$out['update_manifest_url'] = $url;
			}
		}

		if ( isset( $input['hide_title_selector'] ) ) {
			// Sélecteurs CSS : on écarte tout ce qui pourrait fermer le bloc
			// de style et injecter des règles ou du balisage.
			$allowed                    = '/[^a-zA-Z0-9 ,.#_:\-\[\]=>~+*()]/';
			$out['hide_title_selector'] = trim( preg_replace( $allowed, '', (string) $input['hide_title_selector'] ) );
		}

		/*
		 * Une case décochée n'est pas postée : on ne remet à zéro que celles
		 * réellement présentes dans le formulaire soumis.
		 */
		$all_checkboxes = array( 'hide_page_title', 'delete_data_on_uninstall' );
		$scope          = $all_checkboxes;

		if ( isset( $input['wgt_checkbox_scope'] ) ) {
			$declared = array_filter( array_map( 'sanitize_key', explode( ',', (string) $input['wgt_checkbox_scope'] ) ) );
			$scope    = array_intersect( $all_checkboxes, $declared );
		}

		foreach ( $scope as $key ) {
			$out[ $key ] = empty( $input[ $key ] ) ? 0 : 1;
		}

		update_option( self::OPTION, $out );
	}
}
