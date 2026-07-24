#!/usr/bin/env bash
# Sync Pentagon-UI-online static files into public/ while preserving PHP backend.
#
# Usage:
#   ./scripts/sync-pentagon-ui.sh
#   ./scripts/sync-pentagon-ui.sh /path/to/Pentagon-UI-online
#
# Requires: git, rsync

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SOURCE="${1:-}"

if [[ -z "$SOURCE" ]]; then
  CACHE="/tmp/pentagon-ui-online"
  if [[ ! -d "$CACHE/.git" ]]; then
    echo "Cloning Pentagon-UI-online…"
    git clone --depth 1 https://github.com/dazuuuu/Pentagon-UI-online.git "$CACHE"
  else
    echo "Updating Pentagon-UI-online clone…"
    git -C "$CACHE" pull --ff-only origin main 2>/dev/null || git -C "$CACHE" pull --ff-only 2>/dev/null || true
  fi
  SOURCE="$CACHE"
fi

if [[ ! -d "$SOURCE" ]]; then
  echo "Source not found: $SOURCE" >&2
  exit 1
fi

PUBLIC="$ROOT/public"
echo "Syncing UI from $SOURCE → $PUBLIC"

# Preserve backend folders — never overwrite these with static mirror files.
RSYNC_EXCLUDES=(
  --exclude='api/'
  --exclude='admin/'
  --exclude='auth/'
  --exclude='students/'
  --exclude='tutor/'
  --exclude='store/'
  --exclude='courses/'
  --exclude='about/'
  --exclude='contact/'
  --exclude='blogs/'
  --exclude='projects/'
  --exclude='gallery/'
  --exclude='feedback/'
  --exclude='apply/'
  --exclude='templates/'
  --exclude='components/'
  --exclude='assets/'
  --exclude='uploads/'
  --exclude='.htaccess'
  --exclude='index.php'
  --exclude='sw.js'
  --exclude='favicon.svg'
)

rsync -a --delete "${RSYNC_EXCLUDES[@]}" "$SOURCE/" "$PUBLIC/"

# Copy our integration JS (overwrite pentagon stubs)
cp "$ROOT/public/js/pentagon-bridge.js" "$PUBLIC/js/pentagon-bridge.js"
cp "$ROOT/public/js/api.js" "$PUBLIC/js/api.js"
cp "$ROOT/public/js/app.js" "$PUBLIC/js/app.js"

echo "Injecting backend bridge into HTML pages…"
php "$ROOT/scripts/inject-pentagon-bridge.php" "$PUBLIC"

echo ""
echo "Done. Pentagon UI synced. Backend API preserved at public/api/"
echo "Next: configure .env, import database, point cPanel docroot to public/"
