<?php
/**
 * Générateur de QR code.
 *
 * Encodage en mode « octets », correction d'erreur niveau M, versions 1 à 10
 * (jusqu'à 213 caractères), ce qui couvre largement une adresse de page.
 *
 * Le calcul est fait côté serveur : le QR code est affiché en SVG dans la
 * page, sans dépendre d'un script ni d'un service extérieur.
 *
 * @package WeGameTournoi
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WGT_QR {

	/**
	 * Nombre total de mots de code, par version (1 à 10).
	 *
	 * @var array
	 */
	protected static $total_codewords = array(
		1 => 26, 2 => 44, 3 => 70, 4 => 100, 5 => 134,
		6 => 172, 7 => 196, 8 => 242, 9 => 292, 10 => 346,
	);

	/**
	 * Niveau M : mots de correction par bloc, puis groupes de blocs sous la
	 * forme array( array( nombre de blocs, mots de données par bloc ) ).
	 *
	 * @var array
	 */
	protected static $ec_m = array(
		1  => array( 10, array( array( 1, 16 ) ) ),
		2  => array( 16, array( array( 1, 28 ) ) ),
		3  => array( 26, array( array( 1, 44 ) ) ),
		4  => array( 18, array( array( 2, 32 ) ) ),
		5  => array( 24, array( array( 2, 43 ) ) ),
		6  => array( 16, array( array( 4, 27 ) ) ),
		7  => array( 18, array( array( 4, 31 ) ) ),
		8  => array( 22, array( array( 2, 38 ), array( 2, 39 ) ) ),
		9  => array( 22, array( array( 3, 36 ), array( 2, 37 ) ) ),
		10 => array( 26, array( array( 4, 43 ), array( 1, 44 ) ) ),
	);

	/**
	 * Centres des motifs d'alignement, par version.
	 *
	 * @var array
	 */
	protected static $alignment = array(
		1  => array(),
		2  => array( 6, 18 ),
		3  => array( 6, 22 ),
		4  => array( 6, 26 ),
		5  => array( 6, 30 ),
		6  => array( 6, 34 ),
		7  => array( 6, 22, 38 ),
		8  => array( 6, 24, 42 ),
		9  => array( 6, 26, 46 ),
		10 => array( 6, 28, 50 ),
	);

	/**
	 * Tables de logarithmes du corps de Galois GF(256).
	 *
	 * @var array
	 */
	protected static $exp = array();

	/**
	 * Tables d'antilogarithmes.
	 *
	 * @var array
	 */
	protected static $log = array();

	/* ---------------------------------------------------------------------
	 * API
	 * ------------------------------------------------------------------ */

	/**
	 * Matrice du QR code : lignes de booléens (true = module noir).
	 *
	 * @param string $text Contenu à encoder.
	 * @return array|null Null si le contenu est vide ou trop long.
	 */
	public static function matrix( $text ) {
		$text = (string) $text;
		$len  = strlen( $text );

		if ( 0 === $len ) {
			return null;
		}

		$version = self::version_for( $len );
		if ( ! $version ) {
			return null;
		}

		$data    = self::encode_data( $text, $version );
		$final   = self::interleave( $data, $version );
		$size    = 17 + $version * 4;
		$reserved = self::reserved_map( $version, $size );

		/*
		 * Choix du masque : la pénalité est calculée sur une grille d'essai
		 * où les modules de format et de version sont laissés blancs, comme
		 * le veut l'algorithme de référence. À égalité, le premier masque
		 * l'emporte.
		 */
		$best       = 0;
		$best_score = null;

		for ( $mask = 0; $mask < 8; $mask++ ) {
			$grid  = self::place( $final, $version, $size, $reserved, $mask, true );
			$score = self::penalty( $grid, $size );
			if ( null === $best_score || $score < $best_score ) {
				$best_score = $score;
				$best       = $mask;
			}
		}

		return self::place( $final, $version, $size, $reserved, $best );
	}

	/**
	 * QR code au format SVG.
	 *
	 * @param string $text  Contenu.
	 * @param int    $quiet Marge blanche, en modules.
	 * @return string SVG, ou chaîne vide.
	 */
	public static function svg( $text, $quiet = 4 ) {
		$grid = self::matrix( $text );
		if ( ! $grid ) {
			return '';
		}

		$count = count( $grid );
		$quiet = max( 0, (int) $quiet );
		$total = $count + $quiet * 2;

		$path = '';
		for ( $r = 0; $r < $count; $r++ ) {
			for ( $c = 0; $c < $count; $c++ ) {
				if ( $grid[ $r ][ $c ] ) {
					$path .= 'M' . ( $c + $quiet ) . ' ' . ( $r + $quiet ) . 'h1v1h-1z';
				}
			}
		}

		return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $total . ' ' . $total . '" shape-rendering="crispEdges">'
			. '<rect width="' . $total . '" height="' . $total . '" fill="#ffffff"/>'
			. '<path d="' . $path . '" fill="#000000"/></svg>';
	}

	/**
	 * QR code au format PNG.
	 *
	 * Écrit le fichier sans bibliothèque externe : image en niveaux de gris
	 * 1 bit, compressée en zlib.
	 *
	 * @param string $text  Contenu.
	 * @param int    $scale Pixels par module.
	 * @param int    $quiet Marge blanche, en modules.
	 * @return string Données binaires PNG, ou chaîne vide.
	 */
	public static function png( $text, $scale = 12, $quiet = 4 ) {
		$grid = self::matrix( $text );
		if ( ! $grid || ! function_exists( 'gzcompress' ) ) {
			return '';
		}

		$count = count( $grid );
		$scale = max( 1, (int) $scale );
		$quiet = max( 0, (int) $quiet );
		$total = ( $count + $quiet * 2 ) * $scale;

		// Une ligne = un octet de filtre + un octet par pixel (0 = noir).
		$raw = '';
		for ( $y = 0; $y < $total; $y++ ) {
			$row   = str_repeat( "\xff", $total );
			$model = (int) floor( $y / $scale ) - $quiet;

			if ( $model >= 0 && $model < $count ) {
				for ( $c = 0; $c < $count; $c++ ) {
					if ( $grid[ $model ][ $c ] ) {
						$start = ( $c + $quiet ) * $scale;
						for ( $i = 0; $i < $scale; $i++ ) {
							$row[ $start + $i ] = "\x00";
						}
					}
				}
			}

			$raw .= "\x00" . $row;
		}

		$ihdr = pack( 'NN', $total, $total ) . "\x08\x00\x00\x00\x00";

		return "\x89PNG\r\n\x1a\n"
			. self::png_chunk( 'IHDR', $ihdr )
			. self::png_chunk( 'IDAT', gzcompress( $raw, 9 ) )
			. self::png_chunk( 'IEND', '' );
	}

	/**
	 * Adresse « data: » d'un QR code SVG, utilisable en src ou en href.
	 *
	 * @param string $text Contenu.
	 * @return string
	 */
	public static function svg_data_uri( $text ) {
		$svg = self::svg( $text );
		return '' === $svg ? '' : 'data:image/svg+xml;base64,' . base64_encode( $svg );
	}

	/**
	 * Adresse « data: » d'un QR code PNG.
	 *
	 * @param string $text  Contenu.
	 * @param int    $scale Pixels par module.
	 * @return string
	 */
	public static function png_data_uri( $text, $scale = 12 ) {
		$png = self::png( $text, $scale );
		return '' === $png ? '' : 'data:image/png;base64,' . base64_encode( $png );
	}

	/* ---------------------------------------------------------------------
	 * PNG
	 * ------------------------------------------------------------------ */

	/**
	 * Un bloc PNG, avec sa somme de contrôle.
	 *
	 * @param string $type Type (4 caractères).
	 * @param string $data Contenu.
	 * @return string
	 */
	protected static function png_chunk( $type, $data ) {
		return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) );
	}

	/* ---------------------------------------------------------------------
	 * Encodage des données
	 * ------------------------------------------------------------------ */

	/**
	 * Plus petite version capable de contenir $len octets (niveau M).
	 *
	 * @param int $len Longueur en octets.
	 * @return int 0 si aucune version ne convient.
	 */
	protected static function version_for( $len ) {
		foreach ( array_keys( self::$total_codewords ) as $version ) {
			$capacity = self::data_codewords( $version ) - 2; // en-tête : mode + compteur.
			if ( $version >= 10 ) {
				$capacity--; // compteur sur 16 bits.
			}
			if ( $len <= $capacity ) {
				return $version;
			}
		}
		return 0;
	}

	/**
	 * Nombre de mots de code de données d'une version.
	 *
	 * @param int $version Version.
	 * @return int
	 */
	protected static function data_codewords( $version ) {
		list( $ec_per_block, $groups ) = self::$ec_m[ $version ];

		$blocks = 0;
		foreach ( $groups as $group ) {
			$blocks += $group[0];
		}

		return self::$total_codewords[ $version ] - $ec_per_block * $blocks;
	}

	/**
	 * Suite de mots de code de données : en-tête, contenu, terminateur,
	 * remplissage.
	 *
	 * @param string $text    Contenu.
	 * @param int    $version Version.
	 * @return array Octets.
	 */
	protected static function encode_data( $text, $version ) {
		$len   = strlen( $text );
		$bits  = '';
		$bits .= '0100'; // Mode octets.
		$bits .= str_pad( decbin( $len ), $version >= 10 ? 16 : 8, '0', STR_PAD_LEFT );

		for ( $i = 0; $i < $len; $i++ ) {
			$bits .= str_pad( decbin( ord( $text[ $i ] ) ), 8, '0', STR_PAD_LEFT );
		}

		$capacity = self::data_codewords( $version ) * 8;

		// Terminateur : jusqu'à quatre zéros.
		$bits .= str_repeat( '0', min( 4, $capacity - strlen( $bits ) ) );

		// Alignement sur un multiple de huit.
		if ( strlen( $bits ) % 8 ) {
			$bits .= str_repeat( '0', 8 - strlen( $bits ) % 8 );
		}

		$codewords = array();
		foreach ( str_split( $bits, 8 ) as $byte ) {
			$codewords[] = bindec( $byte );
		}

		// Octets de remplissage alternés.
		$pad = array( 0xEC, 0x11 );
		$i   = 0;
		while ( count( $codewords ) < self::data_codewords( $version ) ) {
			$codewords[] = $pad[ $i % 2 ];
			$i++;
		}

		return $codewords;
	}

	/**
	 * Répartit les données en blocs, calcule la correction d'erreur et
	 * entrelace le tout.
	 *
	 * @param array $data    Mots de code de données.
	 * @param int   $version Version.
	 * @return array Mots de code finaux.
	 */
	protected static function interleave( $data, $version ) {
		list( $ec_per_block, $groups ) = self::$ec_m[ $version ];

		$blocks = array();
		$ecs    = array();
		$offset = 0;

		foreach ( $groups as $group ) {
			for ( $b = 0; $b < $group[0]; $b++ ) {
				$block    = array_slice( $data, $offset, $group[1] );
				$offset  += $group[1];
				$blocks[] = $block;
				$ecs[]    = self::error_correction( $block, $ec_per_block );
			}
		}

		$out = array();

		$longest = 0;
		foreach ( $blocks as $block ) {
			$longest = max( $longest, count( $block ) );
		}

		for ( $i = 0; $i < $longest; $i++ ) {
			foreach ( $blocks as $block ) {
				if ( isset( $block[ $i ] ) ) {
					$out[] = $block[ $i ];
				}
			}
		}

		for ( $i = 0; $i < $ec_per_block; $i++ ) {
			foreach ( $ecs as $ec ) {
				if ( isset( $ec[ $i ] ) ) {
					$out[] = $ec[ $i ];
				}
			}
		}

		return $out;
	}

	/**
	 * Prépare les tables du corps de Galois GF(256).
	 */
	protected static function init_gf() {
		if ( ! empty( self::$exp ) ) {
			return;
		}

		self::$exp = array_fill( 0, 512, 0 );
		self::$log = array_fill( 0, 256, 0 );

		$x = 1;
		for ( $i = 0; $i < 255; $i++ ) {
			self::$exp[ $i ]   = $x;
			self::$log[ $x ]   = $i;
			$x <<= 1;
			if ( $x & 0x100 ) {
				$x ^= 0x11D;
			}
		}
		for ( $i = 255; $i < 512; $i++ ) {
			self::$exp[ $i ] = self::$exp[ $i - 255 ];
		}
	}

	/**
	 * Mots de correction d'erreur d'un bloc (Reed-Solomon).
	 *
	 * @param array $block  Mots de données.
	 * @param int   $count  Nombre de mots de correction.
	 * @return array
	 */
	protected static function error_correction( $block, $count ) {
		self::init_gf();

		// Polynôme générateur.
		$generator = array( 1 );
		for ( $i = 0; $i < $count; $i++ ) {
			$next = array_fill( 0, count( $generator ) + 1, 0 );
			foreach ( $generator as $index => $coefficient ) {
				$next[ $index ] ^= $coefficient;
				if ( $coefficient ) {
					$next[ $index + 1 ] ^= self::$exp[ ( self::$log[ $coefficient ] + $i ) % 255 ];
				}
			}
			$generator = $next;
		}

		$remainder = array_merge( $block, array_fill( 0, $count, 0 ) );

		for ( $i = 0; $i < count( $block ); $i++ ) {
			$factor = $remainder[ $i ];
			if ( ! $factor ) {
				continue;
			}
			$factor = self::$log[ $factor ];
			foreach ( $generator as $index => $coefficient ) {
				if ( $coefficient ) {
					$remainder[ $i + $index ] ^= self::$exp[ ( self::$log[ $coefficient ] + $factor ) % 255 ];
				}
			}
		}

		return array_slice( $remainder, count( $block ) );
	}

	/* ---------------------------------------------------------------------
	 * Construction de la matrice
	 * ------------------------------------------------------------------ */

	/**
	 * Carte des emplacements réservés (motifs fixes, format, version).
	 *
	 * @param int $version Version.
	 * @param int $size    Côté de la matrice.
	 * @return array
	 */
	protected static function reserved_map( $version, $size ) {
		$map = array();
		for ( $r = 0; $r < $size; $r++ ) {
			$map[ $r ] = array_fill( 0, $size, false );
		}

		// Motifs de repérage et leurs séparateurs.
		foreach ( array( array( 0, 0 ), array( 0, $size - 7 ), array( $size - 7, 0 ) ) as $corner ) {
			for ( $r = -1; $r <= 7; $r++ ) {
				for ( $c = -1; $c <= 7; $c++ ) {
					$rr = $corner[0] + $r;
					$cc = $corner[1] + $c;
					if ( $rr >= 0 && $rr < $size && $cc >= 0 && $cc < $size ) {
						$map[ $rr ][ $cc ] = true;
					}
				}
			}
		}

		// Motifs d'alignement.
		foreach ( self::alignment_centers( $version, $size ) as $center ) {
			for ( $r = -2; $r <= 2; $r++ ) {
				for ( $c = -2; $c <= 2; $c++ ) {
					$map[ $center[0] + $r ][ $center[1] + $c ] = true;
				}
			}
		}

		// Motifs de synchronisation.
		for ( $i = 0; $i < $size; $i++ ) {
			$map[6][ $i ] = true;
			$map[ $i ][6] = true;
		}

		/*
		 * Zones d'information de format : la ligne 8 et la colonne 8 près du
		 * motif supérieur gauche, huit modules de la ligne 8 à droite et huit
		 * de la colonne 8 en bas (dont le module toujours noir).
		 */
		for ( $i = 0; $i < 9; $i++ ) {
			$map[8][ $i ] = true;
			$map[ $i ][8] = true;
		}
		for ( $i = 0; $i < 8; $i++ ) {
			$map[8][ $size - 1 - $i ] = true;
			$map[ $size - 1 - $i ][8] = true;
		}

		// Information de version (à partir de la version 7).
		if ( $version >= 7 ) {
			for ( $i = 0; $i < 6; $i++ ) {
				for ( $j = 0; $j < 3; $j++ ) {
					$map[ $i ][ $size - 11 + $j ] = true;
					$map[ $size - 11 + $j ][ $i ] = true;
				}
			}
		}

		return $map;
	}

	/**
	 * Centres effectifs des motifs d'alignement (hors motifs de repérage).
	 *
	 * @param int $version Version.
	 * @param int $size    Côté.
	 * @return array Couples ligne/colonne.
	 */
	protected static function alignment_centers( $version, $size ) {
		$positions = self::$alignment[ $version ];
		$out       = array();

		foreach ( $positions as $row ) {
			foreach ( $positions as $col ) {
				// Les trois coins portent déjà un motif de repérage.
				if ( ( 6 === $row && 6 === $col )
					|| ( 6 === $row && $col === $size - 7 )
					|| ( $row === $size - 7 && 6 === $col ) ) {
					continue;
				}
				$out[] = array( $row, $col );
			}
		}

		return $out;
	}

	/**
	 * Dessine motifs fixes, données masquées et informations de format.
	 *
	 * @param array $codewords Mots de code finaux.
	 * @param int   $version   Version.
	 * @param int   $size      Côté.
	 * @param array $reserved  Carte des emplacements réservés.
	 * @param int   $mask      Numéro de masque (0 à 7).
	 * @param bool  $test      Grille d'essai : format et version laissés blancs.
	 * @return array Matrice de booléens.
	 */
	protected static function place( $codewords, $version, $size, $reserved, $mask, $test = false ) {
		$grid = array();
		for ( $r = 0; $r < $size; $r++ ) {
			$grid[ $r ] = array_fill( 0, $size, false );
		}

		// Motifs de repérage.
		foreach ( array( array( 0, 0 ), array( 0, $size - 7 ), array( $size - 7, 0 ) ) as $corner ) {
			for ( $r = 0; $r < 7; $r++ ) {
				for ( $c = 0; $c < 7; $c++ ) {
					$dark = ( 0 === $r || 6 === $r || 0 === $c || 6 === $c )
						|| ( $r >= 2 && $r <= 4 && $c >= 2 && $c <= 4 );
					$grid[ $corner[0] + $r ][ $corner[1] + $c ] = $dark;
				}
			}
		}

		// Motifs d'alignement.
		foreach ( self::alignment_centers( $version, $size ) as $center ) {
			for ( $r = -2; $r <= 2; $r++ ) {
				for ( $c = -2; $c <= 2; $c++ ) {
					$dark = ( 2 === abs( $r ) || 2 === abs( $c ) || ( 0 === $r && 0 === $c ) );
					$grid[ $center[0] + $r ][ $center[1] + $c ] = $dark;
				}
			}
		}

		// Motifs de synchronisation.
		for ( $i = 8; $i < $size - 8; $i++ ) {
			$dark          = ( 0 === $i % 2 );
			$grid[6][ $i ] = $dark;
			$grid[ $i ][6] = $dark;
		}

		// Information de version.
		if ( $version >= 7 ) {
			$bits = self::version_bits( $version );
			for ( $i = 0; $i < 18; $i++ ) {
				$bit = ! $test && (bool) ( ( $bits >> $i ) & 1 );
				$r   = (int) floor( $i / 3 );
				$c   = $size - 11 + $i % 3;
				$grid[ $r ][ $c ] = $bit;
				$grid[ $c ][ $r ] = $bit;
			}
		}

		// Données, en zigzag depuis le coin inférieur droit.
		$bits  = '';
		foreach ( $codewords as $codeword ) {
			$bits .= str_pad( decbin( $codeword ), 8, '0', STR_PAD_LEFT );
		}

		$index     = 0;
		$total     = strlen( $bits );
		$direction = -1;
		$row       = $size - 1;

		for ( $col = $size - 1; $col > 0; $col -= 2 ) {
			// La colonne de synchronisation verticale est sautée.
			if ( 6 === $col ) {
				$col--;
			}

			while ( true ) {
				for ( $i = 0; $i < 2; $i++ ) {
					$c = $col - $i;
					if ( ! $reserved[ $row ][ $c ] ) {
						$bit = $index < $total ? ( '1' === $bits[ $index ] ) : false;
						$index++;
						if ( self::mask_at( $mask, $row, $c ) ) {
							$bit = ! $bit;
						}
						$grid[ $row ][ $c ] = $bit;
					}
				}

				$row += $direction;
				if ( $row < 0 || $row >= $size ) {
					$row      -= $direction;
					$direction = -$direction;
					break;
				}
			}
		}

		/*
		 * Information de format (niveau M + masque), écrite en double : une
		 * copie sur la colonne 8, une autre sur la ligne 8.
		 */
		$format = self::format_bits( $mask );
		for ( $i = 0; $i < 15; $i++ ) {
			$bit = ! $test && (bool) ( ( $format >> $i ) & 1 );

			// Colonne 8 : bits 0 à 7 en haut, bits 8 à 14 en bas.
			if ( $i < 6 ) {
				$grid[ $i ][8] = $bit;
			} elseif ( $i < 8 ) {
				$grid[ $i + 1 ][8] = $bit;
			} else {
				$grid[ $size - 15 + $i ][8] = $bit;
			}

			// Ligne 8 : bits 0 à 7 à droite, bits 8 à 14 à gauche.
			if ( $i < 8 ) {
				$grid[8][ $size - $i - 1 ] = $bit;
			} elseif ( 8 === $i ) {
				$grid[8][7] = $bit;
			} else {
				$grid[8][ 14 - $i ] = $bit;
			}
		}

		// Module toujours noir, posé après les bits de format.
		$grid[ $size - 8 ][8] = ! $test;

		return $grid;
	}

	/**
	 * Le masque assombrit-il ce module ?
	 *
	 * @param int $mask Numéro de masque.
	 * @param int $r    Ligne.
	 * @param int $c    Colonne.
	 * @return bool
	 */
	protected static function mask_at( $mask, $r, $c ) {
		switch ( $mask ) {
			case 0:
				return 0 === ( $r + $c ) % 2;
			case 1:
				return 0 === $r % 2;
			case 2:
				return 0 === $c % 3;
			case 3:
				return 0 === ( $r + $c ) % 3;
			case 4:
				return 0 === ( (int) floor( $r / 2 ) + (int) floor( $c / 3 ) ) % 2;
			case 5:
				return 0 === ( $r * $c ) % 2 + ( $r * $c ) % 3;
			case 6:
				return 0 === ( ( $r * $c ) % 2 + ( $r * $c ) % 3 ) % 2;
			case 7:
				return 0 === ( ( $r + $c ) % 2 + ( $r * $c ) % 3 ) % 2;
		}
		return false;
	}

	/**
	 * Quinze bits d'information de format, niveau M, avec leur code
	 * correcteur BCH et le masque réglementaire.
	 *
	 * @param int $mask Numéro de masque.
	 * @return int
	 */
	protected static function format_bits( $mask ) {
		// Niveau M = 00.
		$data = ( 0 << 3 ) | $mask;
		$rem  = $data;

		for ( $i = 0; $i < 10; $i++ ) {
			$rem <<= 1;
			if ( $rem & 0x400 ) {
				$rem ^= 0x537;
			}
		}

		return ( ( $data << 10 ) | $rem ) ^ 0x5412;
	}

	/**
	 * Dix-huit bits d'information de version (versions 7 et suivantes).
	 *
	 * @param int $version Version.
	 * @return int
	 */
	protected static function version_bits( $version ) {
		$rem = $version;

		for ( $i = 0; $i < 12; $i++ ) {
			$rem <<= 1;
			if ( $rem & 0x1000 ) {
				$rem ^= 0x1F25;
			}
		}

		return ( $version << 12 ) | $rem;
	}

	/* ---------------------------------------------------------------------
	 * Choix du masque
	 * ------------------------------------------------------------------ */

	/**
	 * Score de pénalité d'une matrice : plus il est bas, plus le code est
	 * lisible.
	 *
	 * @param array $grid Matrice.
	 * @param int   $size Côté.
	 * @return int
	 */
	protected static function penalty( $grid, $size ) {
		$score = 0.0;

		// Règle 1 : modules entourés de voisins de même couleur.
		for ( $row = 0; $row < $size; $row++ ) {
			for ( $col = 0; $col < $size; $col++ ) {
				$same = 0;
				$dark = $grid[ $row ][ $col ];

				for ( $r = -1; $r <= 1; $r++ ) {
					if ( $row + $r < 0 || $row + $r >= $size ) {
						continue;
					}
					for ( $c = -1; $c <= 1; $c++ ) {
						if ( $col + $c < 0 || $col + $c >= $size ) {
							continue;
						}
						if ( 0 === $r && 0 === $c ) {
							continue;
						}
						if ( $dark === $grid[ $row + $r ][ $col + $c ] ) {
							$same++;
						}
					}
				}

				if ( $same > 5 ) {
					$score += 3 + $same - 5;
				}
			}
		}

		// Règle 2 : carrés de deux modules sur deux de même couleur.
		for ( $row = 0; $row < $size - 1; $row++ ) {
			for ( $col = 0; $col < $size - 1; $col++ ) {
				$count = 0;
				if ( $grid[ $row ][ $col ] ) {
					$count++;
				}
				if ( $grid[ $row + 1 ][ $col ] ) {
					$count++;
				}
				if ( $grid[ $row ][ $col + 1 ] ) {
					$count++;
				}
				if ( $grid[ $row + 1 ][ $col + 1 ] ) {
					$count++;
				}
				if ( 0 === $count || 4 === $count ) {
					$score += 3;
				}
			}
		}

		// Règle 3 : motifs « noir, blanc, noir, noir, noir, blanc, noir »,
		// qui imitent les repères de position.
		for ( $row = 0; $row < $size; $row++ ) {
			for ( $col = 0; $col < $size - 6; $col++ ) {
				if ( $grid[ $row ][ $col ]
					&& ! $grid[ $row ][ $col + 1 ]
					&& $grid[ $row ][ $col + 2 ]
					&& $grid[ $row ][ $col + 3 ]
					&& $grid[ $row ][ $col + 4 ]
					&& ! $grid[ $row ][ $col + 5 ]
					&& $grid[ $row ][ $col + 6 ] ) {
					$score += 40;
				}
			}
		}

		for ( $col = 0; $col < $size; $col++ ) {
			for ( $row = 0; $row < $size - 6; $row++ ) {
				if ( $grid[ $row ][ $col ]
					&& ! $grid[ $row + 1 ][ $col ]
					&& $grid[ $row + 2 ][ $col ]
					&& $grid[ $row + 3 ][ $col ]
					&& $grid[ $row + 4 ][ $col ]
					&& ! $grid[ $row + 5 ][ $col ]
					&& $grid[ $row + 6 ][ $col ] ) {
					$score += 40;
				}
			}
		}

		// Règle 4 : déséquilibre entre modules noirs et blancs.
		$dark = 0;
		for ( $row = 0; $row < $size; $row++ ) {
			for ( $col = 0; $col < $size; $col++ ) {
				if ( $grid[ $row ][ $col ] ) {
					$dark++;
				}
			}
		}

		$ratio  = abs( 100 * $dark / $size / $size - 50 ) / 5;
		$score += $ratio * 10;

		return $score;
	}
}
