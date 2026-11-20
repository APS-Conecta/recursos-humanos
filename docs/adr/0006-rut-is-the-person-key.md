<!--
  - SPDX-FileCopyrightText: 2026 APS Conecta
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

# 6. The RUT, with nombre y apellidos, is the person key

Accepted, 2026-11-19.

Nextcloud usernames can change, and payroll, outside systems and the Excel upload need a stable key. The HR officer
enters or confirms each person's RUT, checked by its verifier digit, together with their legal nombre y apellidos. The
RUT matches rows in the upload, appears on the Receipt and the Record sheet, and identifies the person in the API v1.

## Considered Options

- The Nextcloud username only: breaks matching when an account is renamed. Rejected.
- An internal number: stable, but no outside system knows it. Rejected.
