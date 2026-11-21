<template>
	<div class="rh-page">
		<h2>Personal</h2>
		<NcLoadingIcon v-if="state === 'loading'" />
		<NcNoteCard v-else-if="state === 'failed'" type="error">
			No se pudo cargar «Personal». Recarga la página; si vuelve a ocurrir, avisa al administrador.
		</NcNoteCard>
		<template v-else>
			<p class="rh-hint">
				{{ summary }}
			</p>
			<NcTextField v-model="query" class="rh-search" label="Buscar por nombre, RUT o cargo" />
			<div class="rh-table-wrap">
				<table class="rh-table">
					<thead>
						<tr>
							<th>Persona</th>
							<th>RUT</th>
							<th>Régimen</th>
							<th>Jefatura directa</th>
						</tr>
					</thead>
					<tbody>
						<tr v-for="person in shown"
							:key="person.uid"
							:class="{ 'rh-selected': person.uid === selected }"
							tabindex="0"
							:aria-label="person.name"
							@click="$emit('open', person)"
							@keydown.enter="$emit('open', person)">
							<td>
								<div>{{ person.name }}</div>
								<div v-if="person.cargos.length > 0" class="rh-hint">
									{{ cargoLine(person.cargos) }}
								</div>
								<NcChip v-if="!person.complete" text="Falta completar" variant="warning" no-close />
							</td>
							<td class="rh-number">
								{{ person.rut ? dottedRut(person.rut) : '—' }}
							</td>
							<td>
								<NcChip v-if="person.regime"
									:text="REGIMES[person.regime]"
									:variant="person.regime === 'honorarios' ? 'warning' : 'secondary'"
									no-close />
								<span v-else>—</span>
							</td>
							<td>
								<span v-if="person.jefatura">{{ person.jefatura.name }}</span>
								<span v-else-if="person.complete" class="rh-hint">Sin jefatura: decide el Director/a</span>
								<span v-else>—</span>
							</td>
						</tr>
						<tr v-if="shown.length === 0">
							<td colspan="4" class="rh-hint">
								Nadie coincide con la búsqueda.
							</td>
						</tr>
					</tbody>
				</table>
			</div>
		</template>
	</div>
</template>

<script>
import NcChip from '@nextcloud/vue/components/NcChip'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import NcTextField from '@nextcloud/vue/components/NcTextField'
import { ocsGet } from './api.js'
import { textMatches } from './aps/filters.js'
import { REGIMES, cargoLine, dottedRut } from './people.js'

/** «Personal» for HR (recursos-humanos#3): every account and its record, searched by name, RUT or cargo. */
export default {
	name: 'StaffPage',
	components: { NcChip, NcLoadingIcon, NcNoteCard, NcTextField },
	props: {
		/** The uid whose sidebar is open, if any. */
		selected: { type: String, default: null },
		/** Bumped after a change, to load the list again. */
		version: { type: Number, default: 0 },
	},
	emits: ['open', 'loaded'],
	setup: () => ({ REGIMES, cargoLine, dottedRut }),
	data: () => ({ state: 'loading', people: [], query: '' }),
	computed: {
		shown() {
			return this.people.filter((person) => textMatches(this.query,
				[person.name, person.givenNames, person.surnames, person.rut, person.rut && dottedRut(person.rut), ...person.cargos]))
		},
		summary() {
			const incomplete = this.people.filter((person) => !person.complete).length
			return `${this.people.length} ${this.people.length === 1 ? 'persona' : 'personas'} · el registro oficial del establecimiento`
				+ (incomplete > 0 ? ` · ${incomplete} con «Falta completar»` : '')
		},
	},
	watch: {
		version() {
			this.load()
		},
	},
	mounted() {
		this.load()
	},
	methods: {
		async load() {
			try {
				this.people = (await ocsGet('people')).people
				this.state = 'ready'
				this.$emit('loaded', this.people)
			} catch {
				this.state = 'failed'
			}
		},
	},
}
</script>

<style scoped>
.rh-search {
	max-width: 340px;
	margin-block: calc(var(--default-grid-baseline) * 2);
}

.rh-table-wrap {
	overflow-x: auto;
}

.rh-table {
	width: 100%;
	border-collapse: collapse;
}

.rh-table th,
.rh-table td {
	padding: calc(var(--default-grid-baseline) * 2);
	border-bottom: 1px solid var(--color-border);
	text-align: start;
	vertical-align: top;
}

.rh-table tbody tr {
	cursor: pointer;
}

.rh-table tbody tr:hover,
.rh-table tbody tr:focus-visible,
.rh-table tbody tr.rh-selected {
	background-color: var(--color-background-hover);
}

.rh-number {
	white-space: nowrap;
}
</style>
