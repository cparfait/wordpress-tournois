<?php
/**
 * Construit les deux paquets de l'extension.
 *
 * - brackethive-<version>.zip
 *   Pour installer sur un site. Contient tout, module de mise à jour
 *   auto-hébergée et catalogues de traduction compris.
 *
 * - brackethive-<version>-POUR-WORDPRESS-ORG.zip
 *   Pour soumettre au répertoire officiel. Le module de mise à jour en est
 *   retiré, le règlement interdisant qu'une extension se mette à jour depuis
 *   une source externe ; les catalogues compilés aussi, le répertoire les
 *   générant lui-même depuis translate.wordpress.org.
 *
 * Le script met aussi à jour les manifestes : version, adresse de
 * téléchargement et empreinte du paquet. C'est cette synchronisation qui évite
 * d'annoncer aux sites une version dont l'archive n'existe pas encore.
 *
 * Usage : php tools/build-org-package.php
 *
 * @package Brackethive
 */

$root   = dirname( __DIR__ );
$source = $root . '/brackethive';
$build  = $root . '/build';

if ( ! is_dir( $source ) ) {
	fwrite( STDERR, "Dossier source introuvable : $source\n" );
	exit( 1 );
}

/**
 * Supprime un dossier et son contenu.
 *
 * @param string $dir Dossier.
 */
function brackethive_rmdir( $dir ) {
	if ( ! is_dir( $dir ) ) {
		return;
	}
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::CHILD_FIRST
	);
	foreach ( $items as $item ) {
		$item->isDir() ? rmdir( $item->getPathname() ) : unlink( $item->getPathname() );
	}
	rmdir( $dir );
}

/**
 * Recopie le dossier source en écartant ce qui ne doit pas être distribué,
 * puis uniformise les fins de ligne : un fichier mixte fausse la numérotation
 * et empêche l'analyseur de rattacher les annotations phpcs:ignore.
 *
 * @param string $source   Dossier source.
 * @param string $target   Dossier de destination.
 * @param array  $rules    Chemins relatifs exclus, fichier ou dossier.
 * @param array  $suffixes Terminaisons de nom exclues.
 * @return array Nombre de fichiers copiés, puis liste des exclusions.
 */
function brackethive_stage( $source, $target, $rules, $suffixes ) {
	brackethive_rmdir( $target );
	@mkdir( $target, 0777, true );

	$copied  = 0;
	$skipped = array();
	$items   = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);

	foreach ( $items as $item ) {
		$relative = str_replace( '\\', '/', substr( $item->getPathname(), strlen( $source ) + 1 ) );

		foreach ( $rules as $rule ) {
			if ( $relative === $rule || 0 === strpos( $relative, $rule . '/' ) ) {
				if ( $relative === $rule ) {
					$skipped[] = $rule;
				}
				continue 2;
			}
		}

		if ( $item->isFile() ) {
			foreach ( $suffixes as $suffix ) {
				if ( substr( $relative, -strlen( $suffix ) ) === $suffix ) {
					$skipped[] = $relative;
					continue 2;
				}
			}
		}

		if ( $item->isDir() ) {
			@mkdir( $target . '/' . $relative, 0777, true );
		} else {
			copy( $item->getPathname(), $target . '/' . $relative );
			$copied++;
		}
	}

	$text  = array( 'php', 'css', 'js', 'txt', 'json', 'html', 'md', 'pot', 'po' );
	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $target, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ( $items as $item ) {
		if ( $item->isDir() || ! in_array( strtolower( $item->getExtension() ), $text, true ) ) {
			continue;
		}
		$raw  = file_get_contents( $item->getPathname() );
		$norm = str_replace( array( "\r\n", "\r" ), "\n", $raw );
		if ( $norm !== $raw ) {
			file_put_contents( $item->getPathname(), $norm );
		}
	}

	return array( $copied, array_values( array_unique( $skipped ) ) );
}

/**
 * Archive un dossier préparé, sous un dossier racine « brackethive ».
 *
 * @param string $target   Dossier préparé.
 * @param string $zip_path Archive à produire.
 */
