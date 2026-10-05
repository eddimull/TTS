import { describe, it, expect, beforeEach, vi } from 'vitest';
import { createStore } from 'vuex';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
vi.mock('@inertiajs/vue3', () => ({ usePage: () => ({ props: { auth: { user: null } } }) }));
import axios from 'axios';
import userStore from '../../Store/userStore';

describe('user store — chat unread + signal', () => {
	let store;
	beforeEach(() => {
		axios.get.mockReset();
		vi.stubGlobal('route', (name) => `/r/${name}`);
		store = createStore({ modules: { user: userStore } });
	});

	it('fetchChatUnread stores the count from chat.unread-count', async () => {
		axios.get.mockResolvedValueOnce({ data: { count: 7 } });
		await store.dispatch('user/fetchChatUnread');
		expect(axios.get).toHaveBeenCalledWith('/r/chat.unread-count');
		expect(store.state.user.chatUnread).toBe(7);
	});

	it('fetchChatUnread keeps the last value when the request fails', async () => {
		axios.get.mockResolvedValueOnce({ data: { count: 3 } });
		await store.dispatch('user/fetchChatUnread');
		axios.get.mockRejectedValueOnce(new Error('offline'));
		await store.dispatch('user/fetchChatUnread');
		expect(store.state.user.chatUnread).toBe(3);
	});

	it('signalChatChange bumps chatSignal and refetches the count', async () => {
		axios.get.mockResolvedValue({ data: { count: 1 } });
		await store.dispatch('user/signalChatChange');
		await store.dispatch('user/signalChatChange');
		expect(store.state.user.chatSignal).toBe(2);
		expect(axios.get).toHaveBeenCalledTimes(2);
	});
});
