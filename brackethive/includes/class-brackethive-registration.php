<?php
/**
 * Inscription publique des équipes.
 *
 * @package Brackethive
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Brackethive_Registration {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_nopriv_brackethive_register_team', array( __CLASS__, 'handle' ) );
		add_action( 'admin_post_brackethive_register_team', array( __CLASS__, 'handle' ) );
	}

	/**
	 * URL courante (pour la redirection après envoi).
	 *
	 * @return string
	 */
	public static function current_url() {
		global $wp;
		return home_url( add_query_arg( array(), $wp->request ) );
	}

	/**
	 * Traitement du formulaire.
	 */
	public static function handle() {
		$redirect = isset( $_POST['brackethive_redirect'] ) ? esc_url_raw( wp_unslash( $_POST['brackethive_redirect'] ) ) : home_url( '/' );

		if ( ! isset( $_POST['brackethive_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['brackethive_nonce'] ) ), 'brackethive_register_team' ) ) {
			self::redirect( $redirect, 'error', __( 'Session expired, please submit the form again.', 'brackethive' ) );
		}

		// Pot de miel anti-robot.
		if ( ! empty( $_POST['brackethive_website'] ) ) {
			self::redirect( $redirect, 'success', __( 'Thank you, your sign-up has been recorded.', 'brackethive' ) );
		}

		$tid = isset( $_POST['tournament_id'] ) ? (int) $_POST['tournament_id'] : 0;
		if ( ! $tid || ! Brackethive_Tournament::is_visible( $tid ) ) {
			self::redirect( $redirect, 'error', __( 'Tournament not found.', 'brackethive' ) );
		}

		if ( ! Brackethive_Tournament::get( $tid, 'registration_open' ) ) {
			self::redirect( $redirect, 'error', __( 'Sign-ups are closed.', 'brackethive' ) );
		}

		// L'attribut « required » du navigateur ne suffit pas : contrôle serveur.
		if ( empty( $_POST['consent'] ) ) {
			self::redirect( $redirect, 'error', __( 'You must accept the rules.', 'brackethive' ) );
		}

		/*
		 * Limitation de débit. Un nonce WordPress est, pour un visiteur non
		 * connecté, commun à tous les anonymes et valable 24 h : il ne protège
		 * donc pas d'un envoi automatisé répété. On plafonne à 3 inscriptions
		 * par heure et par adresse IP.
		 */
		if ( ! self::rate_limit_ok() ) {
			self::redirect( $redirect, 'error', __( 'Too many requests sent from this connection. Please try again in an hour.', 'brackethive' ) );
		}

		$max = (int) Brackethive_Tournament::get( $tid, 'registration_max' );
		if ( $max > 0 && Brackethive_Data::count_registered( $tid ) >= $max ) {
			self::redirect( $redirect, 'error', __( 'The maximum number of teams has been reached.', 'brackethive' ) );
		}

		$name = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		if ( '' === $name ) {
			self::redirect( $redirect, 'error', __( 'The team name is required.', 'brackethive' ) );
		}

		$result = Brackethive_Data::save_team(
			array(
				'tournament_id' => $tid,
				'name'          => $name,
				'tag'           => isset( $_POST['tag'] ) ? sanitize_text_field( wp_unslash( $_POST['tag'] ) ) : '',
				'captain'       => isset( $_POST['captain'] ) ? sanitize_text_field( wp_unslash( $_POST['captain'] ) ) : '',
				'email'         => isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '',
				'phone'         => isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '',
				'players'       => isset( $_POST['players'] ) ? sanitize_textarea_field( wp_unslash( $_POST['players'] ) ) : '',
				'seed'          => 0,
				'status'        => 'pending',
			)
		);

		if ( is_wp_error( $result ) ) {
			self::redirect( $redirect, 'error', $result->get_error_message() );
		}

		self::notify( $result, $tid );
		self::redirect( $redirect, 'success', Brackethive_Tournament::get( $tid, 'registration_msg' ) );
	}

	/**
	 * Autorise au plus 3 envois par heure et par adresse IP.
	 *
	 * @return bool
	 */
	protected static function rate_limit_ok() {
		$ip = self::client_ip();

		if ( '' === $ip ) {
			return true;
		}

		$key   = 'brackethive_rl_' . md5( $ip );
		$count = (int) get_transient( $key );

		if ( $count >= 3 ) {
			return false;
		}

		set_transient( $key, $count + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Notification à l'organisation.
	 *
	 * @param int $team_id Identifiant de l'équipe.
	 * @param int $tid     Tournoi.
	 */
	protected static function notify( $team_id, $tid ) {
		// Sans adresse configurée, l'organisation est prévenue sur l'adresse
		// d'administration du site.
		$to = Brackethive_Tournament::get( $tid, 'notify_email' );
		if ( ! $to || ! is_email( $to ) ) {
			$to = get_option( 'admin_email' );
		}

		$team = Brackethive_Data::get_team( $team_id );
		if ( ! $team ) {
			return;
		}

		self::acknowledge( $team, $tid );

		if ( ! $to || ! is_email( $to ) ) {
			return;
		}

		$subject = sprintf(
			/* translators: 1: nom du tournoi, 2: nom de l'équipe */
			__( '[%1$s] New sign-up: %2$s', 'brackethive' ),
			get_the_title( $tid ),
			$team['name']
		);

		$body  = __( 'New sign-up request received.', 'brackethive' ) . "\n\n";
		$body .= __( 'Team:', 'brackethive' ) . ' ' . $team['name'] . "\n";
		$body .= __( 'Captain:', 'brackethive' ) . ' ' . $team['captain'] . "\n";
		$body .= __( 'Email:', 'brackethive' ) . ' ' . $team['email'] . "\n";
		$body .= __( 'Phone:', 'brackethive' ) . ' ' . $team['phone'] . "\n\n";
		$body .= __( 'Players:', 'brackethive' ) . "\n" . $team['players'] . "\n\n";
		$body .= add_query_arg( array( 'page' => 'brackethive-teams', 'tournament' => (int) $tid ), admin_url( 'admin.php' ) ) . "\n";

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Accusé de réception envoyé au capitaine.
	 *
	 * @param array $team Équipe.
	 * @param int   $tid  Tournoi.
	 */
	protected static function acknowledge( $team, $tid ) {
		$email = isset( $team['email'] ) ? $team['email'] : '';
		if ( ! $email || ! is_email( $email ) ) {
			return;
		}

		$title   = get_the_title( $tid );
		$subject = sprintf(
			/* translators: 1: nom du tournoi, 2: nom de l'équipe */
			__( '[%1$s] Sign-up received: %2$s', 'brackethive' ),
			$title,
			$team['name']
		);

		$body  = sprintf(
			/* translators: %s: nom de l'équipe */
			__( 'Hello, the sign-up request for team "%s" has been received.', 'brackethive' ),
			$team['name']
		) . "\n\n";
		$body .= __( 'It will be reviewed by the organizers, who will get back to you if needed.', 'brackethive' ) . "\n\n";
		$body .= __( 'Follow the tournament:', 'brackethive' ) . ' ' . get_permalink( $tid ) . "\n";

		wp_mail( $email, $subject, $body );
	}

	/**
	 * Adresse IP du visiteur.
	 *
	 * REMOTE_ADDR fait foi. Si elle est privée ou locale (proxy, Cloudflare,
	 * conteneur…) et qu'un en-tête de transfert est présent, on retient la
	 * première adresse publique valide de X-Forwarded-For, ou celle de
	 * CF-Connecting-IP. Ces en-têtes ne sont jamais consultés quand
	 * REMOTE_ADDR est publique : un visiteur direct ne peut pas les forger.
	 *
	 * @return string Adresse, ou chaîne vide.
	 */
	public static function client_ip() {
		$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? trim( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) ) : '';
		if ( false === filter_var( $remote, FILTER_VALIDATE_IP ) ) {
			$remote = '';
		}

		$ip = $remote;

		$is_private = '' === $remote
			|| false === filter_var( $remote, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );

		if ( $is_private ) {
			$candidates = array();

			if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) {
				$candidates[] = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );
			}
			if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
				foreach ( explode( ',', sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) ) as $part ) {
					$candidates[] = $part;
				}
			}

			foreach ( $candidates as $candidate ) {
				$candidate = trim( $candidate );
				if ( false !== filter_var( $candidate, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
					$ip = $candidate;
					break;
				}
			}
		}

		/**
		 * Filtre l'adresse IP retenue pour le visiteur.
		 *
		 * @param string $ip     Adresse retenue (peut être vide).
		 * @param string $remote Valeur brute validée de REMOTE_ADDR.
		 */
		$ip = apply_filters( 'brackethive_client_ip', $ip, $remote );

		return is_string( $ip ) ? $ip : '';
	}

	/**
	 * Mémorise un message puis redirige.
	 *
	 * Le message est rattaché à un jeton aléatoire à usage unique transmis
	 * dans l'URL (brackethive_fb), et non plus à l'adresse IP : deux visiteurs
	 * derrière le même proxy ne peuvent plus lire le message de l'autre.
	 *
	 * @param string $url     URL de retour.
	 * @param string $type    success|error.
	 * @param string $message Message.
	 */
	protected static function redirect( $url, $type, $message ) {
		$token = wp_generate_password( 20, false );
		set_transient( 'brackethive_fb_' . $token, array( 'type' => $type, 'message' => $message ), 5 * MINUTE_IN_SECONDS );

		$url = add_query_arg(
			array(
				'brackethive_sent' => ( 'success' === $type ? '1' : '0' ),
				'brackethive_fb'   => $token,
			),
			$url
		);

		// L'ancre doit correspondre à l'onglet « registration » : avec
		// « #brackethive-inscription » le visiteur retombait sur l'onglet Tableau
		// et ne voyait jamais la confirmation.
		wp_safe_redirect( $url . '#brackethive-registration' );
		exit;
	}

	/**
	 * Récupère et consomme le message de retour (jeton brackethive_fb de l'URL).
	 *
	 * @return array|null Tableau avec « type » et « message », ou null.
	 */
	public static function get_feedback() {
		if ( empty( $_GET['brackethive_fb'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple jeton d'affichage du message de retour, sans effet de bord.
			return null;
		}

		// Le jeton est alphanumérique : il est assaini puis réduit à [A-Za-z0-9].
		$token = preg_replace( '/[^A-Za-z0-9]/', '', sanitize_text_field( wp_unslash( $_GET['brackethive_fb'] ) ) ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Simple jeton d'affichage du message de retour, sans effet de bord.
		if ( '' === $token || strlen( $token ) > 40 ) {
			return null;
		}

		$key  = 'brackethive_fb_' . $token;
		$data = get_transient( $key );
		if ( ! is_array( $data ) || ! isset( $data['type'], $data['message'] ) ) {
			return null;
		}

		delete_transient( $key );

		return array(
			'type'    => 'success' === $data['type'] ? 'success' : 'error',
			'message' => (string) $data['message'],
		);
	}
}
