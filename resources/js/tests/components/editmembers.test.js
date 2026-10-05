import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

vi.mock('axios', () => ({ default: { post: vi.fn() } }));
vi.mock('@inertiajs/vue3', () => ({
	router: { visit: vi.fn() },
	Link: { props: ['href'], template: '<a :href="href"><slot /></a>' },
}));
import axios from 'axios';
import { router } from '@inertiajs/vue3';
import EditMembers from '@/Pages/Band/Components/EditMembers.vue';

const band = {
	id: 1,
	members: [{ id: 5, user: { id: 20, name: 'Taylor Campo', email: 't@x.com' } }],
	owners: [
		{ id: 6, user: { id: 10, name: 'Edward Muller', email: 'e@x.com' } },
		{ id: 7, user: { id: 30, name: 'Ash Walker', email: 'a@x.com' } },
	],
	pending_invites: [],
};

const toastAdd = vi.fn();

// `global.stubs` auto-stubbing relies on Vue's dev-only `transformVNodeArgs`
// hook, which the production build compiles out (see newmessagedialog.test.js
// for the full explanation). `global.components` registers on the app's
// component registry instead, which `resolveComponent` consults in both
// dev and prod builds — so use that for the Button stub. Link is replaced
// entirely via vi.mock('@inertiajs/vue3', ...) above, which works in both.
function mountMembers() {
	return mount(EditMembers, {
		props: { band, inviting: false, invite: { email: '' } },
		global: {
			mocks: { $page: { props: { auth: { user: { id: 10 } } } }, $toast: { add: toastAdd } },
			config: { globalProperties: { route: (name, p) => `/r/${name}/${p ?? ''}` } },
			components: { Button: { props: ['label', 'icon', 'disabled'], template: '<button :disabled="disabled" @click="$emit(\'click\')">{{ label }}<slot /></button>' } },
		},
	});
}

describe('EditMembers — Message action', () => {
	beforeEach(() => { axios.post.mockReset(); router.visit.mockReset(); toastAdd.mockReset(); });

	it('shows a Message button for other people but not for the current user', () => {
		const w = mountMembers();
		const buttons = w.findAll('[data-test="message-user"]');
		// Taylor Campo (member) + Ash Walker (owner); Edward Muller is the current user and gets none.
		expect(buttons).toHaveLength(2);
		expect(buttons[0].attributes('aria-label')).toBe('Message Taylor Campo');
	});

	it('clicking creates the DM and visits the inbox', async () => {
		axios.post.mockResolvedValueOnce({ data: { conversation: { id: 44 } } });
		const w = mountMembers();
		await w.find('[data-test="message-user"]').trigger('click');
		await flushPromises();
		expect(axios.post).toHaveBeenCalledWith('/r/chat.conversations.dm/', { user_id: 20 });
		expect(router.visit).toHaveBeenCalledWith('/r/messages.index/44');
	});

	it('shows a Message button for other owners', async () => {
		axios.post.mockResolvedValueOnce({ data: { conversation: { id: 55 } } });
		const w = mountMembers();
		const buttons = w.findAll('[data-test="message-user"]');
		const ashButton = buttons.find((b) => b.attributes('aria-label') === 'Message Ash Walker');
		expect(ashButton).toBeTruthy();

		await ashButton.trigger('click');
		await flushPromises();
		expect(axios.post).toHaveBeenCalledWith('/r/chat.conversations.dm/', { user_id: 30 });
	});

	it('toasts the server message when the DM cannot be created and re-enables the button', async () => {
		axios.post.mockRejectedValueOnce({ response: { data: { message: 'You do not share a band with this user.' } } });
		const w = mountMembers();
		const button = w.find('[data-test="message-user"]');
		await button.trigger('click');
		await flushPromises();

		expect(toastAdd).toHaveBeenCalledWith(
			expect.objectContaining({
				severity: 'error',
				detail: 'You do not share a band with this user.',
			}),
		);
		expect(router.visit).not.toHaveBeenCalled();
		expect(button.attributes('disabled')).toBeFalsy();
	});
});
