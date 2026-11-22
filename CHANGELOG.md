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
- «Ajustes › Normativa»: the Rule versions the app applies, read-only, loaded from the file it ships on install and
  on every upgrade. API v1 `GET rules`.
- The Ledger: each Balance is the sum of its Movements. A record under the Estatuto APS receives the year's Annual
  leave (15, 20 or 25 días hábiles by Years of service, from more than one year of service, rising on the day a step
  is reached), Administrative days and Training, written as they fall due by an hourly job or
  `occ recursos_humanos:grants`. A mid-year joiner is «Falta completar» until HR sets their Administrative days.
- «Mis días» shows this year's Balances, Years of service and the next rise; the person sidebar gains «Movimientos».
  API v1: `GET me` and `GET people/{rut}` carry the record's Balances and Movements;
  `POST people/{rut}/administrative-days`.
