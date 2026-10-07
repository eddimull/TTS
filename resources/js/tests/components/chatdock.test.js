import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { h, nextTick, reactive } from 'vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
const page = reactive({ component: 'Dashboard', props: { auth: { user: { id: 10, name: 'Me' } } } });
vi.mock('@inertiajs/vue3', () => ({ usePage: () => page }));
const storeState = reactive({ user: { chatSignal: 0, chatUnread: 0 } });
const dispatch = vi.fn();
vi.mock('vuex', () => ({ useStore: () => ({ state: storeState, dispatch }) }));
// Direct import bindings inside <script setup> — module mocks are the only
// thing that intercepts them in both dev and prod builds.
vi.mock('@/Pages/Messages/Components/ConversationList.vue', () => ({
	default: {
		name: 'ConversationList',
		props: ['conversations', 'selectedId', 'showNew'],
		emits: ['select', 'new'],
		render() {
			return h('div', { 'data-test': 'list', 'data-show-new': String(this.showNew) },
				this.conversations.map((c) => h('button', { 'data-test': `row-${c.id}`, onClick: () => this.$emit('select', c.id) }, c.title)));
		},
	},
}));
vi.mock('@/Components/Chat/ConversationThread.vue', () => ({
	default: {
		name: 'ConversationThread',
		props: ['loadUrl', 'currentUserId', 'noun'],
		emits: ['read'],
		render() {
			return h('div', { 'data-test': 'thread', 'data-url': this.loadUrl, onClick: () => this.$emit('read') });
		},
	},
}));
import axios from 'axios';
import { createChatDock } from '@/composables/useChatDock';
import ChatDock from '@/Components/Chat/Dock/ChatDock.vue';

const route = (name, p) => `/r/${name}/${p ?? ''}`;

function row(id, extra = {}) {
	return { id, type: 'dm', title: `Conv ${id}`, topic_type: null, last_message_preview: '', last_message_at: null, unread_count: 0, can_moderate: false, band_id: null, ...extra };
}

function memoryStorage() {
	const data = {};
	return { getItem: (k) => data[k] ?? null, setItem: (k, v) => { data[k] = String(v); }, removeItem: (k) => { delete data[k]; } };
}

let wrappers = [];
let dock;

function mountDock() {
	const w = mount(ChatDock, {
		props: { dock },
		// Teleport target: render in place so the DOM is queryable.
		global: { mixins: [{ methods: { route } }], stubs: { Teleport: true } },
		attachTo: document.body,
	});
	wrappers.push(w);
	return w;
}

// Teleport is stubbed only in dev builds; in prod it renders into <body>.
// Query the document so both paths are covered.
const q = (sel) => document.querySelector(sel);
const qa = (sel) => Array.from(document.querySelectorAll(sel));

describe('ChatDock', () => {
	beforeEach(() => {
		axios.get.mockReset(); axios.post.mockReset(); dispatch.mockReset();
		axios.get.mockResolvedValue({ data: { conversations: [row(1, { unread_count: 2 }), row(2), row(3), row(4)] } });
		axios.post.mockResolvedValue({ data: {} });
		storeState.user.chatSignal = 0;
		storeState.user.chatUnread = 0;
		page.component = 'Dashboard';
		page.props = { auth: { user: { id: 10, name: 'Me' } } };
		vi.stubGlobal('route', route);
		dock = createChatDock({ storage: memoryStorage() });
	});

	afterEach(() => {
		wrappers.forEach((w) => w.unmount());
		wrappers = [];
	});

	it('renders nothing for a guest, a contact, or on the Messages page', async () => {
		page.props = { auth: { user: null } };
		mountDock();
		await flushPromises();
		expect(q('[data-test="dock-launcher"]')).toBeNull();
		expect(axios.get).not.toHaveBeenCalled();

		page.props = { auth: { user: { id: 3, type: 'contact' } } };
		await nextTick();
		expect(q('[data-test="dock-launcher"]')).toBeNull();

		page.props = { auth: { user: { id: 10 } } };
		page.component = 'Messages/Index';
		await nextTick();
		expect(q('[data-test="dock-launcher"]')).toBeNull();
	});

	it('hydrates for the signed-in user, mirrors the store unread count, and opens windows from the list', async () => {
		mountDock();
		await flushPromises();
		expect(axios.get).toHaveBeenCalledWith('/r/chat.conversations.index/');
		expect(q('[data-test="dock-unread-pill"]')).toBeNull();

		storeState.user.chatUnread = 7;
		await nextTick();
		expect(q('[data-test="dock-unread-pill"]').textContent).toBe('7');

		expect(q('[data-test="list"]')).toBeNull();
		q('[data-test="dock-launcher"]').click();
		await nextTick();
		expect(q('[data-test="list"]').getAttribute('data-show-new')).toBe('false');

		q('[data-test="row-2"]').click();
		await nextTick();
		expect(q('[data-test="list"]')).toBeNull();
		expect(qa('[data-test="thread"]').map((el) => el.getAttribute('data-url'))).toEqual(['/r/chat.conversations.messages.index/2']);
		expect(q('[data-test="window-header"]').textContent).toContain('Conv 2');
	});

	it('refreshes the list when chatSignal bumps', async () => {
		mountDock();
		await flushPromises();
		expect(axios.get).toHaveBeenCalledTimes(1);
		storeState.user.chatSignal += 1;
		await flushPromises();
		expect(axios.get).toHaveBeenCalledTimes(2);
	});

	it('a read from a window zeroes its row and refetches the store count', async () => {
		mountDock();
		await flushPromises();
		dock.open(1);
		await nextTick();
		q('[data-test="thread"]').click();
		await nextTick();
		expect(dock.state.conversations.find((c) => c.id === 1).unread_count).toBe(0);
		expect(dispatch).toHaveBeenCalledWith('user/fetchChatUnread');
	});

	it('Escape closes the list popover', async () => {
		mountDock();
		await flushPromises();
		dock.toggleList();
		await nextTick();
		expect(q('[data-test="list"]')).not.toBeNull();
		document.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape' }));
		await nextTick();
		expect(q('[data-test="list"]')).toBeNull();
	});

	it('closes the popover when the dock hides (Messages page)', async () => {
		mountDock();
		await flushPromises();
		dock.toggleList();
		await nextTick();
		expect(q('[data-test="list"]')).not.toBeNull();
		page.component = 'Messages/Index';
		await nextTick();
		expect(dock.state.listOpen).toBe(false);
		page.component = 'Dashboard';
		await nextTick();
		expect(q('[data-test="list"]')).toBeNull();
	});

	it('resets when the user signs out', async () => {
		mountDock();
		await flushPromises();
		dock.open(1);
		page.props = { auth: { user: null } };
		await nextTick();
		expect(dock.state.windows).toEqual([]);
		expect(dock.state.userId).toBeNull();
	});
});
