<?php

/**
 * Finishes the scoped copies of eram/abzar and eram/daynum in packages/:
 * copies each library's LICENSE next to it (MIT requires the notice to travel
 * with the code) and marks the files as third-party code for PHPCS and Plugin Check.
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

foreach (['abzar', 'daynum'] as $package) {
    $license = dirname(__DIR__) . "/vendor/eram/{$package}/LICENSE";
    if (!is_file($license) || !is_dir("{$root}/{$package}") || !copy($license, "{$root}/{$package}/LICENSE")) {
        fwrite(STDERR, "Could not copy the LICENSE for eram/{$package}.\n");
        exit(1);
    }
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
