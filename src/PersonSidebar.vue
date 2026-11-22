<template>
	<NcAppSidebar v-model:active="tab"
		:name="title"
		:subname="subname"
		@close="$emit('close')">
		<NcAppSidebarTab id="summary" name="Resumen" :order="0">
			<template #icon>
				<NcIconSvgWrapper :path="mdiCardAccountDetailsOutline" />
			</template>
			<div class="rh-sidebar">
				<NcLoadingIcon v-if="state === 'loading'" />
			<NcNoteCard v-else-if="state === 'failed'" type="error">
				No se pudo cargar la ficha. Recarga la página.
			</NcNoteCard>

			<form v-else-if="form === 'complete'" class="rh-form" @submit.prevent="complete">
				<NcTextField v-model="draft.givenNames" label="Nombres" />
				<NcTextField v-model="draft.surnames" label="Apellidos" />
				<NcTextField v-model="draft.rut"
					label="RUT"
					placeholder="12.345.678-5"
					helper-text="Con o sin puntos, y el dígito verificador después del guion." />
				<NcDateTimePickerNative id="rh-start"
					:model-value="asDate(draft.startDate)"
					type="date"
					label="Fecha de ingreso"
					@update:model-value="draft.startDate = asDay($event)" />
				<NcTextField v-model="draft.priorMonths"
					type="number"
					min="0"
					label="Servicio público anterior (meses)" />
				<NcSelect v-model="draft.regime"
					input-label="Régimen"
					:options="regimes"
					:reduce="(option) => option.id"
					:clearable="false" />
				<NcDateTimePickerNative id="rh-regime-from"
					:model-value="asDate(draft.regimeFrom || draft.startDate)"
					type="date"
					label="Régimen desde"
					@update:model-value="draft.regimeFrom = asDay($event)" />
				<NcCheckboxRadioSwitch v-model="draft.shift" type="switch">
					Trabaja en turnos: el feriado legal se cuenta de lunes a viernes
				</NcCheckboxRadioSwitch>
				<NcSelect v-model="draft.jefatura"
					input-label="Jefatura directa"
					placeholder="Sin jefatura directa (decide el Director/a)"
					:options="jefaturas"
					:reduce="(option) => option.uid"
					label="name" />
				<NcNoteCard v-if="error" type="error">
					{{ error }}
				</NcNoteCard>
				<p class="rh-hint">
					Recursos Humanos ingresa o confirma el RUT con el nombre y los apellidos: son la llave de la persona en el registro, el comprobante y la planilla de respaldo.
				</p>
				<div class="rh-buttons">
					<NcButton variant="tertiary" @click="close">
						Volver
					</NcButton>
					<NcButton variant="primary" type="submit" :disabled="saving">
						Guardar
					</NcButton>
				</div>
			</form>

			<form v-else-if="form === 'regime'" class="rh-form" @submit.prevent="changeRegime">
				<NcSelect v-model="draft.regime"
					input-label="Nuevo régimen"
					:options="regimes"
					:reduce="(option) => option.id"
					:clearable="false" />
				<NcDateTimePickerNative id="rh-regime-change"
					:model-value="asDate(draft.regimeFrom)"
					type="date"
					label="Desde"
					@update:model-value="draft.regimeFrom = asDay($event)" />
				<NcTextArea v-model="draft.reason"
					label="Motivo (obligatorio)"
					placeholder="Por ejemplo: decreto de nombramiento N° 845."
					resize="vertical" />
				<NcNoteCard v-if="error" type="error">
					{{ error }}
				</NcNoteCard>
				<p class="rh-hint">
					Es una línea nueva desde esa fecha; las anteriores quedan como están.
				</p>
				<div class="rh-buttons">
					<NcButton variant="tertiary" @click="close">
						Volver
					</NcButton>
					<NcButton variant="primary" type="submit" :disabled="saving">
						Guardar
					</NcButton>
				</div>
			</form>

			<form v-else-if="form === 'administrativeDays'" class="rh-form" @submit.prevent="setAdministrativeDays">
				<NcTextField v-model="draft.days"
					type="number"
					min="0"
					step="0.5"
					:label="`Días administrativos ${person.days.year}`" />
				<p class="rh-hint">
					Ingresó el {{ longDay(person.startDate) }}, a mitad de año: no hay un número por defecto. Recursos Humanos fija cuántos tiene en {{ person.days.year }}, en días enteros o medios días.
				</p>
				<NcNoteCard v-if="error" type="error">
					{{ error }}
				</NcNoteCard>
				<div class="rh-buttons">
					<NcButton variant="tertiary" @click="close">
						Volver
					</NcButton>
					<NcButton variant="primary" type="submit" :disabled="saving">
						Guardar
					</NcButton>
				</div>
			</form>

			<template v-else-if="person === null">
				<NcNoteCard type="warning" heading="Falta completar">
					Sin su ficha no puede pedir días: falta el RUT, el nombre y los apellidos, la fecha de ingreso, el servicio anterior, el régimen y la jefatura directa.
				</NcNoteCard>
				<div class="rh-buttons">
					<NcButton variant="primary" @click="open('complete')">
						Completar ficha
					</NcButton>
				</div>
			</template>

			<template v-else>
				<template v-if="person.days.missing.includes('administrativeDays')">
					<NcNoteCard type="warning" heading="Falta completar">
						Ingresó el {{ longDay(person.startDate) }}, a mitad de año: Recursos Humanos fija cuántos días administrativos tiene en {{ person.days.year }}.
					</NcNoteCard>
					<div class="rh-buttons">
						<NcButton variant="primary" @click="open('administrativeDays')">
							Completar ficha
						</NcButton>
					</div>
				</template>
				<dl class="rh-record">
					<dt>Nombre y apellidos</dt>
					<dd>{{ person.givenNames }} {{ person.surnames }}</dd>
					<dt>RUT</dt>
					<dd class="rh-number">
						{{ dottedRut(person.rut) }}
					</dd>
					<dt>Cargo</dt>
					<dd>{{ cargo }}</dd>
					<dt>Régimen</dt>
					<dd>
						<div v-for="line in person.regimes" :key="line.from">
							{{ REGIMES[line.regime] }} <span class="rh-hint">desde el {{ longDay(line.from) }}</span>
						</div>
					</dd>
					<dt>Fecha de ingreso</dt>
					<dd>
						{{ longDay(person.startDate) }}
						<span v-if="person.priorMonths > 0" class="rh-hint">· más {{ person.priorMonths }} {{ person.priorMonths === 1 ? 'mes' : 'meses' }} de servicio público anterior</span>
					</dd>
					<dt>Jefatura directa</dt>
					<dd>{{ person.jefatura ? person.jefatura.name : 'Sin jefatura: decide el Director/a' }}</dd>
				</dl>
				<div class="rh-buttons">
					<NcButton variant="secondary" @click="open('regime')">
						Cambiar régimen
					</NcButton>
				</div>
				<h3>Recorrido de la ficha</h3>
				<ol class="rh-trail">
					<li v-for="(step, index) in person.trail" :key="index">
						<div>
							<strong>{{ stepText(step) }}</strong> · {{ step.actor.name }}
						</div>
						<div class="rh-hint">
							{{ stamp(step.at) }}
						</div>
						<div v-if="step.reason">
							{{ step.reason }}
						</div>
					</li>
				</ol>
			</template>
			</div>
		</NcAppSidebarTab>
		<NcAppSidebarTab v-if="person" id="movements" name="Movimientos" :order="1">
			<template #icon>
				<NcIconSvgWrapper :path="mdiHistory" />
			</template>
			<div class="rh-sidebar">
				<ol v-if="person.movements.length > 0" class="rh-ledger">
					<li v-for="movement in person.movements" :key="movement.id" class="rh-ledger__row">
						<span class="rh-hint rh-number">{{ longDay(movement.date) }}</span>
						<span><strong>{{ MOVEMENT_KINDS[movement.kind] }}</strong> · {{ LEAVE_TYPES[movement.leaveType].name }} {{ movement.period }}</span>
						<span class="rh-ledger__quantity rh-number">{{ signed(movement.quantity) }}</span>
						<span class="rh-ledger__why rh-hint">
							{{ author(movement) }} · {{ movement.reason }}
						</span>
						<span v-if="movement.rule" class="rh-ledger__why rh-hint">
							Norma: {{ movement.rule.title }}, versión {{ movement.rule.version }}
						</span>
					</li>
				</ol>
				<p v-else class="rh-hint">
					Sin movimientos{{ person.regime === 'honorarios' ? ': a honorarios no hay saldos.' : '.' }}
				</p>
				<ul class="rh-totals">
					<li v-for="total in totals" :key="total.key">
						Saldo de {{ total.name }} {{ total.period }} = suma de sus movimientos: <strong class="rh-number">{{ number(total.balance) }}</strong>
					</li>
				</ul>
				<p class="rh-hint">
					Los saldos nunca se escriben encima: cada cambio es un movimiento con fecha, quién y por qué.
				</p>
			</div>
		</NcAppSidebarTab>
	</NcAppSidebar>
