# Local dev and test for «Recursos Humanos»: «IA»'s harness, which is Estadística's (README, «Desarrollo»).
#
#   `make test`       the static gates in a bare PHP container, no Nextcloud.
#   `make instance-*` a throwaway Nextcloud 34 + PostgreSQL 18 (compose.dev.yaml), on 8094.
#   `make test-seam`  the one seam (recursos-humanos#1): the app's OCS API and occ against that
#                     instance, as a person or an operator sees it.
#   `make aps-sync`   copy aps-common (a sibling checkout at ../aps-common) into its committed homes.

IMG := recursos-humanos-test
APS ?= $(CURDIR)/../aps-common
DOCKER := docker run --rm -v "$(CURDIR)":/app -v "$(APS)":/aps-common -w /app $(IMG)
COMPOSE := docker compose -f compose.dev.yaml
OCC := $(COMPOSE) exec --user www-data nextcloud php occ

.PHONY: test bundles build-test-image deps instance-up instance-occ instance-down instance-clean test-seam openapi aps-sync aps-drift clean

build-test-image:
	docker build -q -f Dockerfile.test -t $(IMG) . >/dev/null

# Vendor lands through the container, so no host PHP is needed. composer.json's path repository
# reads aps-common from the sibling mounted at /aps-common.
deps: build-test-image
	$(DOCKER) composer install --no-interaction --no-progress

test: build-test-image
	$(DOCKER) sh -c "composer install --no-interaction --no-progress -q && vendor/bin/phpunit --configuration phpunit.xml --testdox"

# js/ is built, not committed: the release builds it before packing. This asserts the bundle
# and its licence file exist, the failure pack-app itself cannot see.
BUNDLES := main.js main.js.LICENSE.txt

bundles:
	npm run build
	@for f in $(BUNDLES:%=js/recursos_humanos-%); do \
		test -s "$$f" || { echo "::error::npm run build produced no $$f"; exit 1; }; \
	done

# The cargo groups the suite's Provisionador creates (gestion-aio teams.py), so roles read here
# as they do on the suite. The enable runs the app's migrations as a clinic's occ app:enable does.
GROUPS := role-recursos-humanos role-director-cesfam role-subdirector-jefe-tecnico

instance-up:
	$(COMPOSE) up -d --wait
	for g in $(GROUPS); do $(OCC) group:add $$g >/dev/null 2>&1 || true; done
	$(OCC) app:enable recursos_humanos
	# Restart AFTER enabling: the web workers cache the route collection and the enabled apps
	# in APCu, and occ cannot invalidate it (Estadística's Makefile, farmacia d4b7e43).
	$(COMPOSE) restart nextcloud
	$(COMPOSE) up -d --wait
	@echo "Instance ready at http://localhost:8094 (admin / admin)"

# Run any occ command:  make instance-occ CMD="app:list"
instance-occ:
	$(OCC) $(CMD)

instance-down:
	$(COMPOSE) down

# Wipe the instance including its database.
instance-clean:
	$(COMPOSE) down -v

test-seam:
	$(COMPOSE) exec --user www-data -w /var/www/html/custom_apps/recursos_humanos nextcloud \
		php vendor/bin/phpunit --configuration phpunit.seam.xml --testdox

# Regenerate openapi.json from ApiController's attributes and docblocks.
openapi:
	scripts/openapi.sh

# Copy the aps-common package into its committed homes: vendor/aps/common/src (PHP), the stub its
# trait needs under tests/stubs/, and the JS half under src/aps/ (aps-common ADR 0001). The old
# copies go first, so a file the package dropped is dropped here too.
aps-sync:
	@test -d "$(APS)/src" || { echo "aps-common not found at $(APS): clone the sibling first"; exit 1; }
	rm -rf vendor/aps/common/src src/aps
	mkdir -p vendor/aps/common tests/stubs/OC/Hooks src/aps
	git -C "$(APS)" archive HEAD src | tar -x -C vendor/aps/common
	cp "$(APS)/stub/OC/Hooks/Emitter.php" tests/stubs/OC/Hooks/Emitter.php
	git -C "$(APS)" archive HEAD js | tar -x -C src/aps --strip-components=1
	rm -f src/aps/*.test.js

# The committed copies must equal the package. `git status --porcelain` (not diff --exit-code), so
# a rename that deletes one file and adds another is seen.
aps-drift: aps-sync
	@drift="$$(git status --porcelain -- vendor/aps/common/src src/aps tests/stubs/OC/Hooks/Emitter.php)"; \
	if [ -n "$$drift" ]; then \
		printf '%s\n' "$$drift"; \
		echo "::error::aps-common artifacts are behind the package. Run 'make aps-sync' and commit the result."; \
		exit 1; \
	fi

clean:
	docker rmi $(IMG) 2>/dev/null || true
