<template>
	<NcAppSidebar :name="typeName(request)" :subname="rangeText(request)" @close="$emit('close')">
		<div class="rh-sidebar">
			<p class="rh-chips">
				<NcChip :text="STATES[request.state].name" :variant="STATES[request.state].variant" no-close />
			</p>
			<NcNoteCard v-if="rejected" type="error" :heading="`Rechazada por ${rejected.actor.name}`">
				{{ rejected.note }}
			</NcNoteCard>
			<p v-if="request.decider" class="rh-hint">
				{{ deciderText(request.decider) }}
			</p>
			<dl class="rh-record">
				<template v-if="request.person.uid !== viewer">
					<dt>Persona</dt>
					<dd>{{ request.person.name }}</dd>
				</template>
				<dt>Tipo de permiso</dt>
				<dd>{{ typeName(request) }}</dd>
				<dt>Fechas</dt>
				<dd>{{ rangeText(request) }}</dd>
				<dt>Días contados</dt>
				<dd>{{ number(request.days) }} {{ daysWord(request.days, request.unit) }}</dd>
				<dt>Se cuentan</dt>
				<dd>{{ runs(request.counted) || 'ninguno' }}</dd>
				<template v-if="request.skipped.length > 0">
					<dt>No se cuentan</dt>
					<dd>{{ runs(request.skipped.map((day) => day.date)) }}</dd>
				</template>
				<template v-if="request.available.length > 0">
					<dt>Disponible</dt>
					<dd class="rh-number">
						<div v-for="line in afterwards(request)" :key="line.year">
							{{ line.year }}: {{ number(line.before) }} → {{ number(line.after) }}
						</div>
						<span class="rh-hint">El saldo cambia cuando Recursos Humanos la registra.</span>
					</dd>
				</template>
				<template v-if="request.comment">
					<dt>Comentario</dt>
					<dd>«{{ request.comment }}»</dd>
				</template>
			</dl>
			<h3>Normativa aplicada</h3>
			<ul class="rh-norms">
				<li v-for="rule in request.ruleVersions" :key="`${rule.rule}/${rule.version}`">
					<strong>{{ rule.title }}</strong> · versión {{ rule.version }} · {{ rule.summary }}
					<span class="rh-hint">{{ rule.basis }}{{ rule.establishmentRule ? ' · regla del establecimiento' : '' }} · vigente desde el {{ longDay(rule.from) }}</span>
				</li>
			</ul>
			<p class="rh-hint">
				La versión de cada regla con que se contó esta solicitud.
			</p>
			<h3>Recorrido</h3>
			<ol class="rh-trail">
				<li v-for="(step, index) in request.trail" :key="index">
					<div>
						<strong>{{ stepText(step) }}</strong> · {{ step.actor.name }}
					</div>
					<div class="rh-hint">
						{{ stamp(step.at) }}<template v-if="step.delegation">
							· {{ delegationText(step.delegation) }}
						</template>
					</div>
					<div v-if="step.note">
						{{ step.note }}
					</div>
				</li>
			</ol>
		</div>
	</NcAppSidebar>
</template>

<script>
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { daysWord, longDay, number, runs } from './days.js'
import { stamp } from './people.js'
import { STATES, afterwards, deciderText, delegationText, rangeText, rejection, stepText, typeName } from './requests.js'

/**
 * A Request's sidebar (recursos-humanos#6): its state, the reason of a rejection, its days, who
 * decides it, the Rule versions it was counted with and its trail.
 */
export default {
	name: 'RequestSidebar',
	components: { NcAppSidebar, NcChip, NcNoteCard },
	props: {
		request: { type: Object, required: true },
		/** The signed-in account, which reads «Persona» only on someone else's Request. */
		viewer: { type: String, required: true },
	},
	emits: ['close'],
	setup: () => ({ STATES, afterwards, daysWord, deciderText, delegationText, longDay, number, rangeText, runs, stamp, stepText, typeName }),
	computed: {
		rejected() {
			return rejection(this.request)
		},
	},
}
</script>

<style scoped>
.rh-chips {
	margin: 0 0 calc(var(--default-grid-baseline) * 2);
}

.rh-norms {
	padding-inline-start: calc(var(--default-grid-baseline) * 5);
	list-style: disc;
}

.rh-norms li + li {
	margin-top: calc(var(--default-grid-baseline) * 2);
}

.rh-norms .rh-hint {
	display: block;
}
</style>
