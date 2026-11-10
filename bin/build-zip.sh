#!/usr/bin/env sh
# Builds the installable plugin zip (no tests, no dev tooling, no node_modules).
# Usage: bin/build-zip.sh [output-dir]
set -eu

cd "$(dirname "$0")/.."
OUT="${1:-dist}"
STAGE="$(mktemp -d)"
NAME="golive-audit"

npm run build >/dev/null

mkdir -p "$STAGE/$NAME/assets" "$OUT"
cp golive-audit.php uninstall.php readme.txt "$STAGE/$NAME/"
cp -R src languages "$STAGE/$NAME/"
cp -R assets/build assets/src "$STAGE/$NAME/assets/"
find "$STAGE/$NAME/assets/src" \( -name '*.test.js' -o -name '*.test.jsx' \) -delete
rm -rf "$STAGE/$NAME/assets/src/test"

VERSION="$(sed -n 's/^ \* Version: *//p' golive-audit.php | head -n1)"
(cd "$STAGE" && zip -qr "$NAME-$VERSION.zip" "$NAME")
mv "$STAGE/$NAME-$VERSION.zip" "$OUT/"
rm -rf "$STAGE"
echo "Built $OUT/$NAME-$VERSION.zip"
