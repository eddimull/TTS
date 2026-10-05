import { describe, it, expect, afterEach } from 'vitest';
import { defineComponent, h, ref, nextTick } from 'vue';
import { mount } from '@vue/test-utils';
import { useCommentsDrawer } from '../../composables/useCommentsDrawer';

function mountWith(initialUnread, search) {
	window.history.replaceState({}, '', `/events/abc${search}`);
	const unreadProp = ref(initialUnread);
	let api;
	const Host = defineComponent({
		setup() {
			api = useCommentsDrawer(unreadProp);
			return () => h('div', [
				h('span', { 'data-test': 'open' }, String(api.open.value)),
				h('span', { 'data-test': 'unread' }, String(api.unread.value)),
			]);
		},
	});
	const wrapper = mount(Host);
	return { wrapper, api, unreadProp };
}

describe('useCommentsDrawer', () => {
	afterEach(() => window.history.replaceState({}, '', '/'));

	it('starts closed and mirrors the unread prop', async () => {
		const { wrapper, unreadProp } = mountWith(2, '');
		expect(wrapper.find('[data-test="open"]').text()).toBe('false');
		expect(wrapper.find('[data-test="unread"]').text()).toBe('2');
		unreadProp.value = 5;
		await nextTick();
		expect(wrapper.find('[data-test="unread"]').text()).toBe('5');
	});

	it('auto-opens when the URL carries comments=1', () => {
		const { wrapper } = mountWith(0, '?comments=1');
		expect(wrapper.find('[data-test="open"]').text()).toBe('true');
	});

	it('openDrawer opens; onRead zeroes the local unread count', async () => {
		const { wrapper, api } = mountWith(4, '');
		api.openDrawer();
		api.onRead();
		await nextTick();
		expect(wrapper.find('[data-test="open"]').text()).toBe('true');
		expect(wrapper.find('[data-test="unread"]').text()).toBe('0');
	});

	it('treats a null prop (no access) as zero', () => {
		const { wrapper } = mountWith(null, '');
		expect(wrapper.find('[data-test="unread"]').text()).toBe('0');
	});
});
