<?php
/**
 * Construit le paquet destiné au répertoire WordPress.org.
 *
 * Le répertoire officiel interdit qu'une extension se mette à jour depuis une
 * source externe : le module de mise à jour auto-hébergée est donc retiré du
 * paquet. Le code qui l'utilise est conditionné à sa présence, il n'y a donc
 * rien d'autre à modifier.
 *
 * Usage : php tools/build-org-package.php
 * Produit : build/wegame-tournoi/ et build/wegame-tournoi-org-<version>.zip
 *
 * @package WeGameTournoi
 */

$root   = dirname( __DIR__ );
$source = $root . '/wegame-tournoi';
$build  = $root . '/build';
$target = $build . '/wegame-tournoi';

if ( ! is_dir( $source ) ) {
	fwrite( STDERR, "Dossier source introuvable : $source\n" );
	exit( 1 );
}

/**
 * Supprime un dossier et son contenu.
 *
 * @param string $dir Dossier.
 */
function wgt_rmdir( $dir ) {
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

// Fichiers et dossiers exclus du paquet.
$excluded = array(
	'_preview',                        // Maquettes de développement.
	'.claude',                         // Réglages d'outillage, hors production.
	'includes/class-wgt-updater.php',  // Interdit sur WordPress.org.
);

wgt_rmdir( $target );
@mkdir( $target, 0777, true );

$copied  = 0;
$skipped = array();
$items   = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $source, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ( $items as $item ) {
	$relative = str_replace( '\\', '/', substr( $item->getPathname(), strlen( $source ) + 1 ) );

	foreach ( $excluded as $rule ) {
		if ( $relative === $rule || 0 === strpos( $relative, $rule . '/' ) ) {
			if ( $relative === $rule ) {
				$skipped[] = $rule;
			}
			continue 2;
		}
	}

	if ( $item->isDir() ) {
		@mkdir( $target . '/' . $relative, 0777, true );
	} else {
		copy( $item->getPathname(), $target . '/' . $relative );
		$copied++;
	}
}

// load_plugin_textdomain() est superflu pour une extension hébergée sur
// WordPress.org : les traductions y sont chargées automatiquement.
$main = $target . '/wegame-tournoi.php';
$code = file_get_contents( $main );
$code = preg_replace(
	"/\t*load_plugin_textdomain\([^;]*\);\r?\n/",
	"\t// Les traductions sont chargées automatiquement par WordPress.org.\n",
	$code
);
file_put_contents( $main, $code );

// Fins de ligne uniformes : un fichier mixte fausse la numérotation des
// lignes et empêche l'analyseur de rattacher les annotations phpcs:ignore.
$text_ext = array( 'php', 'css', 'js', 'txt', 'json', 'html', 'md' );
$items    = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $target, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);
foreach ( $items as $item ) {
	if ( $item->isDir() || ! in_array( strtolower( $item->getExtension() ), $text_ext, true ) ) {
		continue;
	}
	$raw  = file_get_contents( $item->getPathname() );
	$norm = str_replace( array( "
", "" ), "
", $raw );
	if ( $norm !== $raw ) {
		file_put_contents( $item->getPathname(), $norm );
	}
}

// Version lue dans l'en-tête du fichier principal.
preg_match( '/^ \* Version:\s*(.+)$/m', $code, $m );
$version = isset( $m[1] ) ? trim( $m[1] ) : '0.0.0';

// Archive.
$zip_path = $root . '/wegame-tournoi-' . $version . '-POUR-WORDPRESS-ORG.zip';
@unlink( $zip_path );

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_path, ZipArchive::CREATE ) ) {
	fwrite( STDERR, "Impossible de créer l'archive.\n" );
	exit( 1 );
}

$items = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $target, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);
foreach ( $items as $item ) {
	$relative = 'wegame-tournoi/' . str_replace( '\\', '/', substr( $item->getPathname(), strlen( $target ) + 1 ) );
	$item->isDir() ? $zip->addEmptyDir( $relative ) : $zip->addFile( $item->getPathname(), $relative );
}
$zip->close();

echo "
";
echo "  Cette archive est destinee a WordPress.org : le module de mise a
";
echo "  jour auto-hebergee en a ete retire, comme l'exige le reglement.
";
echo "  Pour installer l'extension sur un site, utilisez plutot
";
echo "  wegame-tournoi-$version.zip

";
echo "Version    : $version\n";
echo "Fichiers   : $copied\n";
echo 'Retirés    : ' . implode( ', ', $skipped ) . "\n";
echo "Dossier    : $target\n";
echo 'Archive    : ' . $zip_path . ' (' . round( filesize( $zip_path ) / 1024 ) . " Ko)\n";
