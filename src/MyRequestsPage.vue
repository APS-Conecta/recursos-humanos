<template>
	<div class="rh-page">
		<h2>Mis solicitudes</h2>
		<p class="rh-hint">
			Cada solicitud, en qué va y quién la decidió. El saldo cambia recién cuando Recursos Humanos la registra.
		</p>
		<NcLoadingIcon v-if="state === 'loading'" />
		<NcNoteCard v-else-if="state === 'failed'" type="error">
			No se pudieron cargar tus solicitudes. Recarga la página; si vuelve a ocurrir, avisa al administrador.
		</NcNoteCard>
		<NcEmptyContent v-else-if="requests.length === 0"
			name="Todavía no tienes solicitudes"
			description="Cuando pidas días, aquí verás cada solicitud y en qué va.">
			<template #icon>
				<NcIconSvgWrapper :path="mdiFormatListBulleted" />
			</template>
		</NcEmptyContent>
		<ul v-else class="rh-requests">
			<NcListItem v-for="request in requests"
				:key="request.id"
				:name="typeName(request)"
				:active="request.id === selected"
				@click.prevent="$emit('open', request)">
				<template #icon>
					<NcIconSvgWrapper :path="LEAVE_TYPES[request.leaveType].icon" />
				</template>
				<template #subname>
					{{ rangeText(request) }} · {{ number(request.days) }} {{ daysWord(request.days, request.unit) }}
				</template>
				<template #details>
					<NcChip :text="STATES[request.state].name" :variant="STATES[request.state].variant" no-close />
				</template>
			</NcListItem>
		</ul>
	</div>
</template>

<script>
import NcChip from '@nextcloud/vue/components/NcChip'
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import NcListItem from '@nextcloud/vue/components/NcListItem'
import NcLoadingIcon from '@nextcloud/vue/components/NcLoadingIcon'
import NcNoteCard from '@nextcloud/vue/components/NcNoteCard'
import { ocsGet } from './api.js'
import { daysWord, number } from './days.js'
import { mdiFormatListBulleted } from './icons.js'
import { LEAVE_TYPES } from './ledger.js'
import { STATES, rangeText, typeName } from './requests.js'

/** «Mis solicitudes» (recursos-humanos#6): the person's own Requests, newest first; one opens in its sidebar. */
export default {
	name: 'MyRequestsPage',
	components: { NcChip, NcEmptyContent, NcIconSvgWrapper, NcListItem, NcLoadingIcon, NcNoteCard },
	props: {
		/** The id of the Request whose sidebar is open. */
		selected: { type: Number, default: null },
		/** Bumped when a Request changes, to read the list again. */
		version: { type: Number, default: 0 },
	},
	emits: ['open'],
	setup: () => ({ LEAVE_TYPES, STATES, daysWord, mdiFormatListBulleted, number, rangeText, typeName }),
	data: () => ({
		state: 'loading',
		requests: [],
	}),
	watch: {
		version: 'load',
	},
	mounted() {
		this.load()
	},
	methods: {
		async load() {
			try {
				this.requests = (await ocsGet('requests')).requests
				this.state = 'ready'
			} catch {
				this.state = 'failed'
			}
		},
	},
}
</script>

<style scoped>
.rh-requests {
	max-width: 720px;
	margin-block: calc(var(--default-grid-baseline) * 3);
	padding: 0;
}
</style>
