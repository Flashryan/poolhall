#!/usr/bin/env bash
#
# Build the two deployable artifacts for staging/production:
#
#   wordpress/dist/poolhall-integration.zip   (plugin)
#   wordpress/dist/hello-elementor-child.zip  (child theme)
#
# Both zips unzip to their correct wp-content folder names, so they work
# with the WordPress admin uploader, hPanel file manager and the Hostinger
# API deploy endpoints alike. The plugin ships without vendor/ — it has no
# production Composer dependencies and falls back to its own PSR-4
# autoloader. Tests, lint config and dev fixtures stay out of the artifact;
# scripts/dev is included because staging setup runs those via WP-CLI.
#
# Usage:  bash wordpress/scripts/build-deploy-zips.sh

set -euo pipefail

WP_SRC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DIST_DIR="$WP_SRC_DIR/dist"
STAGE_DIR="$(mktemp -d)"
trap 'rm -rf "$STAGE_DIR"' EXIT

mkdir -p "$DIST_DIR"
rm -f "$DIST_DIR/poolhall-integration.zip" "$DIST_DIR/hello-elementor-child.zip"

echo "== poolhall-integration.zip"
mkdir -p "$STAGE_DIR/poolhall-integration"
cp "$WP_SRC_DIR/plugins/poolhall-integration/poolhall-integration.php" "$STAGE_DIR/poolhall-integration/"
cp -R "$WP_SRC_DIR/plugins/poolhall-integration/src" "$STAGE_DIR/poolhall-integration/src"
cp -R "$WP_SRC_DIR/plugins/poolhall-integration/scripts" "$STAGE_DIR/poolhall-integration/scripts"
# Bundled client photography the setup scripts sideload into the media library.
cp -R "$WP_SRC_DIR/plugins/poolhall-integration/assets" "$STAGE_DIR/poolhall-integration/assets"
( cd "$STAGE_DIR" && zip -qr "$DIST_DIR/poolhall-integration.zip" poolhall-integration )

echo "== hello-elementor-child.zip"
cp -R "$WP_SRC_DIR/themes/hello-elementor-child" "$STAGE_DIR/hello-elementor-child"
( cd "$STAGE_DIR" && zip -qr "$DIST_DIR/hello-elementor-child.zip" hello-elementor-child )

ls -lh "$DIST_DIR"
echo "Done."