</template>

<script>
import NcAppSidebar from '@nextcloud/vue/components/NcAppSidebar'
import NcAppSidebarTab from '@nextcloud/vue/components/NcAppSidebarTab'
import NcButton from '@nextcloud/vue/components/NcButton'
import NcCheckboxRadioSwitch from '@nextcloud/vue/components/NcCheckboxRadioSwitch'
import NcDateTimePickerNative from '@nextcloud/vue/components/NcDateTimePickerNative'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcSelect from '@nextcloud/vue/components/NcSelect'
import NcTextArea from '@nextcloud/vue/components/NcTextArea'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { ocsGet, ocsPost, refusal } from './api.js'
import { asDate, asDay, longDay, number } from './days.js'
import { mdiCardAccountDetailsOutline, mdiHistory } from './icons.js'
import { LEAVE_TYPES, MOVEMENT_KINDS, author } from './ledger.js'
import { REGIMES, cargoLine, dottedRut, stamp } from './people.js'

const TITLES = { complete: 'Completar ficha', administrativeDays: 'Completar ficha', regime: 'Cambiar régimen' }

/**
 * A person's sidebar in «Personal» (recursos-humanos#3, #5): «Resumen», their record with its
 * Regime lines and trail and HR's «Completar ficha» and «Cambiar régimen»; «Movimientos», their
 * Ledger with each Balance as the sum of its Movements.
 */
