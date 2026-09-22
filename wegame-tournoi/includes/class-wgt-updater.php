<?php
/**
 * Mises à jour automatiques depuis un manifeste auto-hébergé.
 *
 * Le plugin interroge une URL JSON (renseignée dans les réglages) et déclare
 * une mise à jour disponible à WordPress, qui l'affiche alors dans la page
 * Extensions comme n'importe quelle autre.
 *
 * Format du manifeste (JSON) :
 *   - version      (string, obligatoire)  ex. "2.4.0"
 *   - download_url (string, obligatoire)  URL HTTPS de l'archive zip
 *   - sha256       (string, optionnel)    empreinte SHA-256 hexadécimale du zip ;
 *                                         si présente, l'archive est vérifiée
 *                                         avant installation
 *   - name, author, homepage, requires, tested, requires_php, last_updated
 *                  (string, optionnels)
 *   - sections     (objet clé => HTML, optionnel)
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_Updater {

	const TRANSIENT = 'wgt_update_manifest';

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'pre_set_site_transient_update_plugins', array( __CLASS__, 'check' ) );
		add_filter( 'plugins_api', array( __CLASS__, 'info' ), 20, 3 );
		add_filter( 'upgrader_pre_download', array( __CLASS__, 'verified_download' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( __CLASS__, 'flush' ), 10, 2 );
	}

	/**
	 * Identifiant du fichier principal (« wegame-tournoi/wegame-tournoi.php »).
	 *
	 * @return string
	 */
	public static function basename() {
		return plugin_basename( WGT_FILE );
	}

	/**
	 * Slug du dossier.
	 *
	 * @return string
	 */
	public static function slug() {
		return dirname( plugin_basename( WGT_FILE ) );
	}

	/**
	 * Récupère le manifeste distant (mis en cache 6 heures).
	 *
	 * Le manifeste retourné est normalisé (voir normalize) : chaque champ a le
	 * type attendu, les champs optionnels absents sont présents et vides.
	 *
	 * @param bool $force Ignorer le cache.
	 * @return array|null
	 */
	public static function manifest( $force = false ) {
		$url = WGT_Settings::get( 'update_manifest_url' );

		if ( empty( $url ) || ! is_string( $url ) ) {
			return null;
		}

		// Le manifeste désigne du code à installer : jamais en clair.
		if ( 'https' !== wp_parse_url( $url, PHP_URL_SCHEME ) ) {
			return null;
		}

		if ( ! $force ) {
			$cached = get_transient( self::TRANSIENT );
			if ( is_array( $cached ) ) {
				return empty( $cached ) ? null : $cached;
			}
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => true,
				'headers'   => array( 'Accept' => 'application/json' ),
			)
		);

		if ( is_wp_error( $response ) || 200 !== (int) wp_remote_retrieve_response_code( $response ) ) {
			// Cache court en cas d'échec, pour ne pas ralentir l'admin.
			set_transient( self::TRANSIENT, array(), HOUR_IN_SECONDS );
			return null;
		}

		$data = json_decode( wp_remote_retrieve_body( $response ), true );
		$data = self::normalize( $data );

		if ( null === $data ) {
			set_transient( self::TRANSIENT, array(), HOUR_IN_SECONDS );
			return null;
		}

		set_transient( self::TRANSIENT, $data, 6 * HOUR_IN_SECONDS );

		return $data;
	}

	/**
	 * Valide et normalise le manifeste.
	 *
	 * Le paquet doit être servi en HTTPS : WordPress installerait sinon du code
	 * PHP récupéré sur un canal altérable. Les champs sont forcés au type
	 * attendu pour qu'aucun avertissement PHP ne surgisse plus loin.
	 *
	 * @param mixed $data Données décodées.
	 * @return array|null Manifeste normalisé, ou null s'il est invalide.
	 */
	protected static function normalize( $data ) {
		if ( ! is_array( $data ) ) {
			return null;
		}

		if ( ! isset( $data['version'], $data['download_url'] ) || ! is_string( $data['version'] ) || ! is_string( $data['download_url'] ) ) {
			return null;
		}

		$version = trim( $data['version'] );
		if ( '' === $version || ! preg_match( '/^[0-9]+(\.[0-9]+)*([\-+.][0-9A-Za-z.\-]+)?$/', $version ) ) {
			return null;
		}

		$download_url = esc_url_raw( trim( $data['download_url'] ), array( 'https' ) );
		if ( '' === $download_url || 'https' !== wp_parse_url( $download_url, PHP_URL_SCHEME ) ) {
			return null;
		}

		$out = array(
			'version'      => $version,
			'download_url' => $download_url,
			'sha256'       => '',
			'sections'     => array(),
		);

		// Empreinte optionnelle du zip (64 caractères hexadécimaux).
		if ( isset( $data['sha256'] ) && is_string( $data['sha256'] ) ) {
			$hash = strtolower( trim( $data['sha256'] ) );
			if ( preg_match( '/^[0-9a-f]{64}$/', $hash ) ) {
				$out['sha256'] = $hash;
			}
		}

		// Champs texte optionnels.
		foreach ( array( 'name', 'author', 'requires', 'tested', 'requires_php', 'last_updated' ) as $key ) {
			$out[ $key ] = isset( $data[ $key ] ) && is_scalar( $data[ $key ] ) ? sanitize_text_field( (string) $data[ $key ] ) : '';
		}

		$out['homepage'] = isset( $data['homepage'] ) && is_string( $data['homepage'] ) ? esc_url_raw( $data['homepage'] ) : '';

		// Sections : tableau associatif clé => HTML.
		if ( isset( $data['sections'] ) && is_array( $data['sections'] ) ) {
			foreach ( $data['sections'] as $key => $html ) {
				if ( ! is_string( $key ) || ! is_string( $html ) ) {
					continue;
				}
				$key = sanitize_key( $key );
				if ( '' === $key ) {
					continue;
				}
				$out['sections'][ $key ] = wp_kses_post( $html );
			}
		}

		return $out;
	}

	/**
	 * Valide la structure du manifeste.
	 *
	 * Conservée pour compatibilité ; normalize() fait le travail.
	 *
	 * @param mixed $data Données décodées.
	 * @return bool
	 */
	protected static function is_valid( $data ) {
		return null !== self::normalize( $data );
	}

	/**
	 * Déclare la mise à jour à WordPress.
	 *
	 * @param mixed $transient Transient update_plugins.
	 * @return mixed
	 */
	public static function check( $transient ) {
		if ( ! is_object( $transient ) ) {
			return $transient;
		}

		$manifest = self::manifest();
		if ( ! $manifest ) {
			return $transient;
		}

		$file = self::basename();

		$item                 = new stdClass();
		$item->id             = $file;
		$item->slug           = self::slug();
		$item->plugin         = $file;
		$item->new_version    = $manifest['version'];
		$item->url            = $manifest['homepage'];
		$item->package        = $manifest['download_url'];
		$item->tested         = $manifest['tested'];
		$item->requires_php   = $manifest['requires_php'];
		$item->icons          = array();
		$item->banners        = array();
		$item->banners_rtl    = array();
		$item->compatibility  = new stdClass();

		if ( version_compare( $manifest['version'], WGT_VERSION, '>' ) ) {
			if ( ! isset( $transient->response ) || ! is_array( $transient->response ) ) {
				$transient->response = array();
			}
			$transient->response[ $file ] = $item;
			if ( isset( $transient->no_update ) && is_array( $transient->no_update ) ) {
				unset( $transient->no_update[ $file ] );
			}
		} else {
			if ( ! isset( $transient->no_update ) || ! is_array( $transient->no_update ) ) {
				$transient->no_update = array();
			}
			$transient->no_update[ $file ] = $item;
		}

		return $transient;
	}

	/**
	 * Alimente la fiche « Voir les détails ».
	 *
	 * @param mixed  $result Résultat courant.
	 * @param string $action Action demandée.
	 * @param object $args   Arguments.
	 * @return mixed
	 */
	public static function info( $result, $action, $args ) {
		if ( 'plugin_information' !== $action ) {
			return $result;
		}
		if ( ! is_object( $args ) || ! isset( $args->slug ) || self::slug() !== $args->slug ) {
			return $result;
		}

		$manifest = self::manifest();
		if ( ! $manifest ) {
			return $result;
		}

		$info                = new stdClass();
		$info->name          = '' !== $manifest['name'] ? $manifest['name'] : 'We Game Tournoi';
		$info->slug          = self::slug();
		$info->version       = $manifest['version'];
		$info->author        = $manifest['author'];
		$info->homepage      = $manifest['homepage'];
		$info->requires      = $manifest['requires'];
		$info->tested        = $manifest['tested'];
		$info->requires_php  = $manifest['requires_php'];
		$info->last_updated  = $manifest['last_updated'];
		$info->download_link = $manifest['download_url'];
		$info->sections      = ! empty( $manifest['sections'] ) ? $manifest['sections'] : array( 'description' => '' );

		return $info;
	}

	/**
	 * Téléchargement vérifié par empreinte SHA-256.
	 *
	 * Le champ « sha256 » du manifeste est OPTIONNEL : sans lui, WordPress
	 * télécharge le paquet normalement. S'il est renseigné et que l'URL
	 * demandée est celle de notre paquet, on télécharge nous-mêmes le zip,
	 * on compare l'empreinte et on refuse l'installation en cas d'écart.
	 *
	 * @param bool|string|WP_Error $reply    Réponse courante (false = laisser WordPress faire).
	 * @param string               $package  URL du paquet.
	 * @param WP_Upgrader          $upgrader Instance.
	 * @return bool|string|WP_Error Chemin du fichier temporaire, ou erreur.
	 */
	public static function verified_download( $reply, $package, $upgrader = null ) {
		if ( false !== $reply ) {
			return $reply;
		}
		if ( ! is_string( $package ) || '' === $package ) {
			return $reply;
		}

		$manifest = self::manifest();
		if ( ! $manifest || '' === $manifest['sha256'] || $package !== $manifest['download_url'] ) {
			return $reply;
		}

		if ( is_object( $upgrader ) && isset( $upgrader->skin ) && is_object( $upgrader->skin ) && method_exists( $upgrader->skin, 'feedback' ) ) {
			$upgrader->skin->feedback( 'downloading_package', $package );
		}

		if ( ! function_exists( 'download_url' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		$tmp = download_url( $package, 300 );
		if ( is_wp_error( $tmp ) ) {
			return $tmp;
		}

		$hash = hash_file( 'sha256', $tmp );
		if ( ! is_string( $hash ) || ! hash_equals( $manifest['sha256'], strtolower( $hash ) ) ) {
			@unlink( $tmp ); // phpcs:ignore WordPress.PHP.NoSilencedErrors
			return new WP_Error(
				'wgt_bad_checksum',
				__( 'L’empreinte SHA-256 du paquet téléchargé ne correspond pas à celle annoncée par le manifeste : installation refusée.', 'wegame-tournoi' )
			);
		}

		return $tmp;
	}

	/**
	 * Vide le cache après une mise à jour.
	 *
	 * @param object $upgrader Instance.
	 * @param array  $options  Détails de l'opération.
	 */
	public static function flush( $upgrader, $options ) {
		if ( is_array( $options ) && isset( $options['action'], $options['type'] ) && 'update' === $options['action'] && 'plugin' === $options['type'] ) {
			delete_transient( self::TRANSIENT );
		}
	}

	/**
	 * Force une nouvelle vérification.
	 */
	public static function force_check() {
		delete_transient( self::TRANSIENT );
		delete_site_transient( 'update_plugins' );
		self::manifest( true );
	}
}
