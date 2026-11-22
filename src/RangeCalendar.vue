<template>
	<div class="rh-calendar" role="group" aria-label="Calendario para elegir las fechas">
		<div class="rh-calendar__head">
			<NcButton variant="tertiary" aria-label="Mes anterior" :disabled="!canShow(-1)" @click="move(-1)">
				<template #icon>
					<NcIconSvgWrapper :path="mdiChevronLeft" />
				</template>
			</NcButton>
			<span>{{ title }}</span>
			<NcButton variant="tertiary" aria-label="Mes siguiente" :disabled="!canShow(1)" @click="move(1)">
				<template #icon>
					<NcIconSvgWrapper :path="mdiChevronRight" />
				</template>
			</NcButton>
		</div>
		<div class="rh-calendar__grid">
			<span v-for="(letter, index) in WEEK" :key="`h${index}`" class="rh-calendar__weekday" aria-hidden="true">{{ letter }}</span>
			<span v-for="blank in lead" :key="`b${blank}`" />
			<button v-for="cell in cells"
				:key="cell.day"
				type="button"
				:class="['rh-calendar__day', cell.classes]"
				:aria-label="cell.label"
				:aria-pressed="cell.end ? 'true' : undefined"
				:title="cell.holiday ?? undefined"
				:disabled="cell.disabled"
				@click="$emit('pick', cell.day)">
				{{ cell.date }}
			</button>
		</div>
		<p v-if="missing" class="rh-hint rh-calendar__note">
			{{ missing }}
		</p>
		<div class="rh-calendar__legend">
			<span><i class="rh-calendar__swatch rh-calendar__swatch--counted" />se cuenta</span>
			<span><i class="rh-calendar__swatch rh-calendar__swatch--skipped" />no se cuenta</span>
			<span><b class="rh-calendar__holiday-mark">13</b> día feriado</span>
		</div>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { ocsGet, refusal } from './api.js'
import { MONTHS, addDays, asDay, fullDay, weekday } from './days.js'
import { mdiChevronLeft, mdiChevronRight } from './icons.js'

const WEEK = ['L', 'M', 'M', 'J', 'V', 'S', 'D']

/**
 * The range calendar of «Nueva solicitud» (recursos-humanos#4, the mockup's picker): one month,
 * Monday first, with weekends and Public holidays marked and, inside the picked range, the days
 * the count skips. A year the app has no holiday list for cannot be picked.
 */
export default {
	name: 'RangeCalendar',
	components: { NcButton, NcIconSvgWrapper },
	props: {
		from: { type: String, default: '' },
		to: { type: String, default: '' },
		/** The days the preview counts in the range, or null while there is no count. */
		counted: { type: Array, default: null },
	},
	emits: ['pick'],
	setup: () => ({ WEEK, mdiChevronLeft, mdiChevronRight }),
	data() {
		const today = asDay(new Date())
		return {
			today,
			month: (this.from || today).slice(0, 7),
			/** Each year read so far: its holidays' names by date, or null when it has no list. */
			lists: {},
			/** Why a year has no list, by year. */
			reasons: {},
		}
	},
	computed: {
		year() {
			return Number(this.month.slice(0, 4))
		},
		title() {
			const name = MONTHS[Number(this.month.slice(5)) - 1]
			return `${name[0].toUpperCase()}${name.slice(1)} ${this.year}`
		},
		/** The empty cells before the 1st, Monday first. */
		lead() {
			return (weekday(`${this.month}-01`) + 6) % 7
		},
		missing() {
			return this.lists[this.year] === null ? this.reasons[this.year] : ''
		},
		cells() {
			const holidays = this.lists[this.year] ?? {}
			const counted = new Set(this.counted ?? [])
			const cells = []
			for (let day = `${this.month}-01`; day.startsWith(this.month); day = addDays(day, 1)) {
				const holiday = holidays[day] ?? null
				const weekend = [0, 6].includes(weekday(day))
				const inRange = this.from !== '' && this.to !== '' && day >= this.from && day <= this.to
				const skipped = inRange && this.counted !== null && !counted.has(day)
				const end = day === this.from || day === this.to
				const kind = holiday ? `, ${holiday}, día feriado` : weekend ? ', fin de semana' : ''
				const count = inRange && this.counted !== null ? (skipped ? ', no se cuenta' : ', se cuenta') : ''
				cells.push({
					day,
					date: Number(day.slice(8)),
					holiday,
					end,
					disabled: this.lists[this.year] === null,
					label: `${fullDay(day)}${kind}${count}`,
					classes: {
						'rh-calendar__day--weekend': weekend,
						'rh-calendar__day--holiday': holiday !== null,
						'rh-calendar__day--in': inRange,
						'rh-calendar__day--skipped': skipped,
						'rh-calendar__day--end': end,
						'rh-calendar__day--today': day === this.today,
					},
				})
			}
			return cells
		},
	},
	watch: {
		from(day) {
			if (day && !day.startsWith(this.month)) {
				this.month = day.slice(0, 7)
			}
		},
		month: {
			immediate: true,
			handler() {
				// The month's year, and the one a step away across New Year, so the arrows know.
				const month = Number(this.month.slice(5))
				this.read(this.year)
				if (month === 1) {
					this.read(this.year - 1)
				} else if (month === 12) {
					this.read(this.year + 1)
				}
			},
		},
	},
	methods: {
		async read(year) {
			if (year in this.lists) {
				return
			}
			this.lists[year] = undefined
			try {
				const { holidays } = await ocsGet('holidays', { year })
				this.lists[year] = Object.fromEntries(holidays.map(({ date, name }) => [date, name]))
			} catch (error) {
				this.reasons[year] = refusal(error) ?? `No se pudieron leer los días feriados de ${year}. Recarga la página.`
				this.lists[year] = null
			}
		},
		/** The month $step months away. */
		shifted(step) {
			const date = new Date(this.year, Number(this.month.slice(5)) - 1 + step, 1)
			return asDay(date).slice(0, 7)
		},
		canShow(step) {
			return this.lists[Number(this.shifted(step).slice(0, 4))] !== null
		},
		move(step) {
			this.month = this.shifted(step)
		},
	},
}
</script>

