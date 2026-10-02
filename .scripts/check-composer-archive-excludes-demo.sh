#!/usr/bin/env bash
# Fail if a Composer package archive still contains /demo (demos must not ship to consumers).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

COMPOSER=(composer)
if ! command -v composer >/dev/null 2>&1; then
  if [[ -x "$ROOT/vendor/bin/composer" ]]; then
    COMPOSER=("$ROOT/vendor/bin/composer")
  elif command -v php >/dev/null 2>&1 && [[ -f "$ROOT/composer.phar" ]]; then
    COMPOSER=(php "$ROOT/composer.phar")
  else
    echo "check-composer-archive: composer not found; skipping (CI should have composer)."
    exit 0
  fi
fi

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "Building composer archive in $TMP"
"${COMPOSER[@]}" archive --format=zip --dir="$TMP" --file=pbk-dist

ARCHIVE="$(ls "$TMP"/pbk-dist*.zip 2>/dev/null | head -1 || true)"
if [[ -z "$ARCHIVE" || ! -f "$ARCHIVE" ]]; then
  echo "ERROR: composer archive did not produce a zip in $TMP"
  ls -la "$TMP" || true
  exit 1
fi

echo "Inspecting archive: $ARCHIVE"
if command -v unzip >/dev/null 2>&1; then
  LIST="$(unzip -Z1 "$ARCHIVE")"
elif command -v zipinfo >/dev/null 2>&1; then
  LIST="$(zipinfo -1 "$ARCHIVE")"
else
  # PHP fallback
  LIST="$(php -r '$z=new ZipArchive(); $z->open($argv[1]); for($i=0;$i<$z->numFiles;$i++) echo $z->getNameIndex($i), PHP_EOL;' "$ARCHIVE")"
fi

# Match the application demo/ tree only — not docs/images/demo/ screenshots.
FORBIDDEN="$(echo "$LIST" | grep -E '(^|/)demo/' | grep -v 'docs/images/demo/' || true)"
if [[ -n "$FORBIDDEN" ]]; then
  echo "ERROR: composer archive still contains demo/ — consumers must not receive demos."
  echo "$FORBIDDEN" | head -40
  exit 1
fi

if [[ -f .gitattributes ]] && ! grep -E '^/demo[[:space:]]+export-ignore' .gitattributes >/dev/null; then
  echo "ERROR: .gitattributes missing '/demo export-ignore'"
  exit 1
fi

if ! php -r '
$j = json_decode(file_get_contents("composer.json"), true);
$excl = $j["archive"]["exclude"] ?? [];
foreach ($excl as $x) {
    if (rtrim($x, "/") === "/demo" || $x === "demo") { exit(0); }
}
exit(1);
'; then
  echo "ERROR: composer.json archive.exclude must list /demo"
  exit 1
fi

echo "OK: composer archive excludes demo/ ($(basename "$ARCHIVE"))"