export default {
	name: 'PersonSidebar',
	components: { NcAppSidebar, NcAppSidebarTab, NcButton, NcCheckboxRadioSwitch, NcDateTimePickerNative, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard, NcSelect,
		NcTextArea, NcTextField },
	props: {
		/** The person's entry in «Personal». */
		entry: { type: Object, required: true },
		/** Everyone in «Personal», for the Jefatura picker. */
		people: { type: Array, required: true },
	},
	emits: ['close', 'saved'],
	setup: () => ({
		LEAVE_TYPES,
		MOVEMENT_KINDS,
		REGIMES,
		regimes: Object.entries(REGIMES).map(([id, label]) => ({ id, label })),
		asDate,
		asDay,
		author,
		dottedRut,
		longDay,
		mdiCardAccountDetailsOutline,
		mdiHistory,
		number,
		stamp,
	}),
	data() {
		return {
			state: this.entry.rut ? 'loading' : 'ready',
			person: null,
			/** The open tab: «Resumen» or «Movimientos». */
			tab: 'summary',
			form: null,
			draft: {},
			error: '',
			saving: false,
		}
	},
	computed: {
		title() {
			return this.form ? `${TITLES[this.form]} · ${this.entry.name}` : this.entry.name
		},
		subname() {
			return [cargoLine(this.entry.cargos), this.entry.rut && `RUT ${dottedRut(this.entry.rut)}`].filter(Boolean).join(' · ')
		},
		cargo() {
			const cargo = cargoLine(this.entry.cargos) || '—'
			return this.person.shift ? `${cargo} · trabaja en turnos (el feriado legal se cuenta de lunes a viernes)` : cargo
		},
		/** Each Balance in the Ledger, newest period first: the sum of its Movements. */
		totals() {
			const totals = {}
			for (const movement of this.person.movements) {
				const key = `${movement.period}/${movement.leaveType}`
				totals[key] ??= { key, period: movement.period, name: LEAVE_TYPES[movement.leaveType].name.toLowerCase(), balance: 0 }
				totals[key].balance += movement.quantity
			}
			const order = Object.keys(LEAVE_TYPES)
			return Object.values(totals).sort((a, b) => b.period - a.period || order.indexOf(a.key.split('/')[1]) - order.indexOf(b.key.split('/')[1]))
		},
		jefaturas() {
			return this.people.filter((person) => person.uid !== this.entry.uid).map(({ uid, name }) => ({ uid, name }))
		},
	},
	async mounted() {
		if (!this.entry.rut) {
			return
		}
		try {
			this.person = (await ocsGet(`people/${this.entry.rut}`)).person
			this.state = 'ready'
		} catch {
			this.state = 'failed'
		}
	},
	methods: {
		open(form) {
			this.form = form
			this.tab = 'summary'
			this.error = ''
			this.draft = {
				complete: () => ({ givenNames: '', surnames: '', rut: '', startDate: '', priorMonths: '0', regime: 'estatutoAps', regimeFrom: '', shift: false, jefatura: null }),
				administrativeDays: () => ({ days: '' }),
				regime: () => ({ regime: this.person.regime === 'honorarios' ? 'estatutoAps' : 'honorarios', regimeFrom: '', reason: '' }),
			}[form]()
		},
		/** A Movement's quantity with its sign: +15, −0,5. */
		signed(quantity) {
			return quantity > 0 ? `+${number(quantity)}` : quantity < 0 ? `−${number(-quantity)}` : '0'
		},
		close() {
			this.form = null
		},
		stepText(step) {
			const regime = `${REGIMES[step.regime]} desde el ${longDay(step.from)}`
			return step.kind === 'completed' ? `Completó la ficha: ${regime}` : `Cambió el régimen: ${regime}`
		},
		async complete() {
			await this.save(() => ocsPost('people', {
				uid: this.entry.uid,
				rut: this.draft.rut,
				givenNames: this.draft.givenNames,
				surnames: this.draft.surnames,
				startDate: this.draft.startDate,
				priorMonths: Number.parseInt(this.draft.priorMonths, 10) || 0,
				shift: this.draft.shift,
				jefatura: this.draft.jefatura,
				regime: this.draft.regime,
				regimeFrom: this.draft.regimeFrom || this.draft.startDate,
			}))
		},
		async setAdministrativeDays() {
			await this.save(() => ocsPost(`people/${this.person.rut}/administrative-days`, {
				days: Number(String(this.draft.days).replace(',', '.')),
			}))
		},
		async changeRegime() {
			await this.save(() => ocsPost(`people/${this.person.rut}/regimes`, {
				regime: this.draft.regime,
				from: this.draft.regimeFrom,
				reason: this.draft.reason,
			}))
		},
		async save(call) {
			this.saving = true
			this.error = ''
			try {
				this.person = (await call()).person
				this.form = null
				this.$emit('saved')
			} catch (error) {
				this.error = refusal(error) ?? 'No se pudo guardar. Inténtalo de nuevo.'
			} finally {
				this.saving = false
			}
		},
	},
}
</script>

