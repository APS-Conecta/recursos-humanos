<template>
	<div class="rh-page">
		<h2>{{ title }}</h2>
		<NcEmptyContent :name="empty.name" :description="empty.description">
			<template #icon>
				<NcIconSvgWrapper :path="icon" />
			</template>
		</NcEmptyContent>
	</div>
</template>

<script>
import NcEmptyContent from '@nextcloud/vue/components/NcEmptyContent'
import NcIconSvgWrapper from '@nextcloud/vue/components/NcIconSvgWrapper'

/** A page that has nothing to show yet: its title and its empty state. */
export default {
	name: 'EmptyPage',
	components: { NcEmptyContent, NcIconSvgWrapper },
	props: {
		title: { type: String, required: true },
		empty: { type: Object, required: true },
		icon: { type: String, required: true },
	},
}
</script>

<style scoped>
/* NcAppNavigation's toggle sits in the content's top corner, open or folded: the title starts
   beside it, never under it (Estadística's heading, as «IA» uses it). */
.rh-page h2 {
	display: flex;
	align-items: center;
	min-height: var(--default-clickable-area);
	margin: var(--app-navigation-padding) 0 0;
	padding-inline: calc(var(--default-clickable-area) + var(--default-grid-baseline) * 3) 24px;
}
</style>
