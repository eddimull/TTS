import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { h } from 'vue';

// MessagesNavIcon imports Link directly in <script setup> (not resolved by
// bare tag name), so global.stubs can't intercept it under either the dev or
// the production Vue build. Mocking the module itself works in both, since
// it swaps what the `import` binds to at the loader level.
vi.mock('@inertiajs/vue3', () => ({
	Link: {
		props: ['href'],
		render() { return h('a', { href: this.href }, this.$slots.default?.()); },
	},
}));

import MessagesNavIcon from '@/Components/Chat/MessagesNavIcon.vue';

describe('MessagesNavIcon', () => {
	it('links to the inbox and hides the pill at zero', () => {
		const w = mount(MessagesNavIcon, { props: { count: 0, href: '/messages' } });
		expect(w.find('a').attributes('href')).toBe('/messages');
		expect(w.find('[data-test="chat-unread-pill"]').exists()).toBe(false);
		expect(w.find('a').attributes('aria-label')).toBe('Messages');
	});

	it('shows the count in the pill and the aria label', () => {
		const w = mount(MessagesNavIcon, { props: { count: 4, href: '/messages' } });
		expect(w.find('[data-test="chat-unread-pill"]').text()).toBe('4');
		expect(w.find('a').attributes('aria-label')).toBe('Messages, 4 unread');
	});
});
