#!/usr/bin/env bash
#
# Regenerates openapi.json from the attributes and docblocks on ApiController (Estadística's
# script, cloned): the contract is generated and committed, never hand-edited.
#
# The generator is a dev dependency, fetched into tools/ and gitignored there, and pinned because
# its output is CI's drift gate. The app keeps no PHP dependencies and no composer autoloader at
# runtime.
#
# Usage: scripts/openapi.sh     (override with RH_CONTAINER=…)
set -euo pipefail

CONTAINER="${RH_CONTAINER:-recursos-humanos-dev-nextcloud-1}"
APP=/var/www/html/custom_apps/recursos_humanos

# Root, not www-data: this writes tools/ and openapi.json in the repo bind mount, which the
# host owns.
run() { docker exec -w "$APP" -u root "$CONTAINER" "$@"; }

if ! run test -f tools/composer.phar; then
	printf 'Downloading Composer…\n'
	run mkdir -p tools
	run sh -c 'curl -sSL -o tools/composer.phar https://getcomposer.org/composer-stable.phar'
fi

if ! run test -f tools/openapi/vendor/bin/generate-spec; then
	printf 'Installing nextcloud/openapi-extractor…\n'
	run mkdir -p tools/openapi
	run sh -c 'cd tools/openapi && COMPOSER_HOME=/tmp/composer php ../composer.phar \
		require --quiet --no-interaction nextcloud/openapi-extractor:1.9.1'
fi

printf 'Generating openapi.json…\n'
run sh -c 'php tools/openapi/vendor/bin/generate-spec . openapi.json'

# The extractor reads a version out of composer.json, and this app has none: the version comes
# from info.xml, so regenerating re-applies it. MetadataTest fails if the two ever disagree.
run php -r '
	$spec = json_decode(file_get_contents("openapi.json"), true);
	$spec["info"]["version"] = (string)simplexml_load_file("appinfo/info.xml")->version;
	file_put_contents("openapi.json", json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL);
'
run php -r '$s = json_decode(file_get_contents("openapi.json"), true);
	printf("  %s v%s, %d routes\n", $s["info"]["title"], $s["info"]["version"], count($s["paths"]));'
