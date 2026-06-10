#!/usr/bin/env bash
#
# Bootstrap a local WordPress dev environment for the Poolhall build.
# Designed for ephemeral containers (Claude Code on the web): WP core on
# SQLite, no MySQL, repo plugin + child theme symlinked in.
#
# Usage:  bash wordpress/scripts/setup-local-wp.sh [target-dir]
#
# Sources are GitHub mirrors so this also works when wordpress.org is not
# on the network allowlist (it is mirrored at WordPress/WordPress).

set -euo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WP_DIR="${1:-$HOME/wp-local}"
BIN_DIR="$HOME/bin"
WP_TAG="${WP_TAG:-7.0}"
ELEMENTOR_RELEASE_API="https://api.github.com/repos/elementor/elementor/releases?per_page=1"

mkdir -p "$WP_DIR" "$BIN_DIR"

echo "== WP-CLI"
if [ ! -x "$BIN_DIR/wp" ]; then
  curl -sL -o "$BIN_DIR/wp" https://raw.githubusercontent.com/wp-cli/builds/gh-pages/phar/wp-cli.phar
  chmod +x "$BIN_DIR/wp"
fi
WP="$BIN_DIR/wp --allow-root --path=$WP_DIR"

echo "== WordPress core $WP_TAG (GitHub mirror)"
if [ ! -f "$WP_DIR/wp-load.php" ]; then
  curl -sL -o /tmp/wp.tar.gz "https://codeload.github.com/WordPress/WordPress/tar.gz/refs/tags/$WP_TAG"
  tar -xzf /tmp/wp.tar.gz -C "$WP_DIR" --strip-components=1
fi

echo "== SQLite database integration"
if [ ! -d "$WP_DIR/wp-content/plugins/sqlite-database-integration" ]; then
  curl -sL -o /tmp/sqlite.tar.gz https://codeload.github.com/WordPress/sqlite-database-integration/tar.gz/refs/heads/main
  tar -xzf /tmp/sqlite.tar.gz -C "$WP_DIR/wp-content/plugins"
  mv "$WP_DIR/wp-content/plugins/sqlite-database-integration-"* "$WP_DIR/wp-content/plugins/sqlite-database-integration"
fi
cp -n "$WP_DIR/wp-content/plugins/sqlite-database-integration/db.copy" "$WP_DIR/wp-content/db.php" 2>/dev/null || true

echo "== wp-config + install"
if [ ! -f "$WP_DIR/wp-config.php" ]; then
  $WP config create --dbname=poolhall --dbuser=na --dbpass=na --skip-check
fi
if ! $WP core is-installed 2>/dev/null; then
  $WP core install --url=http://localhost:8080 --title="Poolhall Recruitment (local dev)" \
    --admin_user=dev --admin_password=localdev-only --admin_email=dev@example.test --skip-email
fi

echo "== Hello Elementor parent theme"
if [ ! -d "$WP_DIR/wp-content/themes/hello-elementor" ]; then
  curl -sL -o /tmp/hello.tar.gz https://codeload.github.com/elementor/hello-theme/tar.gz/refs/heads/main
  tar -xzf /tmp/hello.tar.gz -C "$WP_DIR/wp-content/themes"
  mv "$WP_DIR/wp-content/themes/hello-theme-main" "$WP_DIR/wp-content/themes/hello-elementor"
fi

echo "== Elementor (latest built zip from GitHub releases)"
if [ ! -d "$WP_DIR/wp-content/plugins/elementor" ]; then
  # Authenticated call avoids GitHub API rate limits; set GITHUB_TOKEN if hit.
  AUTH_HEADER=${GITHUB_TOKEN:+"Authorization: Bearer $GITHUB_TOKEN"}
  EL_URL=$(curl -s ${AUTH_HEADER:+-H "$AUTH_HEADER"} "$ELEMENTOR_RELEASE_API" | grep -o 'https://github.com/elementor/elementor/releases/download/[^"]*\.zip' | head -1 || true)
  if [ -n "$EL_URL" ]; then
    curl -sL -o /tmp/elementor.zip "$EL_URL"
    unzip -qo /tmp/elementor.zip -d "$WP_DIR/wp-content/plugins"
  else
    echo "   WARNING: could not resolve Elementor release (API rate limit?)."
    echo "   Set GITHUB_TOKEN and re-run, or unzip an Elementor zip into wp-content/plugins manually."
  fi
fi
# Elementor Pro is licensed and cannot be fetched: upload the zip and unzip
# it into wp-content/plugins/elementor-pro manually.

echo "== Symlink repo artifacts"
ln -sfn "$REPO_DIR/wordpress/plugins/poolhall-integration" "$WP_DIR/wp-content/plugins/poolhall-integration"
ln -sfn "$REPO_DIR/wordpress/themes/hello-elementor-child" "$WP_DIR/wp-content/themes/hello-elementor-child"

echo "== Activate"
$WP plugin activate elementor poolhall-integration 2>/dev/null || true
[ -d "$WP_DIR/wp-content/plugins/elementor-pro" ] && $WP plugin activate elementor-pro 2>/dev/null || true
$WP theme activate hello-elementor-child
$WP rewrite structure '/%postname%/' --hard >/dev/null

echo "== Plugin dev dependencies + tests"
( cd "$REPO_DIR/wordpress/plugins/poolhall-integration" && composer install --quiet && ./vendor/bin/phpunit --no-progress 2>&1 | tail -1 )

echo "== Integration verification"
$WP eval-file "$REPO_DIR/wordpress/plugins/poolhall-integration/scripts/dev/verify-sync.php" 2>/dev/null | grep "checks passed"

echo
echo "Done. Serve with:  (cd $WP_DIR && php -S 127.0.0.1:8080)"
echo "Admin: http://localhost:8080/wp-admin  (dev / localdev-only)"
