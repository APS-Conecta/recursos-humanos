// How the screens write a Request, its trail and its decider (CONTEXT.md, the mockup).

import { longDay } from './days.js'
import { LEAVE_TYPES } from './ledger.js'

/** The Leave types a person may ask for so far (recursos-humanos#6), as the type picker lists them. */
export const ASKED = ['annualLeave', 'administrativeDay']

/** Each state's chip: its words and NcChip variant. */
export const STATES = {
	pending: { name: 'Pendiente', variant: 'warning' },
	approved: { name: 'Aprobada', variant: 'primary' },
	rejected: { name: 'Rechazada', variant: 'error' },
}

/** How a decision reads, by its capacity: «Aprobó por delegación del Director/a». */
export const CAPACITIES = {
	delegation: 'por delegación del Director/a',
	director: 'como Director/a',
	external: 'fuera de la app',
}

const STEPS = { asked: 'Pidió', approved: 'Aprobó', rejected: 'Rechazó' }

/** A trail step's headline: «Aprobó por delegación del Director/a». */
export const stepText = (step) => [STEPS[step.kind], CAPACITIES[step.capacity]].filter(Boolean).join(' ')

/** The Delegation as approvals cite it: Resolución exenta N° 1234 del 5 ene 2026. */
export const delegationText = (delegation) => `Resolución exenta N° ${delegation.number} del ${longDay(delegation.date)}`

/** The range of a Request: 2 mar 2026, or 2 mar 2026 – 6 mar 2026. */
export const rangeText = (request) => (request.from === request.to ? longDay(request.from) : `${longDay(request.from)} – ${longDay(request.to)}`)

/** What a Request asks for, in a list: Feriado legal. */
export const typeName = (request) => LEAVE_TYPES[request.leaveType].name

/** The rejected step, with the reason the person reads; null for any other state. */
export const rejection = (request) => (request.state === 'rejected' ? [...request.trail].reverse().find((step) => step.kind === 'rejected') ?? null : null)

/**
 * Who decides the viewer's Requests, as the form's foot and «Mis días» say it (story 15).
 *
 * @param {object} decider the API's {capacity, accounts}
 */
export function deciderText(decider) {
	const names = decider.accounts.map((account) => account.name).join(' o ')
	if (decider.capacity === 'external') {
		return 'Como Director/a, tus solicitudes se deciden fuera de la app: Recursos Humanos las registra con el número y la fecha del documento.'
	}
	if (decider.accounts.length === 0) {
		return 'Nadie puede decidir tus solicitudes: no tienes jefatura directa y el establecimiento no tiene Director/a en la app. Avísale a Recursos Humanos.'
	}
	return decider.capacity === 'delegation'
		? `Tu jefatura directa, ${names}, decide tus solicitudes por delegación del Director/a. Después, Recursos Humanos las registra: recién ahí cambia el saldo.`
		: `Decide tus solicitudes el Director/a, ${names}. Después, Recursos Humanos las registra: recién ahí cambia el saldo.`
}

/**
 * Available in each year of a Request in flight, or of a preview, before it and after its days
 * come off (story 28): the API's Available leaves the Request itself out.
 *
 * @param {object} counted a Request or a preview: its {years, available}
 */
export function afterwards(counted) {
	const days = Object.fromEntries(counted.years.map((line) => [line.year, line.days]))
	return counted.available.map((line) => ({ year: line.year, before: line.available, after: line.available - (days[line.year] ?? 0) }))
}
