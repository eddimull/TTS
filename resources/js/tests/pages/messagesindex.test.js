import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { h, nextTick, reactive } from 'vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
// vi.mock factories are hoisted above all imports/const declarations, so a
// variable they close over must itself be declared via vi.hoisted().
const { toastAdd } = vi.hoisted(() => ({ toastAdd: vi.fn() }));
vi.mock('primevue/usetoast', () => ({ useToast: () => ({ add: toastAdd }) }));
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
// ConversationThread and NewMessageDialog are imported directly in
// Index.vue's <script setup> (not resolved by bare tag name), so neither
// `global.stubs` (dev-mode-only; see the component-level comment below) nor
// `global.components` (tag-resolution only) can intercept them — a direct
// import-binding vnode type bypasses both mechanisms entirely, in both dev
// and prod builds. Mocking the module itself works in both, since it swaps
// what the `import` binds to at the loader level, orthogonal to Vue's
// render/compile internals.
vi.mock('@/Components/Chat/ConversationThread.vue', () => ({
	default: {
		name: 'ConversationThread',
		props: ['loadUrl', 'currentUserId'],
		emits: ['read'],
		render() {
			return h('div', { 'data-test': 'thread', 'data-url': this.loadUrl, onClick: () => this.$emit('read') });
		},
	},
}));
vi.mock('@/Pages/Messages/Components/NewMessageDialog.vue', () => ({
	default: {
		// Explicit `name` so `findComponent({ name: 'NewMessageDialog' })` can match the stub.
		name: 'NewMessageDialog',
		props: ['visible'],
		emits: ['update:visible', 'created'],
		render() {
			return h('div', { 'data-test': 'dialog', 'data-visible': String(this.visible) });
		},
	},
}));
import axios from 'axios';
import MessagesIndex from '@/Pages/Messages/Index.vue';

const rows = [
	{ id: 1, type: 'band', title: 'Three Thirty Seven', topic_type: null, last_message_preview: 'gig!', last_message_at: '2026-10-05T10:00:00+00:00', unread_count: 2, can_moderate: true, band_id: 1 },
	{ id: 2, type: 'dm', title: 'Taylor Campo', topic_type: null, last_message_preview: 'yo', last_message_at: '2026-10-05T09:00:00+00:00', unread_count: 0, can_moderate: false, band_id: null },
];

// Container is globally registered at runtime via app.js (referenced by bare
// tag, no local import) and resolved via Vue's `resolveComponent`, which
// consults the app's component registry in both dev and prod builds — so
// `global.components` (not `global.stubs`, which is dev-mode-only; see the
// module mocks above) reaches it correctly in either mode. Render function,
// not a template string, so it doesn't depend on any particular compile path.
const components = {
	Container: { name: 'Container', render() { return h('div', this.$slots.default?.()); } },
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
		global: {
			components,
			// `route` is a real global at runtime (ziggy-js), and app.js additionally
			// installs it as a mixin method so Options API templates (`_ctx.route`)
			// can see it. `vi.stubGlobal` covers the global; this mixin reproduces
			// the same `_ctx.route` resolution the real app provides, for the one
			// call inside this page's own template.
			mixins: [{ methods: { route } }],
		},
	});
	wrappers.push(w);
	return w;
}

describe('Messages/Index', () => {
	beforeEach(() => {
		axios.get.mockReset(); axios.post.mockReset(); dispatch.mockReset(); toastAdd.mockReset();
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

	it('selecting a row mounts the thread for it and rewrites the URL while preserving Inertia history state', async () => {
		// beforeEach already mocked replaceState as a no-op; restore the real
		// implementation to make this call observable, capture the resulting
		// state, then re-mock so the assertion below can inspect the call args.
		window.history.replaceState.mockRestore();
		window.history.replaceState({ page: 'sentinel' }, '', '/');
		const sentinelState = window.history.state;
		vi.spyOn(window.history, 'replaceState').mockImplementation(() => {});
		const w = mountPage();
		await w.findAll('button[aria-current], button').filter((b) => b.text().includes('Taylor Campo'))[0].trigger('click');
		await nextTick();
		expect(w.find('[data-test="thread"]').attributes('data-url')).toBe('/r/chat.conversations.messages.index/2');
		expect(window.history.replaceState).toHaveBeenCalledWith(sentinelState, '', '/r/messages.index/2');
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

	it('toasts once when the list refresh fails and keeps the rows', async () => {
		axios.get.mockRejectedValueOnce(new Error('network down'));
		axios.get.mockRejectedValueOnce(new Error('still down'));
		const w = mountPage();
		await flushPromises();

		storeState.user.chatSignal += 1;
		await flushPromises();
		storeState.user.chatSignal += 1;
		await flushPromises();

		expect(toastAdd).toHaveBeenCalledTimes(1);
		expect(toastAdd).toHaveBeenCalledWith(expect.objectContaining({ severity: 'warn', summary: 'Could not refresh messages' }));
		expect(w.text()).toContain('Three Thirty Seven');
		expect(w.text()).toContain('Taylor Campo');
	});

	it('explains a deep-linked conversation that is not yet in the inbox list', () => {
		const w = mountPage({ initialConversationId: 999 });
		expect(w.find('[data-test="unlisted-conversation"]').exists()).toBe(true);
		expect(w.text()).not.toContain('Select a conversation');
		expect(w.find('[data-test="thread"]').exists()).toBe(false);
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
