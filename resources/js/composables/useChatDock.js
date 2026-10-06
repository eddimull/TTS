import { computed, reactive } from 'vue';
import axios from 'axios';

/**
 * State for the persistent chat dock (launcher + popover list + docked
 * windows). Module-level so it survives Inertia navigation — the dock is
 * mounted beside Inertia's root, never inside a page layout.
 *
 * `createChatDock()` builds an isolated instance for tests; `useChatDock()`
 * returns the app-wide singleton.
 */

export const MAX_WINDOWS = 3;

export function storageKey(userId) {
	return `tts.chatDock.v1.${userId}`;
}

const PLACEHOLDER_TITLE = 'Conversation';

export function createChatDock({ storage = typeof window !== 'undefined' ? window.localStorage : null } = {}) {
	const state = reactive({
		userId: null,
		listOpen: false,
		windows: [], // [{ id, minimized }], oldest first
		conversations: [], // summary rows from chat.conversations.index
		loaded: false, // first successful fetch done
	});

	function persist() {
		if (!storage || state.userId === null) return;
		try {
			storage.setItem(storageKey(state.userId), JSON.stringify({ windows: state.windows }));
		} catch (e) {
			// Storage full / disabled — the dock still works for this page load.
		}
	}

	function restore(userId) {
		if (!storage) return [];
		try {
			const parsed = JSON.parse(storage.getItem(storageKey(userId)) ?? 'null');
			if (!Array.isArray(parsed?.windows)) return [];
			return parsed.windows
				.filter((w) => Number.isInteger(w?.id))
				.map((w) => ({ id: w.id, minimized: Boolean(w.minimized) }))
				.slice(-MAX_WINDOWS);
		} catch (e) {
			return [];
		}
	}

	async function refreshList() {
		try {
			const { data } = await axios.get(route('chat.conversations.index'));
			state.conversations = data.conversations ?? [];
			state.loaded = true;
		} catch (e) {
			// Keep the current rows; the next chatSignal retries.
		}
		if (state.loaded) {
			const ids = new Set(state.conversations.map((c) => c.id));
			const kept = state.windows.filter((w) => ids.has(w.id));
			if (kept.length !== state.windows.length) {
				state.windows = kept;
				persist();
			}
		}
		if (state.listOpen) {
			// Same hook the Messages page fires: "my inbox has everything up to now".
			axios.post(route('chat.conversations.delivered')).catch(() => {});
		}
	}

	async function hydrate(userId) {
		state.userId = userId;
		state.windows = restore(userId);
		await refreshList();
	}

	function reset() {
		state.userId = null;
		state.listOpen = false;
		state.windows = [];
		state.conversations = [];
		state.loaded = false;
	}

	function toggleList() {
		state.listOpen = !state.listOpen;
	}

	function closeList() {
		state.listOpen = false;
	}

	function open(id) {
		const existing = state.windows.find((w) => w.id === id);
		if (existing) {
			existing.minimized = false;
		} else {
			state.windows.push({ id, minimized: false });
			while (state.windows.length > MAX_WINDOWS) state.windows.shift();
		}
		state.listOpen = false;
		persist();
	}

	function close(id) {
		state.windows = state.windows.filter((w) => w.id !== id);
		persist();
	}

	function toggleMinimize(id) {
		const w = state.windows.find((x) => x.id === id);
		if (!w) return;
		w.minimized = !w.minimized;
		persist();
	}

	function markRead(id) {
		const i = state.conversations.findIndex((c) => c.id === id);
		if (i !== -1) state.conversations.splice(i, 1, { ...state.conversations[i], unread_count: 0 });
	}

	const windowRows = computed(() => state.windows.map((w) => ({
		...w,
		conversation: state.conversations.find((c) => c.id === w.id)
			?? { id: w.id, title: PLACEHOLDER_TITLE, unread_count: 0 },
	})));

	return { state, windowRows, hydrate, reset, refreshList, toggleList, closeList, open, close, toggleMinimize, markRead };
}

let singleton = null;

export function useChatDock() {
	if (!singleton) singleton = createChatDock();
	return singleton;
}
