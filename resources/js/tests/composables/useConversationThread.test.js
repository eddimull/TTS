import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { nextTick } from 'vue';
import { installEchoMock } from '../mocks/echo';
import { CONVERSATION_EVENTS, __resetConversationChannelState } from '../../realtime/conversationChannel';

vi.mock('axios', () => ({
	default: { get: vi.fn(), post: vi.fn(), patch: vi.fn(), delete: vi.fn() },
}));
import axios from 'axios';
import {
	useConversationThread,
	MARK_READ_DEBOUNCE_MS,
	TYPING_TTL_MS,
	TYPING_THROTTLE_MS,
} from '../../composables/useConversationThread';

const ME = 10;
const OTHER = 20;

function msg(id, overrides = {}) {
	return {
		id, conversation_id: 1, user_id: OTHER, user_name: 'Other', user_avatar_url: null,
		body: `m${id}`, attachments: [], reactions: [], edited_at: null, is_deleted: false,
		created_at: `2026-01-01T10:00:0${id}+00:00`, ...overrides,
	};
}

function page(messages, extra = {}) {
	return {
		data: {
			conversation: { id: 1, type: 'topic', title: 'Gig', can_moderate: false, unread_count: 0 },
			messages,
			participants: [{ user_id: ME, name: 'Me', last_read_at: null, last_delivered_at: null }],
			channel: 'private-conversation.1',
			has_more: false,
			...extra,
		},
	};
}

