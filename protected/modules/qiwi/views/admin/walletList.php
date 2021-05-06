<?php
/**
 *
 */
$this->title = 'Список всех кошей';
?>

<div id="dynamic-component" class="list">
	<button
		v-for="tab in tabs"
		v-bind:key="tab.caption"
		v-bind:class="['tab-button', { active: currentTab === tab }]"
		v-on:click="currentTab = tab.content"
	>{{ tab.title }}</button>
	<component
		v-bind:is="currentTabComponent"
		class="tab"
	></component>
</div>

<script>
	new Vue({
		el: '#dynamic-component',
		data: function () {
			return {
				tabs: [
					{
						caption: 'all',
						title: 'Показать все ',
						content: 'показать все'
					},
					{
						caption: 'free',
						title: 'Только свободные ',
						content: 'Только свободные '
					},
					{
						caption: 'busy',
						title: 'Только присвоенные',
						content: 'Только присвоенные'
					},
					{
						caption: 'new',
						title: 'Только новые',
						content: 'Только новые'
					}
				],
				currentTab: 'Выберите раздел сверху'
			}
		},
		computed: {
			currentTabComponent: function () {
				return Vue.component('tab-' + this.currentTab.toLowerCase(), {
					template: '<div>' this.currentTab + '</div>'`
				})
			}
		}
	})
</script>
