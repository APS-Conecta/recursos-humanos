<template>
	<NcContent app-name="recursos_humanos">
		<!-- Menu by task (recursos-humanos#1): everyone starts on «Mis días». The navigation lists only
			what works; each later ticket adds its entry, and its role's caption, here. -->
		<NcAppNavigation aria-label="Recursos Humanos">
			<template #list>
				<NcAppNavigationItem v-for="entry in entries"
					:key="entry.id"
					:name="entry.name"
					:active="view === entry.id"
					@click="view = entry.id">
					<template #icon>
						<NcIconSvgWrapper :path="entry.icon" />
					</template>
				</NcAppNavigationItem>
			</template>
		</NcAppNavigation>

		<NcAppContent :page-heading="current.name">
			<EmptyPage :title="current.name" :empty="current.empty" :icon="current.icon" />
		</NcAppContent>
	</NcContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import EmptyPage from './EmptyPage.vue'
import { mdiCalendarAccountOutline, mdiFormatListBulleted } from './icons.js'

/** Everyone's two pages, empty until the person's record is complete (recursos-humanos#3, #5). */
const ENTRIES = [
	{
		id: 'my-days',
		name: 'Mis días',
		icon: mdiCalendarAccountOutline,
		empty: {
			name: 'Tus días aparecerán aquí',
			description: 'Cuando Recursos Humanos complete tu ficha, verás tu feriado legal, tus días administrativos y tus otros permisos.',
		},
	},
	{
		id: 'my-requests',
		name: 'Mis solicitudes',
		icon: mdiFormatListBulleted,
		empty: {
			name: 'Todavía no tienes solicitudes',
			description: 'Cuando pidas días, aquí verás cada solicitud y en qué va.',
		},
	},
]

export default {
	name: 'App',
	components: { EmptyPage, NcAppContent, NcAppNavigation, NcAppNavigationItem, NcContent, NcIconSvgWrapper },
	setup: () => ({ entries: ENTRIES }),
	data: () => ({ view: 'my-days' }),
	computed: {
		current() {
			return ENTRIES.find((entry) => entry.id === this.view)
		},
	},
}
</script>
