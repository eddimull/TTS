import { describe, it, expect, beforeEach } from 'vitest';
import { installEchoMock } from '../mocks/echo';
import {
	subscribeConversation,
	CONVERSATION_EVENTS,
	__resetConversationChannelState,
} from '../../realtime/conversationChannel';

describe('subscribeConversation', () => {
	let echo;
	beforeEach(() => {
		__resetConversationChannelState();
		echo = installEchoMock();
	});

	it('subscribes once per conversation and routes each wire event to its handler', () => {
		const seen = { created: [], updated: [], deleted: [], read: [], delivered: [], typing: [] };
		subscribeConversation(7, {
			onCreated: (m) => seen.created.push(m),
			onUpdated: (m) => seen.updated.push(m),
			onDeleted: (id) => seen.deleted.push(id),
			onRead: (p) => seen.read.push(p),
			onDelivered: (p) => seen.delivered.push(p),
			onTyping: (p) => seen.typing.push(p),
		});

		expect(echo.privateCalls).toEqual(['conversation.7']);

		echo.fire('conversation.7', CONVERSATION_EVENTS.created, { message: { id: 1 } });
		echo.fire('conversation.7', CONVERSATION_EVENTS.updated, { message: { id: 1, body: 'x' } });
		echo.fire('conversation.7', CONVERSATION_EVENTS.deleted, { message_id: 1 });
		echo.fire('conversation.7', CONVERSATION_EVENTS.read, { user_id: 2, last_read_at: 't' });
		echo.fire('conversation.7', CONVERSATION_EVENTS.delivered, { user_id: 2, last_delivered_at: 't' });
		echo.fire('conversation.7', CONVERSATION_EVENTS.typing, { user_id: 2, name: 'Al' });

		expect(seen.created).toEqual([{ id: 1 }]);
		expect(seen.updated).toEqual([{ id: 1, body: 'x' }]);
		expect(seen.deleted).toEqual([1]);
		expect(seen.read).toEqual([{ user_id: 2, last_read_at: 't' }]);
		expect(seen.delivered).toEqual([{ user_id: 2, last_delivered_at: 't' }]);
		expect(seen.typing).toEqual([{ user_id: 2, name: 'Al' }]);
	});

	it('refcounts: leaves only when the last subscriber unsubscribes; unsubscribe is idempotent', () => {
		const offA = subscribeConversation(3, {});
		const offB = subscribeConversation(3, {});
		expect(echo.privateCalls).toEqual(['conversation.3']);

		offA();
		offA();
		expect(echo.leaveCalls).toEqual([]);

		offB();
		expect(echo.leaveCalls).toEqual(['conversation.3']);
	});

	it('is a no-op without a conversation id or without Echo', () => {
		expect(typeof subscribeConversation(null, {})).toBe('function');
		delete window.Echo;
		const off = subscribeConversation(9, {});
		off();
		expect(echo.privateCalls).toEqual([]);
	});
});
