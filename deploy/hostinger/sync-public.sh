#!/usr/bin/env bash
# Sync Laravel public assets → Hostinger public_html (split-folder deploy).
# Run from the Laravel root:  cd ~/domains/pixflow.jinsta.com/pixflow && bash deploy/hostinger/sync-public.sh

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PUBLIC_HTML="$(cd "$ROOT/.." && pwd)/public_html"

if [[ ! -d "$PUBLIC_HTML" ]]; then
  echo "public_html not found at: $PUBLIC_HTML"
  echo "Expected: domains/your-site/public_html next to domains/your-site/pixflow"
  exit 1
fi

echo "Syncing $ROOT/public/ → $PUBLIC_HTML/"

rsync -av --delete \
  --exclude 'index.php' \
  --exclude 'storage' \
  "$ROOT/public/" "$PUBLIC_HTML/"

cp "$ROOT/deploy/hostinger/public_html/index.php" "$PUBLIC_HTML/index.php"

# Storage symlink in public_html (uploaded media URLs)
ln -sfn "$ROOT/storage/app/public" "$PUBLIC_HTML/storage"

echo "Done. Verify CSS size (~100 KB compiled Tailwind):"
ls -lh "$PUBLIC_HTML/css/pixflow.css"
