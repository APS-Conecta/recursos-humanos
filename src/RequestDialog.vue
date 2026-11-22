<template>
	<NcDialog name="Nueva solicitud" size="large" @closing="$emit('close')">
		<div class="rh-picker">
			<RangeCalendar :from="from" :to="to" :counted="count?.counted ?? null" @pick="pick" />
			<div class="rh-stack">
				<div class="rh-dates">
					<NcDateTimePickerNative id="rh-from"
						label="Desde"
						type="date"
						:model-value="asDate(from)"
						@update:model-value="from = asDay($event)" />
					<NcDateTimePickerNative id="rh-to"
						label="Hasta"
						type="date"
						:model-value="asDate(to)"
						:min="asDate(from)"
						@update:model-value="to = asDay($event)" />
				</div>
				<div class="rh-count" aria-live="polite">
					<p v-if="from === ''">
						Elige la fecha de inicio en el calendario o en «Desde».
					</p>
					<p v-else-if="to === ''">
						Ahora elige la fecha de término.
					</p>
					<p v-else-if="error" class="rh-error">
						{{ error }}
					</p>
					<template v-else-if="count">
						<p>
							<span class="rh-count__days rh-number">{{ number(count.days) }}</span>
							<strong>{{ daysWord(count.days, UNIT) }}</strong>
						</p>
						<p><strong>Se cuentan:</strong> {{ runs(count.counted) || 'ninguno' }}</p>
						<p v-if="skipped">
							<strong>No se cuentan:</strong> {{ skipped }}
						</p>
						<table v-if="count.years.length > 1" class="rh-years" aria-label="Días por año">
							<thead>
								<tr>
									<th>Año</th>
									<th class="rh-years__days">
										Días
									</th>
								</tr>
							</thead>
							<tbody>
								<tr v-for="line in count.years" :key="line.year">
									<td>{{ line.year }}</td>
									<td class="rh-years__days rh-number">
										{{ number(line.days) }}
									</td>
								</tr>
							</tbody>
						</table>
					</template>
				</div>
			</div>
		</div>
		<template #actions>
			<NcButton variant="primary" @click="$emit('close')">
				Cerrar
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import { ocsGet, refusal } from './api.js'
import { asDate, asDay, daysWord, number, runs, shortDay } from './days.js'
import RangeCalendar from './RangeCalendar.vue'

/** How the count runs until a Leave type sets it (recursos-humanos#6): Annual leave's días hábiles. */
const UNIT = 'habiles'

/**
 * «Nueva solicitud» (recursos-humanos#4): the range calendar and the live count from the preview
 * endpoint, which says which days count and which do not, and why. Sending it arrives with the
 * Leave types (recursos-humanos#6).
 */
export default {
	name: 'RequestDialog',
	components: { NcButton, NcDateTimePickerNative, NcDialog, RangeCalendar },
	emits: ['close'],
	setup: () => ({ UNIT, asDate, asDay, daysWord, number, runs }),
	data: () => ({
		from: '',
		to: '',
		/** The preview of the range, or null while it has none. */
		count: null,
		error: '',
		/** The latest preview asked for: an older answer that arrives late is dropped. */
		asked: 0,
	}),
	computed: {
		/** The skipped days as the count box writes them: each holiday by name, then the weekends. */
		skipped() {
			const holidays = this.count.skipped.filter((day) => day.name !== null)
				.map((day) => `${shortDay(day.date)}, ${day.name} (día feriado${day.reason === 'weekend' ? ', fin de semana' : ''})`)
			const weekends = this.count.skipped.filter((day) => day.name === null).map((day) => day.date)
			return [...holidays, ...(weekends.length > 0 ? [`fin de semana: ${runs(weekends)}`] : [])].join(' · ')
		},
	},
	watch: {
		from: 'recount',
		to: 'recount',
	},
	methods: {
		/** A day picked in the calendar starts a new range, or ends the one started. */
		pick(day) {
			if (this.from === '' || this.to !== '' || day < this.from) {
				this.from = day
				this.to = ''
			} else {
				this.to = day
			}
		},
		async recount() {
			const asked = ++this.asked
			this.error = ''
			if (this.from === '' || this.to === '') {
				this.count = null
				return
			}
			try {
				const count = await ocsGet('requests/preview', { from: this.from, to: this.to, unit: UNIT })
				if (asked === this.asked) {
					this.count = count
				}
			} catch (error) {
				if (asked === this.asked) {
					this.count = null
					this.error = refusal(error) ?? 'No se pudieron contar los días. Recarga la página.'
				}
			}
		},
	},
}
</script>

<style scoped>
.rh-picker {
	display: grid;
	grid-template-columns: minmax(0, 300px) minmax(0, 1fr);
	gap: calc(var(--default-grid-baseline) * 4);
	align-items: start;
	padding-block-end: calc(var(--default-grid-baseline) * 2);
}

.rh-stack {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
}

.rh-dates {
	display: grid;
	grid-template-columns: repeat(2, minmax(0, 1fr));
	gap: calc(var(--default-grid-baseline) * 3);
}

.rh-count {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 1.5);
	padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 3.5);
	border-radius: var(--border-radius-large);
	background: var(--color-primary-element-light);
}

.rh-count p {
	margin: 0;
}

.rh-count__days {
	margin-inline-end: var(--default-grid-baseline);
	color: var(--color-primary-element-light-text);
	font-size: 28px;
	font-weight: bold;
}

.rh-error {
	color: var(--color-text-error);
}

.rh-years {
	border-collapse: collapse;
}

.rh-years th,
.rh-years td {
	padding: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 3) var(--default-grid-baseline) 0;
	text-align: start;
}

.rh-years .rh-years__days {
	text-align: end;
}

@media (max-width: 700px) {
	.rh-picker {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
