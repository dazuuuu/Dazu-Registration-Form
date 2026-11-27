#!/usr/bin/env bash
# Deploys this project to Hostinger over SSH/rsync.
#   local  public/  ->  remote  $REMOTE_BASE/public_html/   (the web root Hostinger serves)
#   local  app/     ->  remote  $REMOTE_BASE/app/            (sibling of public_html, NOT web-accessible)
# This matches app/v1/config/paths.php, which already auto-detects DOCUMENT_ROOT = public_html on
# cPanel/Hostinger and falls back to the public/ subfolder locally — nothing in the code needs renaming.
#
# Setup (one time):
#   1. Add the public key at ~/.ssh/dazu_registration_deploy.pub to Hostinger (hPanel -> Advanced -> SSH Access).
#   2. Fill in ~/.ssh/config's "dazu-registration" Host block with the real HostName/Port/User.
#   3. Copy .deploy.conf.example to .deploy.conf and set REMOTE_BASE to the absolute path that CONTAINS
#      public_html and app as siblings (e.g. /home/u123456789/domains/yoursite.com).
#
# Usage: ./deploy.sh [--dry-run]

set -euo pipefail
cd "$(dirname "$0")"

CONF=".deploy.conf"
if [ ! -f "$CONF" ]; then
    echo "Missing $CONF — copy .deploy.conf.example to .deploy.conf and fill in REMOTE_BASE." >&2
    exit 1
fi
# shellcheck source=/dev/null
source "$CONF"
: "${REMOTE_HOST:?Set REMOTE_HOST in .deploy.conf (the ~/.ssh/config alias)}"
: "${REMOTE_BASE:?Set REMOTE_BASE in .deploy.conf (absolute path on the server)}"

RSYNC_OPTS=(-azc --delete)
if [ "${1:-}" = "--dry-run" ]; then
    RSYNC_OPTS+=(--dry-run)
    echo "(dry run — nothing will actually be uploaded)"
fi

echo "==> public/ -> ${REMOTE_HOST}:${REMOTE_BASE}/public_html/"
rsync "${RSYNC_OPTS[@]}" \
    --exclude '.DS_Store' \
    --exclude 'uploads/' \
    --exclude 'assets/images/branding/' \
    --exclude 'assets/images/logo/' \
    ./public/ "${REMOTE_HOST}:${REMOTE_BASE}/public_html/"

echo "==> app/ -> ${REMOTE_HOST}:${REMOTE_BASE}/app/"
rsync "${RSYNC_OPTS[@]}" \
    --exclude '.DS_Store' \
    --exclude 'v1/storage/' \
    --exclude 'v1/config/database.php' \
    --exclude 'v1/config/db_connect.php' \
    --exclude 'v1/config/env.php' \
    --exclude 'v1/config/mail.php' \
    --exclude 'v1/config/mpesa.php' \
    --exclude 'v1/config/app.php' \
    --exclude '.env' \
    --exclude '.env.*' \
    ./app/ "${REMOTE_HOST}:${REMOTE_BASE}/app/"

echo "Done."
