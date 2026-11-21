// How the screens write a person record's values (CONTEXT.md, the mockup).

/** The Regimes by their API value. */
export const REGIMES = {
	estatutoAps: 'Estatuto APS',
	honorarios: 'Honorarios / sin derecho',
}

/** A person's cargos on one line. */
export const cargoLine = (cargos) => cargos.join(' · ')

/** 12345678-5 as people write it: 12.345.678-5. */
export function dottedRut(rut) {
	const [body, verifier] = rut.split('-')
	return `${body.replace(/\B(?=(\d{3})+(?!\d))/g, '.')}-${verifier}`
}

/** A YYYY-MM-DD day as a local Date, for the date pickers; null for none. */
export const asDate = (day) => (day ? new Date(`${day}T00:00`) : null)

/** A picked Date as its YYYY-MM-DD day; '' for none. */
export function asDay(date) {
	if (!(date instanceof Date) || Number.isNaN(date.getTime())) {
		return ''
	}
	const pad = (n) => String(n).padStart(2, '0')
	return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`
}

/** 2025-07-01 as the screens show a day: 1 jul 2025. */
export const longDay = (day) => asDate(day).toLocaleDateString('es-CL', { day: 'numeric', month: 'short', year: 'numeric' })

/** A Unix time as the trail shows it. */
export const stamp = (seconds) => new Date(seconds * 1000).toLocaleString('es-CL', { dateStyle: 'medium', timeStyle: 'short' })
