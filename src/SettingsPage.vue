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
							<NcChip v-if="rule.establishment" text="Regla del establecimiento" no-close />
						</div>
						<div>{{ rule.summary }}</div>
						<div class="rh-hint">
							{{ rule.basis }} · vigente desde el {{ longDay(rule.from) }}
						</div>
					</div>
				</li>
			</ul>
		</section>
	</div>
</template>

<script>
import NcChip from '@nextcloud/vue/components/NcChip'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { ocsGet } from './api.js'
import { longDay } from './days.js'
import { mdiScaleBalance } from './icons.js'

/**
 * «Ajustes» (recursos-humanos#5): for now its «Normativa», the Rule versions the app applies,
 * read-only; the other sections arrive with their tickets.
 */
export default {
	name: 'SettingsPage',
	components: { NcChip, NcIconSvgWrapper, NcLoadingIcon, NcNoteCard },
	setup: () => ({ longDay, mdiScaleBalance }),
	data: () => ({
		state: 'loading',
		rules: [],
	}),
	async mounted() {
		try {
			this.rules = (await ocsGet('rules')).rules
			this.state = 'ready'
		} catch {
			this.state = 'failed'
		}
	},
}
</script>

<style scoped>
h3 {
	margin-block: calc(var(--default-grid-baseline) * 4) var(--default-grid-baseline);
	font-size: var(--default-font-size);
	font-weight: bold;
}

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
</style>
