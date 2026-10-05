import { describe, it, expect } from 'vitest';
import { mount } from '@vue/test-utils';
import MessagesNavIcon from '@/Components/Chat/MessagesNavIcon.vue';

const stubs = { Link: { props: ['href'], template: '<a :href="href"><slot /></a>' } };

describe('MessagesNavIcon', () => {
	it('links to the inbox and hides the pill at zero', () => {
		const w = mount(MessagesNavIcon, { props: { count: 0, href: '/messages' }, global: { stubs } });
		expect(w.find('a').attributes('href')).toBe('/messages');
		expect(w.find('[data-test="chat-unread-pill"]').exists()).toBe(false);
		expect(w.find('a').attributes('aria-label')).toBe('Messages');
	});

	it('shows the count in the pill and the aria label', () => {
		const w = mount(MessagesNavIcon, { props: { count: 4, href: '/messages' }, global: { stubs } });
		expect(w.find('[data-test="chat-unread-pill"]').text()).toBe('4');
		expect(w.find('a').attributes('aria-label')).toBe('Messages, 4 unread');
	});
});
