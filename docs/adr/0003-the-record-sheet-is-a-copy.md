<!--
  - SPDX-FileCopyrightText: 2026 APS Conecta
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# 3. The Record sheet is a copy the app writes, never a place to edit

Accepted, 2026-11-19. Follows farmacia's ADR-0001 (the planilla is a projection, not the data).

After each change the app rewrites an Excel copy of every Balance, Request, Movement and the totals in a folder only the
HR officers and the Overview groups can open, so people can review and print it and still have it when the app is down.
Changes are made in the app; a corrected file can only come back through the checked upload, which previews every
change before saving.

## Considered Options

- Two-way: edit the sheet and the app takes it in. Two places to edit would disagree. Rejected.
- A download button only: no fallback when the app is down. Rejected.
