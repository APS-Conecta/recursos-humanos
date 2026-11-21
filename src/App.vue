<template>
	<NcContent app-name="recursos_humanos">
		<!-- Menu by task (recursos-humanos#1): everyone starts on «Mis días». The navigation lists only
			what works; each later ticket adds its entry, and its role's caption, here. -->
		<NcAppNavigation aria-label="Recursos Humanos">
			<template #list>
				<NcAppNavigationItem v-for="entry in ENTRIES"
					:key="entry.id"
					:name="entry.name"
					:active="view === entry.id"
					@click="show(entry.id)">
					<template #icon>
						<NcIconSvgWrapper :path="entry.icon" />
					</template>
				</NcAppNavigationItem>
				<template v-if="roles.hrOfficer">
					<NcAppNavigationCaption name="Recursos Humanos" />
					<NcAppNavigationItem name="Personal" :active="view === 'staff'" @click="show('staff')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiAccountGroupOutline" />
						</template>
					</NcAppNavigationItem>
				</template>
			</template>
		</NcAppNavigation>

		<NcAppContent :page-heading="heading">
			<StaffPage v-if="view === 'staff'"
				:selected="sidebar?.uid ?? null"
				:version="version"
				@open="sidebar = $event"
				@loaded="people = $event" />
			<EmptyPage v-else :title="current.name" :empty="current.empty" :icon="current.icon" />
		</NcAppContent>
		<PersonSidebar v-if="view === 'staff' && sidebar"
			:key="sidebar.uid"
			:entry="sidebar"
			:people="people"
			@close="sidebar = null"
			@saved="version++" />
	</NcContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { ocsGet } from './api.js'
import EmptyPage from './EmptyPage.vue'
import { mdiAccountGroupOutline, mdiCalendarAccountOutline, mdiFormatListBulleted } from './icons.js'
import PersonSidebar from './PersonSidebar.vue'
import StaffPage from './StaffPage.vue'

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
	components: { EmptyPage, NcAppContent, NcAppNavigation, NcAppNavigationCaption, NcAppNavigationItem, NcContent, NcIconSvgWrapper, PersonSidebar, StaffPage },
	setup: () => ({ ENTRIES, mdiAccountGroupOutline }),
	data: () => ({
		view: 'my-days',
		roles: { hrOfficer: false },
		/** «Personal»'s entries, for the Jefatura picker. */
		people: [],
		/** The «Personal» entry whose sidebar is open. */
		sidebar: null,
		version: 0,
	}),
	computed: {
		current() {
			return ENTRIES.find((entry) => entry.id === this.view)
		},
		heading() {
			return this.view === 'staff' ? 'Personal' : this.current.name
		},
	},
	async mounted() {
		try {
			this.roles = (await ocsGet('me')).roles
		} catch {
			// Without the roles the menu keeps everyone's pages, which need none.
		}
	},
	methods: {
		show(view) {
			this.view = view
			this.sidebar = null
		},
	},
}
</script>

<style>
/* NcAppNavigation's toggle sits in the content's top corner, open or folded: a page's title and
   its content start beside it, never under it (Estadística's heading, as «IA» uses it). */
.rh-page {
	padding-inline: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 3) 24px;
}

.rh-page h2 {
	display: flex;
	align-items: center;
	min-height: var(--default-clickable-area);
	margin: var(--app-navigation-padding) 0 0;
}

/* Every page's quieter text, and numbers that line up in a column. */
.rh-hint {
	color: var(--color-text-maxcontrast);
}

.rh-number {
	font-variant-numeric: tabular-nums;
}
</style>
