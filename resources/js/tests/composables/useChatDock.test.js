import { describe, it, expect, beforeEach, vi } from 'vitest';
import { flushPromises } from '@vue/test-utils';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
import axios from 'axios';
import { createChatDock, MAX_WINDOWS, storageKey } from '../../composables/useChatDock';

global.route = (name) => `/mock/${name}`;

function memoryStorage(initial = {}) {
	const data = { ...initial };
	return {
		data,
		getItem: (k) => (k in data ? data[k] : null),
		setItem: (k, v) => { data[k] = String(v); },
		removeItem: (k) => { delete data[k]; },
	};
}

function row(id, extra = {}) {
	return { id, type: 'dm', band_id: null, title: `Conv ${id}`, topic_type: null, last_message_preview: '', last_message_at: null, unread_count: 0, can_moderate: false, ...extra };
}

function listResponse(...ids) {
	return { data: { conversations: ids.map((id) => row(id)) } };
}

describe('useChatDock', () => {
	beforeEach(() => {
		vi.clearAllMocks();
		axios.get.mockResolvedValue(listResponse(1, 2, 3, 4));
		axios.post.mockResolvedValue({});
	});

	it('open() appends windows, caps at MAX_WINDOWS by dropping the oldest, and closes the list', () => {
		const dock = createChatDock({ storage: memoryStorage() });
		dock.toggleList();
		dock.open(1);
		dock.open(2);
		dock.open(3);
		dock.open(4);
		expect(MAX_WINDOWS).toBe(3);
		expect(dock.state.windows.map((w) => w.id)).toEqual([2, 3, 4]);
		expect(dock.state.listOpen).toBe(false);
	});

	it('open() on an existing window un-minimises it without duplicating or reordering', () => {
		const dock = createChatDock({ storage: memoryStorage() });
		dock.open(1);
		dock.open(2);
		dock.toggleMinimize(1);
		expect(dock.state.windows[0].minimized).toBe(true);
		dock.open(1);
		expect(dock.state.windows).toEqual([{ id: 1, minimized: false }, { id: 2, minimized: false }]);
	});

	it('close() removes a window; toggleMinimize flips the flag', () => {
		const dock = createChatDock({ storage: memoryStorage() });
		dock.open(1);
		dock.open(2);
		dock.toggleMinimize(2);
		dock.close(1);
		expect(dock.state.windows).toEqual([{ id: 2, minimized: true }]);
	});

	it('persists windows per user and hydrate() restores them', async () => {
		const storage = memoryStorage();
		const a = createChatDock({ storage });
		await a.hydrate(7);
		a.open(1);
		a.open(2);
		a.toggleMinimize(2);
		expect(JSON.parse(storage.getItem(storageKey(7)))).toEqual({ windows: [{ id: 1, minimized: false }, { id: 2, minimized: true }] });

		const b = createChatDock({ storage });
		await b.hydrate(7);
		expect(b.state.windows).toEqual([{ id: 1, minimized: false }, { id: 2, minimized: true }]);
		expect(b.state.userId).toBe(7);

		const other = createChatDock({ storage });
		await other.hydrate(8);
		expect(other.state.windows).toEqual([]);
	});

	it('hydrate() ignores malformed storage', async () => {
		const dock = createChatDock({ storage: memoryStorage({ [storageKey(7)]: 'not json' }) });
		await dock.hydrate(7);
		expect(dock.state.windows).toEqual([]);
	});

	it('refreshList() loads rows and, once loaded, drops windows whose conversation is gone', async () => {
		const storage = memoryStorage({ [storageKey(7)]: JSON.stringify({ windows: [{ id: 2, minimized: false }, { id: 99, minimized: true }] }) });
		const dock = createChatDock({ storage });
		await dock.hydrate(7);
		await flushPromises();
		expect(axios.get).toHaveBeenCalledWith('/mock/chat.conversations.index');
		expect(dock.state.loaded).toBe(true);
		expect(dock.state.conversations.map((c) => c.id)).toEqual([1, 2, 3, 4]);
		expect(dock.state.windows).toEqual([{ id: 2, minimized: false }]);
		// Stale ids are pruned from storage too.
		expect(JSON.parse(storage.getItem(storageKey(7))).windows).toEqual([{ id: 2, minimized: false }]);
	});

	it('refreshList() keeps the current rows on failure', async () => {
		const dock = createChatDock({ storage: memoryStorage() });
		await dock.refreshList();
		expect(dock.state.conversations).toHaveLength(4);
		axios.get.mockRejectedValueOnce(new Error('offline'));
		await dock.refreshList();
		expect(dock.state.conversations).toHaveLength(4);
		expect(dock.state.loaded).toBe(true);
	});

	it('refreshList() posts delivered only while the list is open', async () => {
		const dock = createChatDock({ storage: memoryStorage() });
		await dock.refreshList();
		expect(axios.post).not.toHaveBeenCalled();
		dock.toggleList();
		await dock.refreshList();
		expect(axios.post).toHaveBeenCalledWith('/mock/chat.conversations.delivered');
	});

	it('windowRows joins windows to their summary, with a placeholder until the list loads', async () => {
		const dock = createChatDock({ storage: memoryStorage() });
		dock.open(2);
		expect(dock.windowRows.value).toEqual([{ id: 2, minimized: false, conversation: { id: 2, title: 'Conversation', unread_count: 0 } }]);
		await dock.refreshList();
		expect(dock.windowRows.value[0].conversation.title).toBe('Conv 2');
	});

	it('markRead() zeroes that row only', async () => {
		axios.get.mockResolvedValueOnce({ data: { conversations: [row(1, { unread_count: 3 }), row(2, { unread_count: 5 })] } });
		const dock = createChatDock({ storage: memoryStorage() });
		await dock.refreshList();
		dock.markRead(1);
		expect(dock.state.conversations.map((c) => c.unread_count)).toEqual([0, 5]);
	});

	it('reset() clears state without touching storage', async () => {
		const storage = memoryStorage();
		const dock = createChatDock({ storage });
		await dock.hydrate(7);
		dock.open(1);
		dock.toggleList();
		dock.reset();
		expect(dock.state).toMatchObject({ userId: null, listOpen: false, windows: [], conversations: [], loaded: false });
		expect(storage.getItem(storageKey(7))).not.toBeNull();
	});
});
