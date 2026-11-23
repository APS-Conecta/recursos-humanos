<template>
	<NcDialog name="Nueva solicitud" size="large" @closing="$emit('close')">
		<NcSelect v-model="leaveType"
			class="rh-type"
			input-label="Tipo de permiso"
			:options="types"
			:reduce="(option) => option.id"
			:clearable="false" />
		<div class="rh-picker">
			<RangeCalendar :from="from"
				:to="to"
				:counted="count?.counted ?? null"
				:unit="count?.unit ?? 'habiles'"
				@pick="pick" />
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
							<strong>{{ daysWord(count.days, count.unit) }}</strong>
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
						<p v-for="line in afterwards(count)" :key="line.year" class="rh-number">
							<strong>Disponible {{ line.year }}:</strong> {{ number(line.before) }} → <span :class="{ 'rh-error': line.after < 0 }">{{ number(line.after) }}</span>
						</p>
					</template>
				</div>
				<p v-for="reason in count?.errors ?? []" :key="reason" class="rh-error">
					{{ reason }}
				</p>
				<p v-if="sendError" class="rh-error">
					{{ sendError }}
				</p>
				<NcTextArea v-model="comment" label="Comentario (opcional)" placeholder="Lo ve quien decide tu solicitud." />
				<p v-if="decider" class="rh-hint">
					{{ deciderText(decider) }}
				</p>
				<p class="rh-hint">
					Disponible: el saldo menos tus solicitudes en curso (pendientes o aprobadas sin registrar).
				</p>
			</div>
		</div>
		<template #actions>
			<NcButton variant="tertiary" @click="$emit('close')">
				Volver
			</NcButton>
			<NcButton variant="primary" :disabled="!sendable" @click="send">
				Enviar solicitud
			</NcButton>
		</template>
	</NcDialog>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcDialog from '@nextcloud/vue/components/NcDialog'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { ocsGet, ocsPost, refusal } from './api.js'
import { asDate, asDay, daysWord, number, runs, shortDay } from './days.js'
import { LEAVE_TYPES } from './ledger.js'
import RangeCalendar from './RangeCalendar.vue'
import { ASKED, afterwards, deciderText } from './requests.js'

/**
 * «Nueva solicitud» (recursos-humanos#4, #6): the Leave type, the range calendar and the live
 * count from the preview endpoint, which says which days count and which do not, and why, what
 * stays Available, and what would refuse the send; then the send.
 */
export default {
	name: 'RequestDialog',
	components: { NcButton, NcDateTimePickerNative, NcDialog, NcSelect, NcTextArea, RangeCalendar },
	props: {
		/** Who decides the viewer's Requests, from their record; null while they have none. */
		decider: { type: Object, default: null },
	},
	emits: ['close', 'sent'],
	setup: () => ({ afterwards, asDate, asDay, daysWord, deciderText, number, runs }),
	data: () => ({
		leaveType: ASKED[0],
		types: ASKED.map((id) => ({ id, label: LEAVE_TYPES[id].name })),
		from: '',
		to: '',
		comment: '',
		sendError: '',
		sending: false,
		/** The preview of the range, or null while it has none. */
		count: null,
		error: '',
		/** The latest preview asked for: an older answer that arrives late is dropped. */
		asked: 0,
	}),
	computed: {
		sendable() {
			return this.count !== null && this.count.errors.length === 0 && !this.sending
		},
		/** The skipped days as the count box writes them: each holiday by name, then the weekends. */
		skipped() {
			const holidays = this.count.skipped.filter((day) => day.name !== null)
				.map((day) => `${shortDay(day.date)}, ${day.name} (día feriado${day.reason === 'weekend' ? ', fin de semana' : ''})`)
			const weekends = this.count.skipped.filter((day) => day.name === null).map((day) => day.date)
			return [...holidays, ...(weekends.length > 0 ? [`fin de semana: ${runs(weekends)}`] : [])].join(' · ')
		},
	},
	watch: {
		leaveType: 'recount',
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
			this.sendError = ''
			if (this.from === '' || this.to === '') {
				this.count = null
				return
			}
			try {
				const count = await ocsGet('requests/preview', { from: this.from, to: this.to, leaveType: this.leaveType })
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
		async send() {
			this.sending = true
			this.sendError = ''
			try {
				const { request } = await ocsPost('requests', { leaveType: this.leaveType, from: this.from, to: this.to, comment: this.comment })
				this.$emit('sent', request)
			} catch (error) {
				this.sendError = refusal(error) ?? 'No se pudo enviar la solicitud. Inténtalo de nuevo.'
			} finally {
				this.sending = false
			}
		},
	},
}
</script>

<style scoped>
.rh-type {
	max-width: 300px;
	margin-block-end: calc(var(--default-grid-baseline) * 3);
}

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
	.rh-type {
	max-width: 300px;
	margin-block-end: calc(var(--default-grid-baseline) * 3);
}

.rh-picker {
		grid-template-columns: minmax(0, 1fr);
	}
}
</style>
