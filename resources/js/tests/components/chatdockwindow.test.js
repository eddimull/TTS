import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import { h } from 'vue';

// Direct import binding in ChatDockWindow's <script setup> — only a module
// mock intercepts it in both dev and prod builds (see messagesindex.test.js).
vi.mock('@/Components/Chat/ConversationThread.vue', () => ({
	default: {
		name: 'ConversationThread',
		props: ['loadUrl', 'currentUserId', 'noun'],
		emits: ['read'],
		render() {
			return h('div', { 'data-test': 'thread', 'data-url': this.loadUrl, 'data-noun': this.noun, onClick: () => this.$emit('read') });
		},
	},
}));
import ChatDockWindow from '@/Components/Chat/Dock/ChatDockWindow.vue';

const route = (name, params) => `/mock/${name}/${params}`;

const dm = { id: 5, type: 'dm', title: 'Taylor Campo', topic_type: null, unread_count: 0 };
const booking = { id: 6, type: 'topic', title: 'Smith Wedding', topic_type: 'booking', unread_count: 4 };

function mountWindow(conversation, extra = {}) {
	const onClose = vi.fn();
	const onToggle = vi.fn();
	const onRead = vi.fn();
	const wrapper = mount(ChatDockWindow, {
		props: { conversation, minimized: false, currentUserId: 10, onClose, onToggle, onRead, ...extra },
		// app.js installs `route` as a mixin so templates resolve `_ctx.route`.
		global: { mixins: [{ methods: { route } }] },
	});
	return { wrapper, onClose, onToggle, onRead };
}

describe('ChatDockWindow', () => {
	it('shows the title and subtitle and mounts the thread for the conversation', () => {
		const { wrapper } = mountWindow(booking);
		expect(wrapper.text()).toContain('Smith Wedding');
		expect(wrapper.text()).toContain('Booking thread');
		const thread = wrapper.find('[data-test="thread"]');
		expect(thread.attributes('data-url')).toBe('/mock/chat.conversations.messages.index/6');
		expect(thread.attributes('data-noun')).toBe('message');
	});

	it('unmounts the thread while minimised and shows the unread pill only then', async () => {
		const { wrapper } = mountWindow(booking);
		expect(wrapper.find('[data-test="window-unread-pill"]').exists()).toBe(false);
		await wrapper.setProps({ minimized: true });
		expect(wrapper.find('[data-test="thread"]').exists()).toBe(false);
		expect(wrapper.find('[data-test="window-unread-pill"]').text()).toBe('4');
	});

	it('no pill when minimised with nothing unread', async () => {
		const { wrapper } = mountWindow(dm, { minimized: true });
		expect(wrapper.text()).toContain('Direct message');
		expect(wrapper.find('[data-test="window-unread-pill"]').exists()).toBe(false);
	});

	it('header click and minimise button toggle; close button closes', async () => {
		const { wrapper, onClose, onToggle } = mountWindow(dm);
		await wrapper.find('[data-test="window-header"]').trigger('click');
		expect(onToggle).toHaveBeenCalledTimes(1);
		await wrapper.find('[data-test="window-minimize"]').trigger('click');
		expect(onToggle).toHaveBeenCalledTimes(2);
		await wrapper.find('[data-test="window-close"]').trigger('click');
		expect(onClose).toHaveBeenCalledTimes(1);
		expect(onToggle).toHaveBeenCalledTimes(2);
	});

	it('header is keyboard-operable', async () => {
		const { wrapper, onToggle } = mountWindow(dm);
		const header = wrapper.find('[data-test="window-header"]');
		expect(header.attributes('role')).toBe('button');
		expect(header.attributes('tabindex')).toBe('0');
		await header.trigger('keydown', { key: 'Enter' });
		await header.trigger('keydown', { key: ' ' });
		expect(onToggle).toHaveBeenCalledTimes(2);
	});

	it('relays read from the thread', async () => {
		const { wrapper, onRead } = mountWindow(dm);
		await wrapper.find('[data-test="thread"]').trigger('click');
		expect(onRead).toHaveBeenCalledTimes(1);
	});
});
