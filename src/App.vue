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
				<template v-if="roles.jefatura || roles.director">
					<NcAppNavigationCaption name="Jefatura" />
					<NcAppNavigationItem name="Por decidir" :active="view === 'decisions'" @click="show('decisions')">
						<template #icon>
							<NcIconSvgWrapper :path="mdiAccountCheckOutline" />
						</template>
						<template v-if="toDecide > 0" #counter>
							<NcCounterBubble :count="toDecide" type="highlighted" />
						</template>
					</NcAppNavigationItem>
				</template>
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

		<NcAppContent :page-heading="HEADINGS[view]">
			<StaffPage v-if="view === 'staff'"
				:selected="sidebar?.uid ?? null"
				:version="version"
				@open="sidebar = $event"
				@loaded="people = $event" />
			<SettingsPage v-else-if="view === 'settings'" />
			<DecisionsPage v-else-if="view === 'decisions'" @decided="countToDecide" />
			<MyRequestsPage v-else-if="view === 'my-requests'"
				:selected="request?.id ?? null"
				:version="version"
				@open="request = $event" />
			<MyDaysPage v-else />
		</NcAppContent>
		<PersonSidebar v-if="view === 'staff' && sidebar"
			:key="sidebar.uid"
			:entry="sidebar"
			:people="people"
			@close="sidebar = null"
			@saved="version++" />
		<RequestSidebar v-if="view === 'my-requests' && request"
			:key="request.id"
			:request="request"
			:viewer="uid"
			@close="request = null" />
		<RequestDialog v-if="requesting"
			:decider="person?.decider ?? null"
			@close="requesting = false"
			@sent="sent" />
	</NcContent>
</template>

<script>
import NcAppContent from '@nextcloud/vue/components/NcAppContent'
import NcAppNavigation from '@nextcloud/vue/components/NcAppNavigation'
import NcAppNavigationCaption from '@nextcloud/vue/components/NcAppNavigationCaption'
import NcAppNavigationItem from '@nextcloud/vue/components/NcAppNavigationItem'
import NcAppNavigationNew from '@nextcloud/vue/components/NcAppNavigationNew'
import NcContent from '@nextcloud/vue/components/NcContent'
import NcCounterBubble from '@nextcloud/vue/components/NcCounterBubble'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'
import { ocsGet } from './api.js'
import DecisionsPage from './DecisionsPage.vue'
import { mdiAccountCheckOutline, mdiAccountGroupOutline, mdiCalendarAccountOutline, mdiCogOutline, mdiFormatListBulleted, mdiPlus } from './icons.js'
import MyDaysPage from './MyDaysPage.vue'
import MyRequestsPage from './MyRequestsPage.vue'
import PersonSidebar from './PersonSidebar.vue'
import RequestDialog from './RequestDialog.vue'
import RequestSidebar from './RequestSidebar.vue'
import SettingsPage from './SettingsPage.vue'
import StaffPage from './StaffPage.vue'

/** Everyone's two pages: «Mis días» (MyDaysPage) and «Mis solicitudes» (MyRequestsPage). */
const ENTRIES = [
	{ id: 'my-days', name: 'Mis días', icon: mdiCalendarAccountOutline },
	{ id: 'my-requests', name: 'Mis solicitudes', icon: mdiFormatListBulleted },
]

/** Each page's heading. */
const HEADINGS = { 'my-days': 'Mis días', 'my-requests': 'Mis solicitudes', decisions: 'Por decidir', staff: 'Personal', settings: 'Ajustes' }

/** The pages a link may open by its #fragment, as the notifications do (Notifier). */
const LINKS = { 'mis-dias': 'my-days', 'mis-solicitudes': 'my-requests', 'por-decidir': 'decisions' }

export default {
	name: 'App',
	components: { DecisionsPage, MyDaysPage, MyRequestsPage, NcAppContent, NcAppNavigation, NcAppNavigationCaption, NcAppNavigationItem, NcAppNavigationNew,
		NcContent, NcCounterBubble, NcIconSvgWrapper, PersonSidebar, RequestDialog, RequestSidebar, SettingsPage, StaffPage },
	setup: () => ({ ENTRIES, HEADINGS, mdiAccountCheckOutline, mdiAccountGroupOutline, mdiCogOutline, mdiPlus }),
	data: () => ({
		view: LINKS[window.location.hash.slice(1)] ?? 'my-days',
		uid: '',
		roles: { hrOfficer: false, director: false, jefatura: false },
		/** The viewer's own record, for who decides their Requests; null until HR completes it. */
		person: null,
		/** How many Requests wait for the viewer's decision. */
		toDecide: 0,
		/** «Personal»'s entries, for the Jefatura picker. */
		people: [],
		/** The «Personal» entry whose sidebar is open. */
		sidebar: null,
		/** The Request whose sidebar is open. */
		request: null,
		version: 0,
		/** Whether «Nueva solicitud» is open. */
		requesting: false,
	}),
	async mounted() {
		try {
			const me = await ocsGet('me')
			this.uid = me.uid
			this.roles = me.roles
			this.person = me.person
		} catch {
			// Without the roles the menu keeps everyone's pages, which need none.
		}
		this.countToDecide()
	},
	methods: {
		show(view) {
			this.view = view
			this.sidebar = null
			this.request = null
		},
		async countToDecide() {
			if (!this.roles.jefatura && !this.roles.director) {
				return
			}
			try {
				this.toDecide = (await ocsGet('decisions')).requests.length
			} catch {
				// The counter is a hint; «Por decidir» itself says when it cannot load.
			}
		},
		/** A Request just sent opens in «Mis solicitudes», with its sidebar. */
		sent(request) {
			this.requesting = false
			this.show('my-requests')
			this.request = request
			this.version++
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

/* Every page's quieter text, its refusals, and numbers that line up in a column. */
.rh-hint {
	color: var(--color-text-maxcontrast);
}

.rh-error {
	color: var(--color-text-error);
}

.rh-number {
	font-variant-numeric: tabular-nums;
}

/* A sidebar's body, and its sections. */
.rh-sidebar {
	padding: 0 calc(var(--default-grid-baseline) * 3) calc(var(--default-grid-baseline) * 4);
}

.rh-sidebar h3 {
	margin-block: calc(var(--default-grid-baseline) * 4) calc(var(--default-grid-baseline) * 2);
	font-size: var(--default-font-size);
	font-weight: bold;
}

/* A record's facts, label above value, whatever Nextcloud's own dl styles say. */
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

/* A record's or a Request's trail, oldest step first. */
.rh-trail {
	padding-inline-start: calc(var(--default-grid-baseline) * 5);
}

.rh-trail li + li {
	margin-top: calc(var(--default-grid-baseline) * 2);
}
</style>
