import { getCurrentInstance, onBeforeUnmount, ref } from 'vue';
import axios from 'axios';
import { subscribeConversation } from '../realtime/conversationChannel';

export const MARK_READ_DEBOUNCE_MS = 1500;
export const TYPING_TTL_MS = 5000;
export const TYPING_THROTTLE_MS = 3000;

/**
 * Thread state + actions for ONE conversation. Comment-agnostic: the
 * comments drawer and (later) the Messages page both drive it. Mirrors the
 * Flutter ChatThreadNotifier so the two clients behave the same way:
 *  - realtime `message.created` appends only unseen ids (dedupes own echo)
 *    and debounces a read ack when the author is someone else;
 *  - `message.deleted` tombstones in place; read/delivered patch participants;
 *  - typing entries expire after TYPING_TTL_MS and own typing is ignored;
 *  - reactions are serialised per message so a slow response can't reorder.
 */
export function useConversationThread({ currentUserId }) {
	const conversation = ref(null);
	const messages = ref([]);
	const participants = ref([]);
	const hasMore = ref(false);
	const loading = ref(false);
	const loadingOlder = ref(false);
	const error = ref(null);
	const loadOlderError = ref(null);
	const sending = ref(false);
	const typingUsers = ref([]);
	const readSignal = ref(0);

	let unsubscribe = null;
	let readTimer = null;
	let lastTypingAt = 0;
	const typingTimers = new Map();
	const reactionsInFlight = new Set();

	function indexOf(id) {
		return messages.value.findIndex((m) => m.id === id);
	}

	function upsert(message) {
		const i = indexOf(message.id);
		if (i === -1) {
			messages.value = [...messages.value, message];
			return true;
		}
		messages.value.splice(i, 1, message);
		return false;
	}

	function tombstone(id) {
		const i = indexOf(id);
		if (i === -1) return;
		messages.value.splice(i, 1, {
			...messages.value[i],
			body: null,
			attachments: [],
			reactions: [],
			is_deleted: true,
		});
	}

	function patchParticipant(userId, patch) {
		const i = participants.value.findIndex((p) => p.user_id === userId);
		if (i === -1) {
			participants.value = [
				...participants.value,
				{ user_id: userId, name: null, avatar_url: null, last_read_at: null, last_delivered_at: null, ...patch },
			];
			return;
		}
		participants.value.splice(i, 1, { ...participants.value[i], ...patch });
	}

	function onTyping({ user_id, name }) {
		if (user_id === currentUserId) return;
		if (!typingUsers.value.some((u) => u.user_id === user_id)) {
			typingUsers.value = [...typingUsers.value, { user_id, name }];
		}
		clearTimeout(typingTimers.get(user_id));
		typingTimers.set(
			user_id,
			setTimeout(() => {
				typingUsers.value = typingUsers.value.filter((u) => u.user_id !== user_id);
				typingTimers.delete(user_id);
			}, TYPING_TTL_MS),
		);
	}

	function clearTyping(userId) {
		clearTimeout(typingTimers.get(userId));
		typingTimers.delete(userId);
		typingUsers.value = typingUsers.value.filter((u) => u.user_id !== userId);
	}

	function bind(conversationId) {
		unsubscribe?.();
		unsubscribe = subscribeConversation(conversationId, {
			onCreated: (m) => {
				const isNew = upsert(m);
				clearTyping(m.user_id);
				if (isNew && m.user_id !== currentUserId) scheduleMarkRead();
			},
			onUpdated: upsert,
			onDeleted: tombstone,
			onRead: ({ user_id, last_read_at }) => patchParticipant(user_id, { last_read_at }),
			onDelivered: ({ user_id, last_delivered_at }) => patchParticipant(user_id, { last_delivered_at }),
			onTyping,
		});
	}

	function applyPage(data) {
		conversation.value = data.conversation;
		messages.value = data.messages;
		participants.value = data.participants;
		hasMore.value = Boolean(data.has_more);
	}

	async function load(url) {
		loading.value = true;
		error.value = null;
		try {
			const { data } = await axios.get(url);
			applyPage(data);
			bind(data.conversation.id);
		} catch (e) {
			error.value = e;
		} finally {
			loading.value = false;
		}
	}

	async function loadOlder() {
		if (!hasMore.value || loadingOlder.value || !conversation.value || !messages.value.length) return;
		loadingOlder.value = true;
		loadOlderError.value = null;
		try {
			const before = messages.value[0].id;
			const { data } = await axios.get(
				route('chat.conversations.messages.index', conversation.value.id),
				{ params: { before } },
			);
			messages.value = [...data.messages, ...messages.value];
			hasMore.value = Boolean(data.has_more);
		} catch (e) {
			loadOlderError.value = e;
		} finally {
			loadingOlder.value = false;
		}
	}

	function scheduleMarkRead() {
		clearTimeout(readTimer);
		readTimer = setTimeout(markRead, MARK_READ_DEBOUNCE_MS);
	}

	async function markRead() {
		clearTimeout(readTimer);
		readTimer = null;
		const last = messages.value[messages.value.length - 1];
		if (!conversation.value || !last) return;
		try {
			await axios.post(route('chat.conversations.read', conversation.value.id), {
				last_read_message_id: last.id,
			});
			readSignal.value += 1;
		} catch (e) {
			// Read acks are best-effort: a dropped request just means the next
			// scheduled/triggered markRead() retries it.
		}
	}

	async function send({ body, files = [] }) {
		if (!conversation.value) return null;
		const form = new FormData();
		if (body && body.trim() !== '') form.append('body', body.trim());
		files.forEach((f) => form.append('images[]', f));
		sending.value = true;
		try {
			const { data } = await axios.post(
				route('chat.conversations.messages.store', conversation.value.id),
				form,
			);
			upsert(data.message);
			return data.message;
		} finally {
			sending.value = false;
		}
	}

	async function edit(id, body) {
		const { data } = await axios.patch(route('chat.messages.update', id), { body });
		upsert(data.message);
	}

	async function remove(id) {
		await axios.delete(route('chat.messages.destroy', id));
		tombstone(id);
	}

	async function toggleReaction(id, emoji) {
		if (reactionsInFlight.has(id)) return;
		const i = indexOf(id);
		if (i === -1) return;
		const mine = (messages.value[i].reactions ?? []).some(
			(r) => r.emoji === emoji && r.user_ids.includes(currentUserId),
		);
		reactionsInFlight.add(id);
		try {
			const { data } = mine
				? await axios.delete(route('chat.messages.reactions.destroy', { message: id, emoji }))
				: await axios.post(route('chat.messages.reactions.store', id), { emoji });
			const j = indexOf(id);
			if (j !== -1) messages.value.splice(j, 1, { ...messages.value[j], reactions: data.reactions });
		} finally {
			reactionsInFlight.delete(id);
		}
	}

	function notifyTyping() {
		if (!conversation.value) return;
		const now = Date.now();
		if (now - lastTypingAt < TYPING_THROTTLE_MS) return;
		lastTypingAt = now;
		axios.post(route('chat.conversations.typing', conversation.value.id)).catch(() => {});
	}

	function destroy() {
		unsubscribe?.();
		unsubscribe = null;
		clearTimeout(readTimer);
		readTimer = null;
		typingTimers.forEach((t) => clearTimeout(t));
		typingTimers.clear();
	}

	if (getCurrentInstance()) onBeforeUnmount(destroy);

	return {
		conversation, messages, participants, hasMore, loading, loadingOlder, error, loadOlderError, sending, typingUsers, readSignal,
		load, loadOlder, send, edit, remove, toggleReaction, markRead, notifyTyping, destroy,
	};
}
