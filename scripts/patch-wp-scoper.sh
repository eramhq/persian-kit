#!/usr/bin/env bash
# Workaround for veronalabs/wp-scoper <=1.2.5: the built-in exclude pattern
# `/ext\//i` is unanchored and matches "ext/" as a substring inside "Text/",
# dropping dependency directories named Text/ from the scoped output.
# Anchor it to a path-segment boundary. Remove once upstream ships the fix.
set -euo pipefail

CONFIG="$(cd "$(dirname "$0")/.." && pwd)/vendor/veronalabs/wp-scoper/src/Config/Config.php"

if [ ! -f "$CONFIG" ]; then
  exit 0
fi

php -r '
    $path = $argv[1];
    $src = file_get_contents($path);
    $old = "\x27/ext\\\\//i\x27";
    $new = "\x27/(^|\\\\/)ext\\\\//i\x27";
    if (strpos($src, $new) !== false) { exit(0); }
    if (strpos($src, $old) === false) {
        fwrite(STDERR, "patch-wp-scoper: pattern not found in {$path}\n");
        exit(1);
    }
    file_put_contents($path, str_replace($old, $new, $src));
    echo "Patched veronalabs/wp-scoper ext/ regex.\n";
' "$CONFIG"
