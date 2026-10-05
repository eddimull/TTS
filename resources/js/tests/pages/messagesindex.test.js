import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { nextTick, reactive } from 'vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
const storeState = reactive({ user: { chatSignal: 0 } });
const dispatch = vi.fn();
// mapState/mapActions are no-ops here — they're only needed because importing the
// page transitively imports BreezeAuthenticatedLayout (via defineOptions({ layout })),
// which uses them in its Options API; the layout itself plays no role in this test.
vi.mock('vuex', () => ({
	useStore: () => ({ state: storeState, dispatch }),
	mapState: () => ({}),
	mapActions: () => ({}),
}));
vi.mock('@inertiajs/vue3', () => ({
	usePage: () => ({ props: { auth: { user: { id: 10 } } } }),
	Head: { template: '<div />' },
}));
import axios from 'axios';
import MessagesIndex from '@/Pages/Messages/Index.vue';

const rows = [
	{ id: 1, type: 'band', title: 'Three Thirty Seven', topic_type: null, last_message_preview: 'gig!', last_message_at: '2026-10-05T10:00:00+00:00', unread_count: 2, can_moderate: true, band_id: 1 },
	{ id: 2, type: 'dm', title: 'Taylor Campo', topic_type: null, last_message_preview: 'yo', last_message_at: '2026-10-05T09:00:00+00:00', unread_count: 0, can_moderate: false, band_id: null },
];

const stubs = {
	// Container is globally registered at runtime via app.js; stub it here since
	// this test mounts the page in isolation (brief Step 3 note).
	Container: { template: '<div><slot /></div>' },
	ConversationThread: { props: ['loadUrl', 'currentUserId'], template: '<div data-test="thread" :data-url="loadUrl" @click="$emit(\'read\')" />' },
	// Explicit `name` so `findComponent({ name: 'NewMessageDialog' })` can match the stub.
	NewMessageDialog: { name: 'NewMessageDialog', props: ['visible'], template: '<div data-test="dialog" :data-visible="visible" />' },
};

// Wrappers from every mountPage() call in the current test, unmounted in
// afterEach. Without this, a previous test's instance stays mounted and its
// `watch(() => store.state.user.chatSignal, ...)` keeps firing against the
// module-level shared `storeState`/`axios` mocks, racing the current test's
// own axios.get call against leftover `mockResolvedValueOnce` queues.
let wrappers = [];

function mountPage(props = {}) {
	const w = mount(MessagesIndex, {
		props: { conversations: rows, initialConversationId: null, ...props },
		// `route` is a real global at runtime (ziggy-js), and app.js additionally
		// installs it as a mixin method so Options API templates (`_ctx.route`)
		// can see it. `vi.stubGlobal` covers the global; this mixin reproduces
		// the same `_ctx.route` resolution the real app provides, for the one
		// call inside this page's own template.
		global: { stubs, mixins: [{ methods: { route } }] },
	});
	wrappers.push(w);
	return w;
}

describe('Messages/Index', () => {
	beforeEach(() => {
		axios.get.mockReset(); axios.post.mockReset(); dispatch.mockReset();
		storeState.user.chatSignal = 0;
		vi.stubGlobal('route', (name, p) => `/r/${name}/${p ?? ''}`);
		vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
		axios.post.mockResolvedValue({ data: {} });
	});

	afterEach(() => {
		wrappers.forEach((w) => w.unmount());
		wrappers = [];
	});

	it('renders rows and the empty thread state', () => {
		const w = mountPage();
		expect(w.text()).toContain('Three Thirty Seven');
		expect(w.text()).toContain('Select a conversation');
		expect(w.find('[data-test="thread"]').exists()).toBe(false);
	});

	it('posts the delivered ack on mount', async () => {
		mountPage();
		await flushPromises();
		expect(axios.post).toHaveBeenCalledWith('/r/chat.conversations.delivered/');
	});

	it('selecting a row mounts the thread for it and rewrites the URL', async () => {
		const w = mountPage();
		await w.findAll('button[aria-current], button').filter((b) => b.text().includes('Taylor Campo'))[0].trigger('click');
		await nextTick();
		expect(w.find('[data-test="thread"]').attributes('data-url')).toBe('/r/chat.conversations.messages.index/2');
		expect(window.history.replaceState).toHaveBeenCalledWith(null, '', '/r/messages.index/2');
		expect(w.text()).toContain('Direct message');
	});

	it('preselects initialConversationId', () => {
		const w = mountPage({ initialConversationId: 1 });
		expect(w.find('[data-test="thread"]').attributes('data-url')).toBe('/r/chat.conversations.messages.index/1');
		expect(w.text()).toContain('Band channel');
	});

	it('refreshes the list when the store signal changes', async () => {
		axios.get.mockResolvedValueOnce({ data: { conversations: [{ ...rows[1], unread_count: 5 }] } });
		const w = mountPage();
		await flushPromises();
		storeState.user.chatSignal += 1;
		await flushPromises();
		expect(axios.get).toHaveBeenCalledWith('/r/chat.conversations.index/');
		expect(w.find('[data-test="unread-pill"]').text()).toBe('5');
	});

	it('read from the thread zeroes that row and refetches the badge', async () => {
		const w = mountPage({ initialConversationId: 1 });
		expect(w.find('[data-test="unread-pill"]').text()).toBe('2');
		await w.find('[data-test="thread"]').trigger('click');
		await nextTick();
		expect(w.find('[data-test="unread-pill"]').exists()).toBe(false);
		expect(dispatch).toHaveBeenCalledWith('user/fetchChatUnread');
	});

	it('inserts and selects a conversation created from the dialog', async () => {
		const w = mountPage();
		await w.find('[data-test="new-message"]').trigger('click');
		expect(w.find('[data-test="dialog"]').attributes('data-visible')).toBe('true');
		w.findComponent({ name: 'NewMessageDialog' }).vm.$emit('created', { id: 99, type: 'dm', title: 'New Person', unread_count: 0, last_message_at: null, last_message_preview: null, topic_type: null, band_id: null, can_moderate: false });
		await nextTick();
		expect(w.text()).toContain('New Person');
		expect(w.find('[data-test="thread"]').attributes('data-url')).toBe('/r/chat.conversations.messages.index/99');
	});
});
