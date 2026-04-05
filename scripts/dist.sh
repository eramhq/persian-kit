#!/usr/bin/env bash
set -euo pipefail

PLUGIN_SLUG="persian-kit"
DIST_DIR="dist"
PROJECT_ROOT="$(cd "$(dirname "$0")/.." && pwd)"

cd "$PROJECT_ROOT"

# ── Check prerequisites ──────────────────────────────────────────────
missing=()
for cmd in wp composer npm rsync zip; do
    command -v "$cmd" &>/dev/null || missing+=("$cmd")
done

if [ ${#missing[@]} -gt 0 ]; then
    echo "Error: missing required commands: ${missing[*]}"
    exit 1
fi

echo "==> Installing Composer dependencies (full, for wp-scoper)..."
composer install

echo "==> Installing npm dependencies and building assets..."
npm ci
npm run build

echo "==> Generating POT file..."
npm run build:pot

echo "==> Preparing dist directory..."
rm -rf "$DIST_DIR"
mkdir -p "$DIST_DIR/$PLUGIN_SLUG"

echo "==> Copying files (respecting .distignore)..."
rsync -a \
    --exclude-from=".distignore" \
    ./ "$DIST_DIR/$PLUGIN_SLUG/"

echo "==> Creating zip archive..."
cd "$DIST_DIR"
zip -rq "$PLUGIN_SLUG.zip" "$PLUGIN_SLUG/"
cd "$PROJECT_ROOT"

ZIP_SIZE=$(du -h "$DIST_DIR/$PLUGIN_SLUG.zip" | cut -f1)
echo "==> Done! Created $DIST_DIR/$PLUGIN_SLUG.zip ($ZIP_SIZE)"
