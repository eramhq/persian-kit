<?php

/**
 * Marks the scoped copies of eram/abzar and eram/daynum in packages/ as
 * third-party code for PHPCS and Plugin Check.
 *
 * Both are plain PHP libraries that also run outside WordPress, so their
 * exception messages can't use WordPress escaping. Persian Kit never prints
 * those messages. Runs after wp-scoper regenerates packages/ (see composer.json).
 */

$root = dirname(__DIR__) . '/packages/eram';
$marker = '// phpcs:ignoreFile -- Bundled third-party library (MIT), checked upstream; not WordPress code.';

if (!is_dir($root)) {
    fwrite(STDERR, "packages/eram not found; run composer install first.\n");
    exit(1);
}

$marked = 0;
$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

foreach ($files as $file) {
    if ($file->getExtension() !== 'php') {
        continue;
    }

    $path = $file->getPathname();
    $contents = (string) file_get_contents($path);

    if (str_contains($contents, $marker) || !str_starts_with($contents, '<?php')) {
        continue;
    }

    file_put_contents($path, preg_replace('/^<\?php\b/', "<?php\n\n" . $marker, $contents, 1));
    $marked++;
}

echo "Marked {$marked} bundled library file(s) for PHPCS.\n";
