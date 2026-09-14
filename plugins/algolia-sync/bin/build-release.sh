#!/usr/bin/env bash
set -euo pipefail

# Replicates .github/workflows/release.yml locally (without GitHub upload).
# Requires: PHP 8.1+, Composer, WP-CLI.

PLUGIN_SLUG="${PLUGIN_SLUG:-algolia-sync}"
BUILD_FOLDER="${BUILD_FOLDER:-.build}"
DIST_FOLDER="${DIST_FOLDER:-dist}"

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PLUGIN_ROOT="$(cd "${SCRIPT_DIR}/.." && pwd)"
BUILD_DIR="${PLUGIN_ROOT}/${BUILD_FOLDER}"
DIST_DIR="${PLUGIN_ROOT}/${DIST_FOLDER}"

for cmd in composer wp; do
	if ! command -v "${cmd}" >/dev/null 2>&1; then
		echo "Error: ${cmd} is required but not found in PATH." >&2
		exit 1
	fi
done

echo "==> Preparing build directory: ${BUILD_DIR}"
rm -rf "${BUILD_DIR}"
mkdir -p "${BUILD_DIR}" "${DIST_DIR}"

echo "==> Copying plugin source"
rsync -a \
	--exclude='.git/' \
	--exclude='.build/' \
	--exclude='dist/' \
	--exclude='vendor/' \
	"${PLUGIN_ROOT}/" "${BUILD_DIR}/"

echo "==> Installing production Composer dependencies"
(
	cd "${BUILD_DIR}"
	composer install --no-dev --optimize-autoloader --no-interaction
)

echo "==> Ensuring WP-CLI dist-archive command"
if ! wp help dist-archive >/dev/null 2>&1; then
	wp package install wp-cli/dist-archive-command:^3.1
fi

echo "==> Building ${PLUGIN_SLUG} zip"
wp dist-archive "${BUILD_DIR}" "${DIST_DIR}" --plugin-dirname="${PLUGIN_SLUG}" --force

echo "==> Cleaning up build directory"
rm -rf "${BUILD_DIR}"

echo "==> Done. Release artifact:"
ls -1 "${DIST_DIR}/${PLUGIN_SLUG}"*.zip
