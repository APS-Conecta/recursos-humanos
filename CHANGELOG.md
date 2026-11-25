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
- The first end-to-end Request. A person asks for Annual leave or Administrative days in «Nueva solicitud», which
  shows what stays Available and why a send would be refused: an incomplete record, honorarios, Annual leave before
  the first year of service, an overlap with another Request in flight, or more than Available in one of its years.
  Available is the Balance minus the Requests in flight; only registration will change the Balance. The assigned
  Jefatura decides «por delegación del Director/a» in «Por decidir», oldest first, with Available before and after;
  without a Jefatura the Director decides «como Director/a». A rejection needs a written reason, Annual leave too,
  by the establishment Rule version «Feriado legal: la jefatura puede rechazarlo con motivo». Each Request keeps its
  trail with each decision's capacity and Delegation, and the Rule versions it was counted with. Nextcloud
  notifications reach the decider when someone asks and the person when it is decided. «Mis solicitudes» lists the
  person's Requests with their sidebar; «Ajustes › Delegación» records the Director's Delegation. API v1:
  `GET requests`, `POST requests`, `GET requests/{id}`, `GET decisions`, `POST requests/{id}/approval`,
  `POST requests/{id}/rejection`, `GET` and `POST settings/delegation`; `GET requests/preview` takes an optional
  `leaveType`, which sets the unit, and answers the unit, the refusal reasons and Available; `GET me` adds the
  Jefatura role, Available on each Balance and who decides the person's Requests.