<style scoped>
.rh-sidebar {
	padding: 0 calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
}

.rh-form {
	display: flex;
	flex-direction: column;
	gap: calc(var(--default-grid-baseline) * 3);
}

.rh-buttons {
	display: flex;
	flex-wrap: wrap;
	justify-content: flex-end;
	gap: calc(var(--default-grid-baseline) * 2);
	margin-block: calc(var(--default-grid-baseline) * 3);
}

/* Label above value, whatever Nextcloud's own dl styles say. */
.rh-record dt,
.rh-record dd {
	display: block;
	float: none;
	width: auto;
	margin: 0;
	padding: 0;
	text-align: start;
}

.rh-record dt {
	margin-top: calc(var(--default-grid-baseline) * 3);
	color: var(--color-text-maxcontrast);
}

.rh-sidebar h3 {
	margin-block: calc(var(--default-grid-baseline) * 4) calc(var(--default-grid-baseline) * 2);
	font-size: var(--default-font-size);
	font-weight: bold;
}

.rh-trail {
	padding-inline-start: calc(var(--default-grid-baseline) * 5);
}

.rh-trail li + li {
	margin-top: calc(var(--default-grid-baseline) * 2);
}

.rh-ledger {
	margin: calc(var(--default-grid-baseline) * 3) 0;
	padding: 0;
	list-style: none;
}

.rh-ledger__row {
	display: grid;
	grid-template-columns: auto minmax(0, 1fr) auto;
	gap: var(--default-grid-baseline) calc(var(--default-grid-baseline) * 3);
	padding-block: calc(var(--default-grid-baseline) * 2);
	border-bottom: 1px solid var(--color-border);
}

.rh-ledger__quantity {
	font-weight: bold;
	text-align: end;
}

.rh-ledger__why {
	grid-column: 2 / -1;
}

.rh-totals {
	margin: 0 0 calc(var(--default-grid-baseline) * 3);
	padding: 0;
	list-style: none;
}
</style>
