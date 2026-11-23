<template>
	<div class="rh-page">
		<h2>Por decidir</h2>
		<NcLoadingIcon v-if="state === 'loading'" />
		<NcNoteCard v-else-if="state === 'failed'" type="error">
			No se pudo cargar «Por decidir». Recarga la página; si vuelve a ocurrir, avisa al administrador.
		</NcNoteCard>
		<template v-else>
			<p v-if="subtitle" class="rh-hint">
				{{ subtitle }}
			</p>
			<NcNoteCard v-if="delegates && !delegation" type="warning" heading="Falta la delegación">
				Recursos Humanos todavía no registra la delegación del Director/a. Sin ella no puedes aprobar ni rechazar: avísale.
			</NcNoteCard>
			<NcEmptyContent v-if="requests.length === 0"
				name="Nada por decidir"
				description="Cuando alguien de tu equipo pida días, aparece aquí y te llega un aviso.">
				<template #icon>
					<NcIconSvgWrapper :path="mdiAccountCheckOutline" />
				</template>
			</NcEmptyContent>
			<template v-else>
				<h3>Solicitudes</h3>
				<p class="rh-hint">
					{{ requests.length === 1 ? '1 solicitud' : `${requests.length} solicitudes` }}, la más antigua primero.
				</p>
				<article v-for="request in requests" :key="request.id" class="rh-decision" :aria-label="`Solicitud de ${request.person.name}`">
					<div class="rh-decision__head">
						<div>
							<strong>{{ request.person.name }}</strong>
							<div v-if="request.person.cargos.length > 0" class="rh-hint">
								{{ cargoLine(request.person.cargos) }}
							</div>
						</div>
						<NcChip :text="STATES[request.state].name" :variant="STATES[request.state].variant" no-close />
					</div>
					<dl class="rh-record">
						<dt>Tipo de permiso</dt>
						<dd><strong>{{ typeName(request) }}</strong></dd>
						<dt>Fechas</dt>
						<dd>{{ rangeText(request) }}</dd>
						<dt>Días</dt>
						<dd>
							{{ number(request.days) }} {{ daysWord(request.days, request.unit) }}
							<div v-if="request.skipped.length > 0" class="rh-hint">
								No se cuentan: {{ runs(request.skipped.map((day) => day.date)) }}
							</div>
						</dd>
						<dt>Disponible</dt>
						<dd class="rh-number">
							<div v-for="line in afterwards(request)" :key="line.year">
								{{ line.year }}: {{ number(line.before) }} → <span :class="{ 'rh-error': line.after < 0 }">{{ number(line.after) }}</span>
							</div>
							<span class="rh-hint">El saldo menos sus otras solicitudes en curso.</span>
						</dd>
						<template v-if="request.comment">
							<dt>Comentario</dt>
							<dd>«{{ request.comment }}»</dd>
						</template>
						<dt>Pedida</dt>
						<dd>{{ stamp(request.trail[0].at) }}</dd>
					</dl>
					<div v-if="rejecting === request.id" class="rh-form">
						<NcTextArea v-model="reason"
							label="Motivo del rechazo (obligatorio)"
							placeholder="Por ejemplo: ese día el vacunatorio queda sin personal. ¿Puedes pedir otra fecha?"
							:helper-text="`${request.person.name} lo ve en su solicitud. El rechazo siempre lleva motivo, también en el feriado legal (regla del establecimiento).`" />
					</div>
					<p v-if="errors[request.id]" class="rh-error">
						{{ errors[request.id] }}
					</p>
					<div class="rh-decision__foot">
						<span class="rh-hint">Decides {{ CAPACITIES[request.decider?.capacity] ?? '' }}. Si la apruebas, pasa a Recursos Humanos: el saldo cambia recién cuando la registra.</span>
						<template v-if="rejecting === request.id">
							<NcButton variant="tertiary" @click="rejecting = null">
								Volver
							</NcButton>
							<NcButton variant="primary" :disabled="busy" @click="reject(request)">
								Rechazar solicitud
							</NcButton>
						</template>
						<template v-else>
							<NcButton variant="secondary" :disabled="busy" @click="startRejecting(request)">
								Rechazar
							</NcButton>
							<NcButton variant="primary" :disabled="busy" @click="approve(request)">
								Aprobar
							</NcButton>
						</template>
					</div>
				</article>
			</template>
		</template>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import { ocsGet, ocsPost, refusal } from './api.js'
