<!--
  - SPDX-FileCopyrightText: 2026 APS Conecta
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# 1. Balances are the sum of dated Movements, and closed years stay closed

Accepted, 2026-11-19 (grill for the «Recursos Humanos» app).

A Balance is never stored or typed over: it is the sum of the person's Movements for that Leave type and period. Every
grant, use, Cancellation, correction and Year close is a new dated Movement with who made it and why. This keeps the
official record complete and in order, and lets a Recount show exactly what would change. A Closed year receives no
more Movements: a later difference for it is written as one Movement in the current year («Corrección de 2025»).

## Considered Options

- An editable balance with a change log: simpler to show, but the log and the number can disagree. Rejected.
- Correcting inside the closed year and carrying the effect forward: exact, but it rewrites a year already closed and
  printed. Rejected.

## Consequences

- The Year close also writes the carry-over line, so a count only needs the current year's Movements.
- Corrections need only a reason from the HR officer; nobody is notified, the trail is the control.
