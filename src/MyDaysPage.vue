<template>
	<div class="rh-page">
		<h2>Mis días</h2>
		<NcLoadingIcon v-if="state === 'loading'" />
		<NcNoteCard v-else-if="state === 'failed'" type="error">
			No se pudieron cargar tus días. Recarga la página; si vuelve a ocurrir, avisa al administrador.
		</NcNoteCard>
		<NcEmptyContent v-else-if="person === null"
			name="Tus días aparecerán aquí"
			description="Cuando Recursos Humanos complete tu ficha, verás tu feriado legal, tus días administrativos y tus otros permisos.">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCalendarAccountOutline" />
			</template>
		</NcEmptyContent>
		<template v-else>
			<h3>Saldos {{ days.year }}</h3>
			<p class="rh-hint">
				Saldo: la suma de tus movimientos; cambia solo cuando Recursos Humanos registra. Disponible: el saldo menos tus solicitudes en curso (pendientes o aprobadas sin registrar).
			</p>
			<NcNoteCard v-if="honorarios" type="info">
				Estás a honorarios: sin feriado legal ni días administrativos.
			</NcNoteCard>
			<div v-else class="rh-cards">
				<section v-for="balance in days.balances" :key="balance.leaveType" class="rh-card">
					<h4 class="rh-card__head">
						<NcIconSvgWrapper :path="LEAVE_TYPES[balance.leaveType].icon" />
						{{ LEAVE_TYPES[balance.leaveType].name }}
					</h4>
					<p v-if="balance.leaveType === 'annualLeave' && days.annualLeaveFrom">
						Tendrás feriado legal desde el <strong>{{ longDay(days.annualLeaveFrom) }}</strong>, al cumplir más de un año de servicio.
					</p>
					<template v-else-if="balance.leaveType === 'administrativeDay' && days.missing.includes('administrativeDays')">
						<NcChip text="Falta completar" variant="warning" no-close />
						<p class="rh-hint">
							Recursos Humanos fija tus días administrativos de {{ days.year }}: ingresaste a mitad de año y no hay un número por defecto.
						</p>
					</template>
					<template v-else>
						<div class="rh-card__numbers">
							<div>
								<div class="rh-hint">
									Saldo
								</div>
								<span class="rh-card__balance rh-number">{{ number(balance.balance) }}</span>
								<span class="rh-hint">de {{ number(balance.granted) }}</span>
							</div>
							<div>
								<div class="rh-hint">
									Disponible
								</div>
								<span class="rh-card__balance rh-number">{{ number(balance.available) }}</span>
							</div>
						</div>
						<p class="rh-hint">
							{{ daysWord(balance.granted, balance.unit) }}
						</p>
						<div class="rh-meter" aria-hidden="true">
							<span :style="{ width: `${share(balance)}%` }" />
						</div>
					</template>
				</section>
			</div>
			<div class="rh-service">
				<NcIconSvgWrapper :path="mdiHistory" />
				<div>
					<strong>Años de servicio: {{ serviceText(days.serviceMonths) }}</strong>
					<p class="rh-hint">
						{{ serviceLine }}
					</p>
					<p class="rh-hint">
						Régimen: {{ person.regimes.map((line) => `${REGIMES[line.regime]} desde el ${longDay(line.from)}`).join(' · ') }}
					</p>
				</div>
			</div>
			<p v-if="!honorarios" class="rh-hint rh-decider">
				{{ deciderText(person.decider) }}
			</p>
		</template>
	</div>
</template>

<script>
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { ocsGet } from './api.js'
import { daysWord, longDay, number } from './days.js'
import { mdiCalendarAccountOutline, mdiHistory } from './icons.js'
import { LEAVE_TYPES, serviceText } from './ledger.js'
import { REGIMES } from './people.js'
import { deciderText } from './requests.js'

/**
 * «Mis días» (recursos-humanos#5, #6): this year's Balance and Available days of each Leave type,
 * the person's Years of service, the day their Annual leave rises, and who decides their Requests;
 * empty until HR completes the record.
 */
export default {
	name: 'MyDaysPage',
	components: { NcChip, NcEmptyContent, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard },
	setup: () => ({ LEAVE_TYPES, REGIMES, daysWord, deciderText, longDay, mdiCalendarAccountOutline, mdiHistory, number, serviceText }),
	data: () => ({
		state: 'loading',
		/** The caller's record, null until HR completes it. */
		person: null,
	}),
	computed: {
		days() {
			return this.person.days
		},
		honorarios() {
			return this.person.regime === 'honorarios'
		},
		/** Where Years of service come from, and what they bring next. */
		serviceLine() {
			const prior = this.person.priorMonths > 0 ? `, más ${serviceText(this.person.priorMonths)} de servicio público anterior` : ''
			const since = `Ingreso el ${longDay(this.person.startDate)}${prior}.`
			if (this.honorarios) {
				return `${since} A honorarios no hay feriado legal ni días administrativos.`
			}
			if (this.days.annualLeaveFrom) {
				return `${since} El feriado legal empieza con más de un año de servicio: desde el ${longDay(this.days.annualLeaveFrom)}.`
			}
			const rise = this.days.nextRise
			return rise
				? `${since} El ${longDay(rise.on)} cumples ${rise.years} años y tu feriado legal sube a ${number(rise.days)} días hábiles ese mismo día.`
				: since
		},
	},
	async mounted() {
		try {
			this.person = (await ocsGet('me')).person
			this.state = 'ready'
		} catch {
			this.state = 'failed'
		}
	},
	methods: {
		/** How much of the granted days is left, for the meter. */
		share({ balance, granted }) {
			return granted > 0 ? Math.max(0, Math.min(100, Math.round((balance / granted) * 100))) : 0
		},
	},
}
</script>

<style scoped>
.rh-cards {
	display: grid;
	grid-template-columns: repeat(auto-fill, minmax(205px, 1fr));
	gap: calc(var(--default-grid-baseline) * 3);
	margin-block: calc(var(--default-grid-baseline) * 3);
}

.rh-card {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 2);
	min-width: 0;
	padding: calc(var(--default-grid-baseline) * 3.5) calc(var(--default-grid-baseline) * 4);
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
}

.rh-card p {
	margin: 0;
}

.rh-card__head {
	display: flex;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
	margin: 0;
	font-size: var(--default-font-size);
	font-weight: bold;
}

.rh-card__numbers {
	display: flex;
	flex-wrap: wrap;
	gap: calc(var(--default-grid-baseline) * 5);
}

.rh-card__balance {
	margin-inline-end: var(--default-grid-baseline);
	font-size: 28px;
	font-weight: bold;
}

.rh-meter {
	height: 6px;
	overflow: hidden;
	border-radius: 3px;
	background: var(--color-background-dark);
}

.rh-meter > span {
	display: block;
	height: 100%;
	border-radius: 3px;
	background: var(--color-primary-element);
}

.rh-service {
	display: flex;
	align-items: flex-start;
	gap: calc(var(--default-grid-baseline) * 3);
	max-width: 720px;
	padding: calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 3.5);
	border-radius: var(--border-radius-large);
	background: var(--color-background-hover);
}

.rh-service p {
	margin: var(--default-grid-baseline) 0 0;
}

.rh-decider {
	max-width: 720px;
	margin-block-start: calc(var(--default-grid-baseline) * 3);
}
</style>