describe('useConversationThread', () => {
	let echo;
	beforeEach(() => {
		vi.useFakeTimers();
		__resetConversationChannelState();
		echo = installEchoMock();
		vi.stubGlobal('route', (name, params) => `/r/${name}/${encodeURIComponent(JSON.stringify(params ?? null))}`);
		axios.get.mockReset(); axios.post.mockReset(); axios.patch.mockReset(); axios.delete.mockReset();
	});
	afterEach(() => {
		vi.useRealTimers();
		vi.unstubAllGlobals();
	});

	it('load() fills state from a ThreadPage and subscribes to the channel', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1), msg(2)]));
		const t = useConversationThread({ currentUserId: ME });

		await t.load('/topic-url');

		expect(axios.get).toHaveBeenCalledWith('/topic-url');
		expect(t.messages.value.map((m) => m.id)).toEqual([1, 2]);
		expect(t.conversation.value.title).toBe('Gig');
		expect(echo.privateCalls).toEqual(['conversation.1']);
		expect(t.loading.value).toBe(false);
	});

	it('load() failure sets error and leaves messages empty', async () => {
		axios.get.mockRejectedValueOnce(new Error('boom'));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');
		expect(t.error.value).toBeTruthy();
		expect(t.messages.value).toEqual([]);
	});

	it('appends realtime messages once (dedupes own echo) and debounces markRead for others', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1)]));
		axios.post.mockResolvedValue({ data: {} });
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		echo.fire('conversation.1', CONVERSATION_EVENTS.created, { message: msg(2) });
		echo.fire('conversation.1', CONVERSATION_EVENTS.created, { message: msg(2) });
		expect(t.messages.value.map((m) => m.id)).toEqual([1, 2]);

		expect(axios.post).not.toHaveBeenCalled();
		vi.advanceTimersByTime(MARK_READ_DEBOUNCE_MS);
		await nextTick();
		expect(axios.post).toHaveBeenCalledWith(
			expect.stringContaining('chat.conversations.read'),
			{ last_read_message_id: 2 },
		);
	});

	it('does not markRead on its own echoed message', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1)]));
		axios.post.mockResolvedValue({ data: {} });
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		echo.fire('conversation.1', CONVERSATION_EVENTS.created, { message: msg(3, { user_id: ME }) });
		vi.advanceTimersByTime(MARK_READ_DEBOUNCE_MS * 2);
		expect(axios.post).not.toHaveBeenCalled();
	});

	it('updated replaces in place, deleted tombstones', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1), msg(2)]));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		echo.fire('conversation.1', CONVERSATION_EVENTS.updated, { message: msg(1, { body: 'edited', edited_at: 'x' }) });
		expect(t.messages.value[0].body).toBe('edited');

		echo.fire('conversation.1', CONVERSATION_EVENTS.deleted, { message_id: 2 });
		expect(t.messages.value[1]).toMatchObject({ id: 2, is_deleted: true, body: null, attachments: [], reactions: [] });
	});

	it('read/delivered patch participants; typing is ignored for self and expires', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1)]));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		echo.fire('conversation.1', CONVERSATION_EVENTS.read, { user_id: OTHER, last_read_at: 'R' });
		echo.fire('conversation.1', CONVERSATION_EVENTS.delivered, { user_id: OTHER, last_delivered_at: 'D' });
		const other = t.participants.value.find((p) => p.user_id === OTHER);
		expect(other).toMatchObject({ last_read_at: 'R', last_delivered_at: 'D' });

		echo.fire('conversation.1', CONVERSATION_EVENTS.typing, { user_id: ME, name: 'Me' });
		echo.fire('conversation.1', CONVERSATION_EVENTS.typing, { user_id: OTHER, name: 'Other' });
		expect(t.typingUsers.value).toEqual([{ user_id: OTHER, name: 'Other' }]);

		vi.advanceTimersByTime(TYPING_TTL_MS);
		expect(t.typingUsers.value).toEqual([]);
	});

	it('loadOlder() prepends and keeps order; stops when has_more is false', async () => {
		axios.get.mockResolvedValueOnce(page([msg(5), msg(6)], { has_more: true }));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		axios.get.mockResolvedValueOnce(page([msg(3), msg(4)], { has_more: false }));
		await t.loadOlder();

		expect(axios.get).toHaveBeenLastCalledWith(
			expect.stringContaining('chat.conversations.messages.index'),
			{ params: { before: 5 } },
		);
		expect(t.messages.value.map((m) => m.id)).toEqual([3, 4, 5, 6]);
		expect(t.hasMore.value).toBe(false);

		await t.loadOlder();
		expect(axios.get).toHaveBeenCalledTimes(2);
	});

	it('send() posts multipart and appends the returned message', async () => {
		axios.get.mockResolvedValueOnce(page([]));
		axios.post.mockResolvedValueOnce({ data: { message: msg(9, { user_id: ME, body: 'hi' }) } });
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		const file = new File(['x'], 'a.jpg', { type: 'image/jpeg' });
		await t.send({ body: 'hi', files: [file] });

		const [url, form] = axios.post.mock.calls[0];
		expect(url).toContain('chat.conversations.messages.store');
		expect(form).toBeInstanceOf(FormData);
		expect(form.get('body')).toBe('hi');
		expect(form.getAll('images[]')).toHaveLength(1);
		expect(t.messages.value.map((m) => m.id)).toEqual([9]);
	});

	it('toggleReaction() adds when absent, removes when mine, and serialises per message', async () => {
		axios.get.mockResolvedValueOnce(page([msg(1, { reactions: [{ emoji: '👍', count: 1, user_ids: [ME] }] })]));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		let resolveDelete;
		axios.delete.mockReturnValueOnce(new Promise((r) => { resolveDelete = r; }));
		const p = t.toggleReaction(1, '👍');
		t.toggleReaction(1, '🎉'); // ignored while in flight
		resolveDelete({ data: { reactions: [] } });
		await p;

		expect(axios.delete).toHaveBeenCalledTimes(1);
		expect(axios.post).not.toHaveBeenCalled();
		expect(t.messages.value[0].reactions).toEqual([]);

		axios.post.mockResolvedValueOnce({ data: { reactions: [{ emoji: '🎉', count: 1, user_ids: [ME] }] } });
		await t.toggleReaction(1, '🎉');
		expect(axios.post).toHaveBeenCalledWith(expect.stringContaining('chat.messages.reactions.store'), { emoji: '🎉' });
		expect(t.messages.value[0].reactions[0].emoji).toBe('🎉');
	});

	it('notifyTyping() is throttled', async () => {
		axios.get.mockResolvedValueOnce(page([]));
		axios.post.mockResolvedValue({ data: {} });
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');

		t.notifyTyping(); t.notifyTyping();
		expect(axios.post).toHaveBeenCalledTimes(1);
		vi.advanceTimersByTime(TYPING_THROTTLE_MS);
		t.notifyTyping();
		expect(axios.post).toHaveBeenCalledTimes(2);
	});

	it('destroy() leaves the channel', async () => {
		axios.get.mockResolvedValueOnce(page([]));
		const t = useConversationThread({ currentUserId: ME });
		await t.load('/topic-url');
		t.destroy();
		expect(echo.leaveCalls).toEqual(['conversation.1']);
	});
});