function brackethive_zip( $target, $zip_path ) {
	@unlink( $zip_path );

	$zip = new ZipArchive();
	if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
		fwrite( STDERR, "Impossible de créer l'archive : $zip_path\n" );
		exit( 1 );
	}

	/*
	 * Horodatage fixe. Sans lui, deux constructions du même code produisent
	 * des archives différentes, donc des empreintes différentes : republier
	 * le paquet invaliderait le sha256 annoncé par le manifeste et les sites
	 * refuseraient la mise à jour. La date n'a aucune autre portée.
	 */
	$mtime = mktime( 12, 0, 0, 1, 1, 2020 );
	$stamp = method_exists( $zip, 'setMtimeName' );

	$items = new RecursiveIteratorIterator(
		new RecursiveDirectoryIterator( $target, FilesystemIterator::SKIP_DOTS ),
		RecursiveIteratorIterator::SELF_FIRST
	);
	foreach ( $items as $item ) {
		$relative = 'brackethive/' . str_replace( '\\', '/', substr( $item->getPathname(), strlen( $target ) + 1 ) );

		if ( $item->isDir() ) {
			$zip->addEmptyDir( $relative );
			if ( $stamp ) {
				// Les dossiers sont enregistrés avec une barre oblique finale.
				$zip->setMtimeName( $relative . '/', $mtime );
			}
			continue;
		}

		$zip->addFile( $item->getPathname(), $relative );
		if ( $stamp ) {
			$zip->setMtimeName( $relative, $mtime );
		}
	}
	$zip->close();

	if ( ! $stamp ) {
		fwrite( STDERR, "Note : PHP 8.0+ est requis pour figer les horodatages ; l'empreinte changera à chaque construction.\n" );
	}
}

// Version lue dans l'en-tête du fichier principal.
preg_match( '/^ \* Version:\s*(.+)$/m', file_get_contents( $source . '/brackethive.php' ), $m );
$version = isset( $m[1] ) ? trim( $m[1] ) : '0.0.0';

// Hors production, dans les deux paquets.
$common = array(
	'_preview', // Maquettes de développement.
	'.claude',  // Réglages d'outillage.
);

// Paquet destiné à l'installation sur un site.
list( $copied_site ) = brackethive_stage( $source, $build . '/site/brackethive', $common, array() );
$zip_site            = $root . '/brackethive-' . $version . '.zip';
brackethive_zip( $build . '/site/brackethive', $zip_site );
$sha256 = hash_file( 'sha256', $zip_site );

// Paquet destiné au répertoire WordPress.org.
$org_rules = array_merge( $common, array( 'includes/class-brackethive-updater.php' ) );
$org_files = array( '.po', '.mo', '.l10n.php' );

list( $copied_org, $skipped_org ) = brackethive_stage( $source, $build . '/brackethive', $org_rules, $org_files );
$zip_org                          = $root . '/brackethive-' . $version . '-POUR-WORDPRESS-ORG.zip';
brackethive_zip( $build . '/brackethive', $zip_org );

// Manifestes de mise à jour.
$download = 'https://github.com/cparfait/wordpress-tournois/releases/download/v'
	. $version . '/brackethive-' . $version . '.zip';

$updated = array();
foreach ( array( $root . '/brackethive-manifest.json', $root . '/docs/manifest.json' ) as $file ) {
	if ( ! is_file( $file ) ) {
		continue;
	}
	$data = json_decode( file_get_contents( $file ), true );
	if ( ! is_array( $data ) ) {
		fwrite( STDERR, "Manifeste illisible : $file\n" );
		continue;
	}
	$data['version']      = $version;
	$data['download_url'] = $download;
	$data['sha256']       = $sha256;
	file_put_contents( $file, json_encode( $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n" );
	$updated[] = basename( $file );
}

/**
 * Taille lisible d'un fichier.
 *
 * @param string $path Fichier.
 * @return string
 */
function brackethive_size( $path ) {
	return round( filesize( $path ) / 1024 ) . ' Ko';
}

echo "Version   : $version\n\n";

echo "Installation sur un site\n";
echo '  ' . basename( $zip_site ) . ' (' . brackethive_size( $zip_site ) . ")\n";
echo "  $copied_site fichiers, module de mise a jour et traductions compris\n\n";

echo "Soumission au repertoire WordPress.org\n";
echo '  ' . basename( $zip_org ) . ' (' . brackethive_size( $zip_org ) . ")\n";
echo "  $copied_org fichiers\n";
echo '  Retires : ' . implode( ', ', $skipped_org ) . "\n\n";

echo "Manifestes mis a jour : " . implode( ', ', $updated ) . "\n";
echo "  sha256 : $sha256\n";
echo "  URL    : $download\n\n";

echo "La release GitHub v$version doit exister et porter l'archive\n";
echo '  ' . basename( $zip_site ) . ", sans quoi les sites verront une mise a jour en erreur.\n";
