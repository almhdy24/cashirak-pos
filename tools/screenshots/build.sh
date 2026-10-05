#!/usr/bin/env bash
# Regenerate every screenshot and showcase image in docs/.
#
# Everything runs on a throw-away copy of the project in a temp folder, so
# your real database, license and storage are never touched.
# Needs: php 8 (pdo_sqlite), node + playwright, ImageMagick (convert).
# SKIP_SHOWCASE=1 only refreshes the raw screenshots.
#
#   bash tools/screenshots/build.sh
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PORT="${PORT:-8765}"
WORK="$(mktemp -d)"
TOOLS="$WORK/tools/screenshots"
export BASE_URL="http://127.0.0.1:$PORT"
trap 'kill "${SERVER_PID:-}" 2>/dev/null || true; rm -rf "$WORK"' EXIT

echo "→ Copying project to $WORK"
(cd "$ROOT" && tar --exclude=.git --exclude=database --exclude=docs/screenshots --exclude=docs/showcase \
  --exclude='storage/*.lock' --exclude='storage/.license_data' --exclude='storage/.device_id' \
  --exclude='storage/sessions/sess_*' -cf - .) | tar -xf - -C "$WORK"

mkdir -p "$WORK"/storage/{logs,sessions,backups} "$WORK/database"

# Demo copy only: skip the license gate (activation is online + device-bound).
sed -i 's|^\\Core\\License::requireActive();|// screenshot build: license gate skipped|' "$WORK/bootstrap.php"

echo "→ Starting app on :$PORT"
(cd "$WORK" && CASHIRAK_DATA_PATH="$WORK" exec php -S "127.0.0.1:$PORT" -t public >/dev/null 2>&1) &
SERVER_PID=$!
sleep 1

echo "→ Setup wizard"
node "$TOOLS/capture.js" install
rm -rf "$WORK/storage/sessions/"sess_*

echo "→ Seeding demo data"
CASHIRAK_DATA_PATH="$WORK" php "$TOOLS/seed-demo.php"

echo "→ License screen"
node "$TOOLS/capture.js" license

# Show the settings page as a licensed install would (display only — not a real key).
cat > "$WORK/storage/.license_data" <<JSON
{"license_key":"DEMO-XXXX-XXXX-XXXX","status":"active","device_id":"$(cat "$WORK/storage/.device_id")","activated_at":$(date +%s),"last_seen_time":$(date +%s)}
JSON

echo "→ App screens"
node "$TOOLS/capture.js" app

if [ -z "${SKIP_SHOWCASE:-}" ]; then
  echo "→ Showcase images"
  node "$TOOLS/showcase.js"

  echo "→ WebP versions"
  mkdir -p "$WORK/docs/showcase/webp"
  for f in "$WORK"/docs/showcase/*.png; do
    convert "$f" -quality 86 "$WORK/docs/showcase/webp/$(basename "${f%.png}").webp"
  done
  rm -rf "$ROOT/docs/showcase"
fi

rm -rf "$ROOT/docs/screenshots"
mkdir -p "$ROOT/docs"
cp -r "$WORK/docs/." "$ROOT/docs/"
echo "✓ Done — see docs/screenshots and docs/showcase"
