#!/usr/bin/env bash
# build-app-bundle.sh — build the FULL-APP vendored release ZIP the one-file web installer downloads.
#
# Output: dist/tiger-<version>.zip  (+ .sha256)
#   The COMPLETE Tiger app — the webtigers/tiger skeleton (application/, public/, bin/, composer.json)
#   with vendor/ fully resolved and bundled — that unzips and runs with NO composer on the target. This
#   is the artifact WebTigers/tiger-install (tiger-install.php) fetches + checksum-verifies for a fresh
#   no-shell / cPanel install. See INSTALLER.md (§6, §12) and INSTALL.md.
#
#   DISTINCT from bin/build-release-zip.sh, which builds a vendor/-ONLY zip (tiger-core-vendored-*.zip)
#   for the in-place CORE UPDATER (Tiger_Update_Core). That one swaps vendor/ on an existing install;
#   THIS one is the whole app for a first install.
#
# OWNERSHIP — we ship what Composer resolves, UNTOUCHED. This build does NOT reach into vendored
#   packages to delete files (ARCHITECTURE §0: every file is owned by exactly one party). `--prefer-dist`
#   already gives each dependency its OWN dist footprint, filtered by that package's own
#   `.gitattributes export-ignore` (e.g. TigerZF drops its /documentation, /tests, /demos itself). If a
#   TigerZF subsystem (Pdf/Search/Gdata/Dojo/Tool/…) should not ship, that is a **TigerZF** decision made
#   in the TigerZF repo — never an ad-hoc prune here. So: no deny-list, no locale-trim in this script.
#
# Usage (CI, on a webtigers/tiger release):
#   # from Packagist (simplest):
#   VERSION=0.1.1-beta ./bin/build-app-bundle.sh
#
#   # from local checkouts (exact release commit, no Packagist-indexing race):
#   VERSION=0.1.1-beta SKELETON_PATH=/path/to/tiger CORE_PATH=/path/to/tiger-core ./bin/build-app-bundle.sh
#
# Env:
#   VERSION        (required) release tag -> the zip filename tiger-<version>.zip
#   SKELETON_PATH  (opt) a webtigers/tiger checkout to build from; else composer create-project (Packagist)
#   CORE_PATH      (opt) a webtigers/tiger-core checkout -> resolve tiger-core from it (needs SKELETON_PATH)
#   OUT            (default dist) output dir
set -euo pipefail

VERSION="${VERSION:?set VERSION (e.g. 0.1.1-beta)}"
VERSION="${VERSION#v}"
OUT="${OUT:-dist}"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT
APP="$WORK/app"

for t in composer zip; do command -v "$t" >/dev/null || { echo "!! '$t' required"; exit 1; }; done

# --- 1) Assemble the full app tree into $APP --------------------------------
if [ -n "${SKELETON_PATH:-}" ]; then
    SK="$(cd "$SKELETON_PATH" && pwd)"
    echo "Copying skeleton from ${SK} …"
    mkdir -p "$APP"
    if command -v rsync >/dev/null; then
        rsync -a --exclude='.git' --exclude='.github' --exclude='.idea' --exclude='vendor' \
              --exclude='node_modules' --exclude='var/cache/*' --exclude='var/log/*' \
              --exclude='application/configs/local.ini' "$SK"/ "$APP"/
    else
        cp -a "$SK"/. "$APP"/
        rm -rf "$APP/.git" "$APP/.github" "$APP/.idea" "$APP/vendor" "$APP/node_modules"
        rm -f  "$APP/application/configs/local.ini"
    fi
    # Resolve tiger-core from a local checkout (exact release commit; no Packagist-indexing race) —
    # same mechanism as build-release-zip.sh: a Composer path repo + dev stability, stable deps.
    if [ -n "${CORE_PATH:-}" ]; then
        CORE="$(cd "$CORE_PATH" && pwd)"
        echo "Pinning tiger-core to local path ${CORE} …"
        ( cd "$APP" \
          && composer config repositories.tigercore "{\"type\":\"path\",\"url\":\"${CORE}\",\"options\":{\"symlink\":false}}" \
          && composer config minimum-stability dev \
          && composer config prefer-stable true )
    fi
    echo "Installing dependencies (--no-dev, --prefer-dist, optimized autoloader) …"
    ( cd "$APP" && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --no-progress )
else
    echo "Creating project webtigers/tiger ${VERSION} from Packagist …"
    composer create-project "webtigers/tiger" "$APP" "$VERSION" \
        --no-dev --no-interaction --no-progress --prefer-dist --stability=beta
    ( cd "$APP" && composer dump-autoload --no-dev --optimize --no-interaction )
fi

# --- 2) Sanity: the vendored tree must carry tiger-core ---------------------
VER_FILE="$APP/vendor/webtigers/tiger-core/library/Tiger/Version.php"
[ -f "$VER_FILE" ] || { echo "!! vendored app has no tiger-core — aborting"; exit 1; }
CORE_VER="$(grep -oE "VERSION\s*=\s*'[^']+'" "$VER_FILE" | grep -oE "'[^']+'" | tr -d "'")"
echo "  resolved tiger-core: ${CORE_VER}"

# --- 3) Fresh-install hygiene (APP-OWNED cruft only; never touch vendor/) ----
# The bundle we're assembling is app-owned, so we may tidy ITS root: no VCS, no IDE files, no secrets,
# no stale runtime junk. We do NOT descend into vendor/ — those packages own their own footprint (§0).
rm -rf "$APP/.git" "$APP/.github" "$APP/.idea" "$APP/node_modules"
rm -f  "$APP/application/configs/local.ini"
[ -d "$APP/var" ] && find "$APP/var" -mindepth 1 -type f ! -name '.gitkeep' -delete 2>/dev/null || true

# Strip VCS metadata from vendored packages. A package's DIST never contains .git — it only appears
# when Composer falls back to a SOURCE install (e.g. a just-tagged version whose dist zipball isn't
# ready yet). Removing it makes the tree match the dist; it is NOT pruning a package's code (that's
# the ownership rule above), just deleting an install-mode artifact that bloats the bundle.
find "$APP/vendor" -type d -name '.git' -prune -exec rm -rf {} + 2>/dev/null || true

# --- 4) Zip the whole app (zip root = app root: application/ vendor/ public/ …) --
mkdir -p "$OUT"; ABS_OUT="$(cd "$OUT" && pwd)"
ZIP="${ABS_OUT}/tiger-${VERSION}.zip"
rm -f "$ZIP" "$ZIP.sha256"
( cd "$APP" && zip -qr "$ZIP" . -x '.*' )   # keeps public/.htaccess (not a root dotfile); drops root dotfiles

# --- 5) Checksum sidecar (same convention as build-release-zip.sh) ----------
sha256() { if command -v sha256sum >/dev/null; then sha256sum "$1" | cut -d' ' -f1; else shasum -a 256 "$1" | cut -d' ' -f1; fi; }
sha256 "$ZIP" > "$ZIP.sha256"

echo "Built $(basename "$ZIP") ($(du -h "$ZIP" | cut -f1))  sha256=$(cat "$ZIP.sha256")"
echo "Attach BOTH to the webtigers/tiger ${VERSION} release: tiger-${VERSION}.zip and tiger-${VERSION}.zip.sha256"
