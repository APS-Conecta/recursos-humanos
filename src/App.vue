<template>
	<NcContent app-name="recursos_humanos">
		<!-- Menu by task (recursos-humanos#1): everyone starts on «Mis días». The navigation lists only
			what works; each later ticket adds its entry, and its role's caption, here. -->
		<NcAppNavigation aria-label="Recursos Humanos">
			<NcAppNavigationNew text="Nueva solicitud" @click="requesting = true">
				<template #icon>
					<NcIconSvgWrapper :path="mdiPlus" />
				</template>
			</NcAppNavigationNew>
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
					<NcAppNavigationItem name="Ajustes" :active="view === 'settings'" @click="show('settings')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiCogOutline" />
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
			<SettingsPage v-else-if="view === 'settings'" />
			<MyDaysPage v-else-if="view === 'my-days'" />
			<EmptyPage v-else :title="current.name" :empty="current.empty" :icon="current.icon" />
		</NcAppContent>
		<PersonSidebar v-if="view === 'staff' && sidebar"
			:key="sidebar.uid"
			:entry="sidebar"
			:people="people"
			@close="sidebar = null"
			@saved="version++" />
		<RequestDialog v-if="requesting" @close="requesting = false" />
	</NcContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { ocsGet } from './api.js'
import EmptyPage from './EmptyPage.vue'
import { mdiAccountGroupOutline, mdiCalendarAccountOutline, mdiCogOutline, mdiFormatListBulleted, mdiPlus } from './icons.js'
import MyDaysPage from './MyDaysPage.vue'
import PersonSidebar from './PersonSidebar.vue'
import RequestDialog from './RequestDialog.vue'
import SettingsPage from './SettingsPage.vue'
import StaffPage from './StaffPage.vue'

/** Everyone's two pages: «Mis días» (MyDaysPage), and «Mis solicitudes», empty until Requests arrive (recursos-humanos#6). */
const ENTRIES = [
	{
		id: 'my-days',
		name: 'Mis días',
		icon: mdiCalendarAccountOutline,
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
	components: { EmptyPage, NcAppContent, NcAppNavigation, NcAppNavigationCaption, NcAppNavigationItem, NcAppNavigationNew, NcContent, NcIconSvgWrapper,
		MyDaysPage, PersonSidebar, RequestDialog, SettingsPage, StaffPage },
	setup: () => ({ ENTRIES, mdiAccountGroupOutline, mdiCogOutline, mdiPlus }),
	data: () => ({
		view: 'my-days',
		roles: { hrOfficer: false },
		/** «Personal»'s entries, for the Jefatura picker. */
		people: [],
		/** The «Personal» entry whose sidebar is open. */
		sidebar: null,
		version: 0,
		/** Whether «Nueva solicitud» is open. */
		requesting: false,
	}),
	computed: {
		current() {
			return ENTRIES.find((entry) => entry.id === this.view)
		},
		heading() {
			return { staff: 'Personal', settings: 'Ajustes' }[this.view] ?? this.current.name
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

/* A page's sections. */
.rh-page h3 {
	margin-block: calc(var(--default-grid-baseline) * 4) var(--default-grid-baseline);
	font-size: var(--default-font-size);
	font-weight: bold;
}

/* Every page's quieter text, and numbers that line up in a column. */
.rh-hint {
	color: var(--color-text-maxcontrast);
}

.rh-number {
	font-variant-numeric: tabular-nums;
}
</style>
