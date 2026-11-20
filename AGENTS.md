# AGENTS.md — «Recursos Humanos»

Nextcloud 34 app for APS Conecta Gestión: the establishment's official record of its staff's leave (release 1), then
of ratings (release 2) and career scores (release 3), under Ley 19.378. The spec is APS-Conecta/recursos-humanos#1;
its tickets live in this repo.

## Working rules

- **Names:** code is English, using the English terms `CONTEXT.md` gives (Request, Balance, Available, Movement,
  AnnualLeave, AdministrativeDay; Jefatura is kept). Never rename a table, column, route, controller action (an
  OpenAPI operation id) or API field once it ships: API v1 only grows, and a breaking change ships as v2 beside it.
- **The ledger is the record** (ADR 0001): a Balance is the sum of Movements, never stored or typed over; a Closed
  year receives no Movements. **Rules are data** (ADR 0002): the law's numbers live in dated Rule versions that
  arrive with app updates, never in code.
- **Privacy:** a Sick leave is dates only, and everyone but the person and HR sees «Ausente»; «IA» never reads this
  app's data (ADR 0004). No personal data in logs.
- **Tests at the one seam** (recursos-humanos#1, Testing Decisions): `tests/seam/` reaches the app only through its
  OCS API and occ, against the throwaway instance, never its classes. `tests/unit/` holds the static gates, nothing
  else.
- **Gate before opening a PR**, what `ci.yml` and `release.yml` run: `make aps-drift` (aps-common cloned beside this
  repo); `npm ci`; `npm run lint-js`; `make bundles`; `make test`; `make deps && make instance-up && make
  test-seam`; `make openapi` with no drift in `openapi.json`; then `make instance-clean`. A step you could not run is
  named in the PR, never reported as passed.
- **`src/` changed → `npm run build`**: `js/` is build output, ignored. Never commit `js/`, `*.js.map` or
  `node_modules/`.
- **Never edit by hand** `package-lock.json`, `composer.lock`, the dependency lists in `package.json` and
  `composer.json` outside a ticket that names the dependency, `openapi.json` (generated from `ApiController` by
  `make openapi`; MetadataTest and CI fail on drift), or aps-common's copies `vendor/aps/common/src/`, `src/aps/`
  and `tests/stubs/OC/Hooks/Emitter.php` (`make aps-sync`; `make aps-drift` fails on drift).
- **Strings:** what a person reads (templates, pages, messages) is Spanish, written as literals, in tú as the mockup
  writes it; this app uses no `t()` / `$l->t()`, like its siblings. Code, comments, commits and PR text are English.
- **Theme only:** `src/` reads Nextcloud's CSS variables and never writes a colour or a font; the suite's theme gives
  the APS palette, Fraunces and Nunito Sans (`eslint.config.mjs` enforces it).
- **One ticket per PR**, and it contains every change its description claims.

## Agent skills

### Issue tracker

GitHub Issues on `APS-Conecta/recursos-humanos`, via the `gh` CLI. See `docs/agents/issue-tracker.md`.

### Triage labels

Canonical vocabulary, unmodified: `needs-triage`, `needs-info`, `ready-for-agent`, `ready-for-human`, `wontfix`. All
five exist on the repo. See `docs/agents/triage-labels.md`.

### Domain docs

Single-context: [`CONTEXT.md`](CONTEXT.md) for the vocabulary, [`docs/adr/`](docs/adr/) for the decisions,
[`docs/research/aps-hr-law.md`](docs/research/aps-hr-law.md) for the legal facts and their sources. See
`docs/agents/domain.md`.