<style scoped>
.rh-calendar {
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
	padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 2) calc(var(--default-grid-baseline) * 2);
}

.rh-calendar__head {
	display: flex;
	align-items: center;
	justify-content: space-between;
	gap: var(--default-grid-baseline);
	font-weight: bold;
}

.rh-calendar__grid {
	display: grid;
	grid-template-columns: repeat(7, minmax(0, 1fr));
	row-gap: 2px;
}

.rh-calendar__weekday {
	padding-block: var(--default-grid-baseline);
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
	font-weight: bold;
	text-align: center;
}

.rh-calendar__grid .rh-calendar__day {
	height: var(--default-clickable-area);
	min-width: 0;
	min-height: 0;
	margin: 0;
	padding: 0;
	border: 0;
	border-radius: var(--border-radius-element);
	background: none;
	font-weight: normal;
	font-variant-numeric: tabular-nums;
	cursor: pointer;
}

/* Core styles every focused or hovered button; the grid's days keep their own marks, and hover
   marks only a day outside the range. */
.rh-calendar__grid .rh-calendar__day:enabled:not(.rh-calendar__day--in, .rh-calendar__day--end):hover {
	background: var(--color-background-hover);
}

.rh-calendar__grid .rh-calendar__day--weekend {
	color: var(--color-text-maxcontrast);
}

.rh-calendar__grid .rh-calendar__day--holiday {
	color: var(--color-text-error);
	font-weight: bold;
}

.rh-calendar__grid .rh-calendar__day--in {
	border-radius: 0;
	background: var(--color-primary-element-light);
	color: var(--color-primary-element-light-text);
}

.rh-calendar__grid .rh-calendar__day--in.rh-calendar__day--skipped,
.rh-calendar__swatch--skipped {
	background: repeating-linear-gradient(135deg, var(--color-background-dark) 0 4px, var(--color-main-background) 4px 8px);
	color: var(--color-text-maxcontrast);
	text-decoration: line-through;
}

.rh-calendar__grid .rh-calendar__day--in.rh-calendar__day--skipped.rh-calendar__day--holiday {
	color: var(--color-text-error);
}

.rh-calendar__grid .rh-calendar__day--end {
	border-radius: var(--border-radius-element);
	background: var(--color-primary-element);
	color: var(--color-primary-element-text);
}

.rh-calendar__grid .rh-calendar__day--today {
	box-shadow: inset 0 -3px 0 var(--color-primary-element);
}

.rh-calendar__grid .rh-calendar__day:disabled {
	background: none;
	color: var(--color-text-maxcontrast);
	text-decoration: none;
	cursor: not-allowed;
	opacity: .5;
}

.rh-calendar__note {
	margin-block: calc(var(--default-grid-baseline) * 2) 0;
}

.rh-calendar__legend {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 3);
	padding: calc(var(--default-grid-baseline) * 2) 2px 0;
	color: var(--color-text-maxcontrast);
	font-size: var(--font-size-small, 13px);
}

.rh-calendar__swatch {
	display: inline-block;
	width: 12px;
	height: 12px;
	margin-inline-end: var(--default-grid-baseline);
	border-radius: 3px;
	vertical-align: -2px;
}

.rh-calendar__swatch--counted {
	background: var(--color-primary-element-light);
}

.rh-calendar__holiday-mark {
	color: var(--color-text-error);
}
</style>
