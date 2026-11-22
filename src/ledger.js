// How the screens write Leave types, Balances and Movements (CONTEXT.md, the mockup).

import { mdiBeach, mdiBriefcaseOutline, mdiSchoolOutline } from './icons.js'

/** The Leave types with a Balance, by their API value. */
export const LEAVE_TYPES = {
	annualLeave: { name: 'Feriado legal', icon: mdiBeach },
	administrativeDay: { name: 'Días administrativos', icon: mdiBriefcaseOutline },
	training: { name: 'Capacitación', icon: mdiSchoolOutline },
}

/** What each kind of Movement is called. */
export const MOVEMENT_KINDS = {
	grant: 'Concesión anual',
	serviceRise: 'Concesión por años de servicio',
}

/** Who wrote a Movement: its account, or the app on its own. */
export const author = (movement) => movement.actor?.name ?? 'Automático (la app)'

/** 179 → 14 años y 11 meses. */
export function serviceText(months) {
	const years = Math.floor(months / 12)
	const rest = months % 12
	const parts = []
	if (years > 0 || rest === 0) {
		parts.push(`${years} ${years === 1 ? 'año' : 'años'}`)
	}
	if (rest > 0) {
		parts.push(`${rest} ${rest === 1 ? 'mes' : 'meses'}`)
	}
	return parts.join(' y ')
}
