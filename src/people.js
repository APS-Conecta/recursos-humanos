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

/** A Unix time as the trail shows it. */
export const stamp = (seconds) => new Date(seconds * 1000).toLocaleString('es-CL', { dateStyle: 'medium', timeStyle: 'short' })
