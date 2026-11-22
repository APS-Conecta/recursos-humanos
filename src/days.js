// How the screens write days, ranges and counts (the mockup's words). A day is the API's
// YYYY-MM-DD string; it becomes a local Date only to be read or picked.

export const MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
const MONTHS_SHORT = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sept', 'oct', 'nov', 'dic']
const WEEKDAYS = ['domingo', 'lunes', 'martes', 'miércoles', 'jueves', 'viernes', 'sábado']
const WEEKDAYS_SHORT = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb']

/** A YYYY-MM-DD day's year, month (1 for January) and date. */
const parts = (day) => day.split('-').map(Number)

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
export function longDay(day) {
	const [year, month, date] = parts(day)
	return `${date} ${MONTHS_SHORT[month - 1]} ${year}`
}

/** The day $count days after a YYYY-MM-DD day. */
export function addDays(day, count) {
	const date = asDate(day)
	date.setDate(date.getDate() + count)
	return asDay(date)
}

/** A day's weekday, 0 for Sunday. */
export const weekday = (day) => asDate(day).getDay()

/** A day in a list of days: lun 5 ene. */
export function shortDay(day) {
	const [, month, date] = parts(day)
	return `${WEEKDAYS_SHORT[weekday(day)]} ${date} ${MONTHS_SHORT[month - 1]}`
}

/** A day read out on its own: lunes 5 de enero de 2026. */
export function fullDay(day) {
	const [year, month, date] = parts(day)
	return `${WEEKDAYS[weekday(day)]} ${date} de ${MONTHS[month - 1]} de ${year}`
}

/** Days that follow each other, as one run each: lun 5 ene, 7–9 ene, 30 dic – 2 ene. */
export function runs(days) {
	const spans = []
	for (const day of days) {
		const last = spans.at(-1)
		if (last && addDays(last[1], 1) === day) {
			last[1] = day
		} else {
			spans.push([day, day])
		}
	}
	return spans.map(([first, last]) => {
		if (first === last) {
			return shortDay(first)
		}
		const [, monthA, dateA] = parts(first)
		const [, monthB, dateB] = parts(last)
		return monthA === monthB
			? `${dateA}–${dateB} ${MONTHS_SHORT[monthA - 1]}`
			: `${dateA} ${MONTHS_SHORT[monthA - 1]} – ${dateB} ${MONTHS_SHORT[monthB - 1]}`
	}).join(', ')
}

/** A quantity of days with a decimal comma: 2,5. */
export const number = (n) => String(Math.round(n * 10) / 10).replace('.', ',')

/** The words after a count of days: 1 → día hábil, 3 → días hábiles; or corridos. */
export function daysWord(n, unit) {
	const one = n === 1
	return unit === 'corridos'
		? (one ? 'día corrido' : 'días corridos')
		: (one ? 'día hábil' : 'días hábiles')
}
