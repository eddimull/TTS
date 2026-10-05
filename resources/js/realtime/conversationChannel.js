// Refcounted subscriptions to the full-payload conversation stream.
//
// Companion to bandChannel.js (thin band signals). An open thread subscribes
// to `private-conversation.{id}` and receives whole messages on create /
// update / delete plus read, delivered and typing events, so it can patch
// local state without a refetch. Refcounting means an Inertia page
// transition that mounts the new drawer before the old one unmounts can
// never Echo.leave() a channel that is still in use.

export const CONVERSATION_EVENTS = {
	created: '.message.created',
	updated: '.message.updated',
	deleted: '.message.deleted',
	read: '.conversation.read',
	delivered: '.conversation.delivered',
	typing: '.conversation.typing',
};

// conversationId -> { count, handlers: Set<object> }
const channels = new Map();

function channelName(conversationId) {
	return `conversation.${conversationId}`;
}

function fanOut(entry, key, value) {
	entry.handlers.forEach((h) => h[key]?.(value));
}

function ensureChannel(conversationId) {
	let entry = channels.get(conversationId);
	if (entry) return entry;

	entry = { count: 0, handlers: new Set() };
	channels.set(conversationId, entry);

	window.Echo.private(channelName(conversationId))
		.subscribed(() => {})
		.error((err) => {
			console.warn(`[conversationChannel] auth/subscribe error on ${channelName(conversationId)}`, err);
		})
		.listen(CONVERSATION_EVENTS.created, (p) => fanOut(entry, 'onCreated', p.message))
		.listen(CONVERSATION_EVENTS.updated, (p) => fanOut(entry, 'onUpdated', p.message))
		.listen(CONVERSATION_EVENTS.deleted, (p) => fanOut(entry, 'onDeleted', p.message_id))
		.listen(CONVERSATION_EVENTS.read, (p) => fanOut(entry, 'onRead', p))
		.listen(CONVERSATION_EVENTS.delivered, (p) => fanOut(entry, 'onDelivered', p))
		.listen(CONVERSATION_EVENTS.typing, (p) => fanOut(entry, 'onTyping', p));

	return entry;
}

/**
 * Subscribe a handler bundle to one conversation. Returns an idempotent
 * unsubscribe. Handlers are copied into a fresh object so the same bundle
 * can be subscribed twice without colliding in the Set.
 */
export function subscribeConversation(conversationId, handlers = {}) {
	if (conversationId === null || conversationId === undefined || !window.Echo) return () => {};

	const entry = ensureChannel(conversationId);
	const bundle = { ...handlers };
	entry.count += 1;
	entry.handlers.add(bundle);

	let done = false;
	return () => {
		if (done) return;
		done = true;
		entry.handlers.delete(bundle);
		entry.count -= 1;
		if (entry.count <= 0) {
			channels.delete(conversationId);
			window.Echo?.leave(channelName(conversationId));
		}
	};
}

/** Reset module state (tests only). */
export function __resetConversationChannelState() {
	channels.clear();
}
