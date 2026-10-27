#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

SDK=php

# >>> version-files (S3; must match versionFiles("php") in ci/sdk-mirror/set-version.mjs)
VERSION_FILES=(
	legacy/composer.json
)
# <<< version-files

fail() { echo "::error::$SDK: $1" >&2; exit 1; }
check() { local name=$1; shift; "$@" || fail "$name"; }

T=$(mktemp -d)
trap 'rm -rf "$T"' EXIT

# Prints "<file> <field> <value>" per version field.
read_versions() {
	local f
	for f in "${VERSION_FILES[@]}"; do
		case $f in
			legacy/composer.json) php -r '$j = json_decode(file_get_contents($argv[1]), true, 512, JSON_THROW_ON_ERROR); echo $argv[1], " require[signalhouse-sms/sdk] ", $j["require"]["signalhouse-sms/sdk"] ?? "<missing>", "\n";' "$f" ;;
			*) fail "version-files: no reader for $f" ;;
		esac
	done
}

check_versions() {
	local lines want
	lines=$(read_versions) || return 1
	want=${EXPECTED_VERSION:-$(head -1 <<<"$lines" | awk '{print $NF}')}
	echo "$lines" | awk -v want="$want" '$NF != want { print "  " $0 " != " want; bad=1 } END { exit bad }'
}

check_legacy_readme() {
	cmp -s LEGACY_README.md <(cat LEGACY_NOTICE.md README.md)
}

check_lint() {
	local f
	while IFS= read -r f; do
		php -l "$f" >/dev/null || return 1
	done < <(find src -name '*.php' | sort)
}

check_tests() {
	local t
	for t in tests/*.php; do
		php "$t" || { echo "  $t" >&2; return 1; }
	done
}

# Asserts an archive listing holds only src/, composer.json and README.md.
assert_dist_listing() {
	local bad
	bad=$(grep -vE '^(src/.*|src/?|composer\.json|README\.md)$' "$1" || true)
	[ -z "$bad" ] || { echo "  unexpected $2 entries:" >&2; echo "$bad" >&2; return 1; }
	grep -qx composer.json "$1" && grep -qx README.md "$1" && grep -q '^src/' "$1" || { echo "  $2 missing src/, composer.json or README.md" >&2; return 1; }
}

# Checks composer archive and git archive (what GitHub/Packagist serve), both of which honour .gitattributes.
check_archive() {
	local src=$T/src
	mkdir -p "$src"
	tar cf - --exclude ./vendor --exclude ./composer.lock . | tar xf - -C "$src" || return 1
	(cd "$src" && composer archive --format=zip --dir="$T" --file=dist -q) || return 1
	unzip -Z1 "$T/dist.zip" | sort >"$T/composer.list"
	assert_dist_listing "$T/composer.list" "composer archive" || return 1
	(cd "$src" && git init -q && git add -A && git archive --format=tar "$(git write-tree)") | tar tf - | sort >"$T/git.list" || return 1
	assert_dist_listing "$T/git.list" "git archive" || return 1
}

check_smoke() {
	php -r 'require "vendor/autoload.php"; new SignalHouse\SDK\SignalHouseSDK(apiKey: "x", baseUrl: "http://127.0.0.1:9");'
}

echo "== $SDK: versions"; check versions check_versions
echo "== $SDK: legacy readme"; check legacy-readme check_legacy_readme
echo "== $SDK: composer validate"; check "composer validate" composer validate --strict --no-interaction
# The shim pins signalhouse-sms/sdk exactly by design; --no-check-all allows that one warning.
check "composer validate legacy" composer validate --strict --no-check-all --no-interaction --working-dir=legacy
echo "== $SDK: composer install"; check "composer install" composer install --no-interaction --prefer-dist --no-progress -q
echo "== $SDK: php -l"; check "php -l" check_lint
echo "== $SDK: tests"; check tests check_tests
echo "== $SDK: archive"; check archive check_archive
echo "== $SDK: smoke"; check smoke check_smoke
echo "gate passed: $SDK"
