#!/usr/bin/env bash
# Compiles the bundled languages/persian-kit-<locale>.po files into the .mo,
# .l10n.php and script JSON files WordPress loads.
set -euo pipefail

cd "$(dirname "$0")/.."

for po in languages/persian-kit-*.po; do
    locale="${po#languages/persian-kit-}"
    locale="${locale%.po}"

    rm -f "languages/persian-kit-${locale}-"*.json
    wp i18n make-mo "$po" languages/
    wp i18n make-php "$po" languages/
    wp i18n make-json "$po" languages/ --no-purge
done

# make-json strips "min.js" with an unescaped dot, so public/js/admin.js is
# hashed as public/js/a.js. Re-key those files to the path
# load_script_textdomain() hashes.
php -r '
foreach (glob("languages/persian-kit-*-*.json") as $file) {
    $data = json_decode(file_get_contents($file), true);
    if (($data["source"] ?? "") !== "public/js/a.js" || !preg_match("/persian-kit-(.+)-[0-9a-f]{32}\.json$/", $file, $m)) {
        continue;
    }
    $data["source"] = "public/js/admin.js";
    $target = "languages/persian-kit-" . $m[1] . "-" . md5($data["source"]) . ".json";
    file_put_contents($target, json_encode($data, JSON_UNESCAPED_SLASHES));
    if ($target !== $file) {
        unlink($file);
    }
}
'
