<template>
	<div class="rh-page">
		<h2>Ajustes</h2>
		<section>
			<h3>Normativa</h3>
			<p class="rh-hint">
				Las reglas con que la app cuenta los días, con su versión, desde cuándo rigen y su base. Solo lectura: las versiones nuevas llegan con las actualizaciones de la aplicación.
			</p>
			<NcLoadingIcon v-if="state === 'loading'" />
			<NcNoteCard v-else-if="state === 'failed'" type="error">
				No se pudo cargar la normativa. Recarga la página.
			</NcNoteCard>
			<ul v-else class="rh-rules">
				<li v-for="rule in rules" :key="`${rule.rule}/${rule.version}`" class="rh-rule">
					<NcIconSvgWrapper :path="mdiScaleBalance" />
					<div>
						<div class="rh-rule__title">
							<strong>{{ rule.title }}</strong>
							<span class="rh-hint">versión {{ rule.version }}</span>
							<NcChip v-if="rule.establishmentRule" text="Regla del establecimiento" no-close />
						</div>
						<div>{{ rule.summary }}</div>
						<div class="rh-hint">
							{{ rule.basis }} · vigente desde el {{ longDay(rule.from) }}
						</div>
					</div>
				</li>
			</ul>
		</section>
		<section>
			<h3>Delegación</h3>
			<p class="rh-hint">
				El acto del Director/a que permite a las jefaturas directas decidir las solicitudes en su nombre. Aparece en cada aprobación y en el comprobante.
			</p>
			<NcLoadingIcon v-if="delegationState === 'loading'" />
			<NcNoteCard v-else-if="delegationState === 'failed'" type="error">
				No se pudo cargar la delegación. Recarga la página.
			</NcNoteCard>
			<template v-else>
				<dl class="rh-record">
					<dt>Vigente</dt>
					<dd v-if="delegation">
						{{ delegationText(delegation) }}
						<span class="rh-hint">· registrada por {{ delegation.recordedBy.name }} el {{ stamp(delegation.recordedAt) }}</span>
					</dd>
					<dd v-else>
						Sin registrar: hasta que la registres, las jefaturas no pueden decidir.
					</dd>
					<dt>Delega en</dt>
					<dd>La jefatura directa que Recursos Humanos asigna a cada persona. Sin jefatura, decide el Director/a.</dd>
				</dl>
				<form class="rh-delegation" @submit.prevent="record">
					<NcTextField v-model="number" label="N° de resolución" />
					<NcDateTimePickerNative id="rh-delegation-date"
						label="Fecha"
						type="date"
						:model-value="asDate(date)"
						@update:model-value="date = asDay($event)" />
					<p v-if="error" class="rh-error">
						{{ error }}
					</p>
					<div>
						<NcButton variant="primary" type="submit" :disabled="saving">
							Guardar delegación
						</NcButton>
					</div>
				</form>
			</template>
		</section>
	</div>
</template>

<script>
import NcButton from '@nextcloud/vue/components/NcButton'
import NcChip from '@nextcloud/vue/components/NcChip'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { ocsGet, ocsPost, refusal } from './api.js'
import { asDate, asDay, longDay } from './days.js'
import { mdiScaleBalance } from './icons.js'
import { stamp } from './people.js'
import { delegationText } from './requests.js'

/**
 * «Ajustes» (recursos-humanos#5, #6): «Normativa», the Rule versions the app applies, read-only;
 * and «Delegación», the Director's act HR records so that the Jefaturas can decide. The other
 * sections arrive with their tickets.
 */
export default {
	name: 'SettingsPage',
	components: { NcButton, NcChip, NcDateTimePickerNative, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard, NcTextField },
	setup: () => ({ asDate, asDay, delegationText, longDay, mdiScaleBalance, stamp }),
	data: () => ({
		state: 'loading',
		rules: [],
		delegationState: 'loading',
		/** The Delegation in force, null until HR records one. */
		delegation: null,
		/** The form's resolution number and date. */
		number: '',
		date: '',
		error: '',
		saving: false,
	}),
	mounted() {
		this.loadRules()
		this.loadDelegation()
	},
	methods: {
		async loadRules() {
			try {
				this.rules = (await ocsGet('rules')).rules
				this.state = 'ready'
			} catch {
				this.state = 'failed'
			}
		},
		async loadDelegation() {
			try {
				this.delegation = (await ocsGet('settings/delegation')).delegation
				this.delegationState = 'ready'
			} catch {
				this.delegationState = 'failed'
			}
		},
		async record() {
			this.saving = true
			this.error = ''
			try {
				this.delegation = (await ocsPost('settings/delegation', { number: this.number, date: this.date })).delegation
				this.number = ''
				this.date = ''
			} catch (error) {
				this.error = refusal(error) ?? 'No se pudo guardar la delegación. Inténtalo de nuevo.'
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.rh-rules {
	max-width: 820px;
	margin: calc(var(--default-grid-baseline) * 3) 0;
	padding: 0;
	list-style: none;
}

.rh-rule {
	display: flex;
	align-items: flex-start;
	gap: calc(var(--default-grid-baseline) * 3);
	padding-block: calc(var(--default-grid-baseline) * 3);
	border-bottom: 1px solid var(--color-border);
}

.rh-rule__title {
	display: flex;
	flex-wrap: wrap;
	align-items: center;
	gap: calc(var(--default-grid-baseline) * 2);
}

.rh-delegation {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
	max-width: 420px;
	margin-block: calc(var(--default-grid-baseline) * 3);
}
</style>
