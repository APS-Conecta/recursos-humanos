# Changelog

All notable changes to `APS-Conecta/recursos-humanos` are recorded here.

Format: [Keep a Changelog 1.1.0](https://keepachangelog.com/en/1.1.0/).
Versioning: [Semantic Versioning 2.0.0](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Added

- «Recursos Humanos» in the suite menu, opening on «Mis días» and «Mis solicitudes», both empty for now.
- API v1 `GET me`: the caller's account and the roles their cargo groups give (HR officer, Director, Subdirector).
- «Personal» for HR officers: every account with its person record, keyed by RUT, «Falta completar» until HR fills
  «Completar ficha»; «Cambiar régimen» adds a dated Regime line with its reason. API v1 `GET people`,
  `GET people/{rut}`, `POST people` and `POST people/{rut}/regimes`.
- The national Public holiday list for 2026 and 2027, shipped with the app. API v1 `GET holidays?year=`.
- «Nueva solicitud»: a range calendar with weekends and Public holidays marked, and the live count of días hábiles.
  API v1 `GET requests/preview`: the days counted, the days skipped with their reason and holiday name, and the days
  per year, in días hábiles or días corridos.
