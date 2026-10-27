#!/usr/bin/env bash
set -euo pipefail
cd "$(dirname "$0")/.."

SDK=php
PACKAGE=signalhouse-sms/sdk
TRIES=${PUBLISH_TRIES:-20}
fail() { echo "::error::$SDK: $1" >&2; exit 1; }
run() { if [ "${PUBLISH_DRY_RUN:-}" = 1 ]; then printf '+ (dry run) would run:'; printf ' %q' "$@"; echo; else "$@"; fi; }

# The release is the tag; a dry run outside a tag falls back to the legacy pin.
tag_version() {
	local tags
	tags=$(git tag --points-at HEAD --list 'v*' 2>/dev/null || true)
	if [ "$(grep -c . <<<"$tags")" -eq 1 ]; then echo "${tags#v}"; return; fi
	[ -z "$tags" ] || fail "several v* tags on HEAD: $tags"
	[ "${PUBLISH_DRY_RUN:-}" = 1 ] || fail "HEAD has no v* tag"
	php -r 'echo json_decode(file_get_contents("legacy/composer.json"), true)["require"]["signalhouse-sms/sdk"], "\n";'
	echo "(dry run) no v* tag on HEAD; using the legacy/composer.json pin" >&2
}

# Exit 0 = Packagist lists the version, 1 = not yet; anything else fails.
published() {
	local body code
	body=$(curl -sS -w '\n%{http_code}' --retry 3 "https://repo.packagist.org/p2/$PACKAGE.json") || fail "packagist check $1"
	code=${body##*$'\n'}
	case $code in
		200) php -r '$j = json_decode($argv[1], true); foreach ($j["packages"][$argv[2]] ?? [] as $p) { if (in_array($p["version"] ?? "", [$argv[3], "v" . $argv[3]], true)) exit(0); } exit(1);' "${body%$'\n'*}" "$PACKAGE" "$1" ;;
		404) return 1 ;;
		*) fail "packagist check $1: HTTP $code" ;;
	esac
}

# Packagist picks the tag up from the GitHub hook; wait for it.
wait_for_packagist() {
	local i
	for ((i = 1; i <= TRIES; i++)); do
		published "$V" && return 0
		[ "$i" -lt "$TRIES" ] && { echo "packagist has no $V yet (try $i/$TRIES); retrying in 30s"; sleep 30; }
	done
	return 1
}

V=$(tag_version)
[ -n "$V" ] || fail "read version"
if published "$V"; then echo "$PACKAGE $V exists, skipping"; exit 0; fi
echo "polling https://repo.packagist.org/p2/$PACKAGE.json for $V (up to $TRIES tries, 30s apart)"
run wait_for_packagist || fail "packagist did not list $PACKAGE $V"