import { daysWord, number, runs } from './days.js'
import { mdiAccountCheckOutline } from './icons.js'
import { cargoLine, stamp } from './people.js'
import { CAPACITIES, STATES, afterwards, delegationText, rangeText, typeName } from './requests.js'

/**
 * «Por decidir» (recursos-humanos#6): the pending Requests the viewer decides, oldest first, each
 * with the person's Available before and after it. Approving is one click; rejecting needs a
 * written reason the person reads.
 */
export default {
	name: 'DecisionsPage',
	components: { NcButton, NcChip, NcEmptyContent, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard, NcTextArea },
	emits: ['decided'],
	setup: () => ({ CAPACITIES, STATES, afterwards, cargoLine, daysWord, mdiAccountCheckOutline, number, rangeText, runs, stamp, typeName }),
	data: () => ({
		state: 'loading',
		requests: [],
		delegation: null,
		/** The Request whose rejection is being written, and its reason. */
		rejecting: null,
		reason: '',
		/** Each Request's last refusal, by id. */
		errors: {},
		busy: false,
	}),
	computed: {
		/** Whether the viewer decides some Request by the Director's Delegation. */
		delegates() {
			return this.requests.some((request) => request.decider?.capacity === 'delegation')
		},
		subtitle() {
			if (this.delegates) {
				return this.delegation
					? `Decides por delegación del Director/a · ${delegationText(this.delegation)}`
					: 'Decides por delegación del Director/a.'
			}
			return this.requests.length > 0 ? 'Solicitudes de las personas sin jefatura directa, o de las que eres jefatura. Decides como Director/a.' : ''
		},
	},
	mounted() {
		this.load()
	},
	methods: {
		async load() {
			try {
				const queue = await ocsGet('decisions')
				this.requests = queue.requests
				this.delegation = queue.delegation
				this.state = 'ready'
			} catch {
				this.state = 'failed'
			}
		},
		startRejecting(request) {
			this.rejecting = request.id
			this.reason = ''
		},
		async approve(request) {
			await this.decide(request, () => ocsPost(`requests/${request.id}/approval`))
		},
		async reject(request) {
			await this.decide(request, () => ocsPost(`requests/${request.id}/rejection`, { reason: this.reason }))
		},
		async decide(request, call) {
			this.busy = true
			this.errors = { ...this.errors, [request.id]: '' }
			try {
				await call()
				this.rejecting = null
				this.$emit('decided')
				await this.load()
			} catch (error) {
				this.errors = { ...this.errors, [request.id]: refusal(error) ?? 'No se pudo guardar la decisión. Inténtalo de nuevo.' }
			} finally {
				this.busy = false
			}
		},
	},
}
</script>

<style scoped>
.rh-decision {
	max-width: 720px;
	margin-block: calc(var(--default-grid-baseline) * 3);
	padding: calc(var(--default-grid-baseline) * 3.5) calc(var(--default-grid-baseline) * 4);
	border: 1px solid var(--color-border-dark);
	border-radius: var(--border-radius-large);
}

.rh-decision__head {
	display: flex;
	align-items: flex-start;
	justify-content: space-between;
	gap: calc(var(--default-grid-baseline) * 2);
}

.rh-decision__foot {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	justify-content: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-top: calc(var(--default-grid-baseline) * 3);
}

.rh-decision__foot .rh-hint {
	flex: 1 1 260px;
}

.rh-form {
	margin-top: calc(var(--default-grid-baseline) * 3);
}
</style>
