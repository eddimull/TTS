import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
import axios from 'axios';
import NewMessageDialog from '@/Pages/Messages/Components/NewMessageDialog.vue';

const stubs = {
	Dialog: { props: ['visible'], template: '<div v-if="visible"><slot name="header" /><slot /></div>' },
	Tag: { props: ['value'], template: '<span>{{ value }}</span>' },
};

const contacts = [
	{ id: 20, name: 'Taylor Campo', avatar_url: null, context: 'Three Thirty Seven', is_sub: false },
	{ id: 31, name: 'Sam Sub', avatar_url: null, context: 'Sub — Three Thirty Seven', is_sub: true },
];

describe('NewMessageDialog', () => {
	beforeEach(() => {
		axios.get.mockReset(); axios.post.mockReset();
		vi.stubGlobal('route', (name) => `/r/${name}`);
	});

	it('loads contacts when opened, filters by name, flags subs', async () => {
		axios.get.mockResolvedValueOnce({ data: { contacts } });
		const w = mount(NewMessageDialog, { props: { visible: true }, global: { stubs } });
		await flushPromises();

		expect(axios.get).toHaveBeenCalledWith('/r/chat.contacts');
		expect(w.text()).toContain('Taylor Campo');
		expect(w.text()).toContain('Sub');

		await w.find('input').setValue('tay');
		expect(w.findAll('[data-test="contact-row"]')).toHaveLength(1);
	});

	it('choosing a contact creates the DM and emits created + closes', async () => {
		axios.get.mockResolvedValueOnce({ data: { contacts } });
		axios.post.mockResolvedValueOnce({ data: { conversation: { id: 77, type: 'dm', title: 'Taylor Campo', unread_count: 0 } } });
		const onCreated = vi.fn();
		const onUpdateVisible = vi.fn();
		const w = mount(NewMessageDialog, { props: { visible: true, onCreated, 'onUpdate:visible': onUpdateVisible }, global: { stubs } });
		await flushPromises();

		await w.find('[data-test="contact-row"]').trigger('click');
		await flushPromises();

		expect(axios.post).toHaveBeenCalledWith('/r/chat.conversations.dm', { user_id: 20 });
		expect(onCreated).toHaveBeenCalledWith(expect.objectContaining({ id: 77 }));
		expect(onUpdateVisible).toHaveBeenCalledWith(false);
	});

	it('shows the server message when the DM cannot be created', async () => {
		axios.get.mockResolvedValueOnce({ data: { contacts } });
		axios.post.mockRejectedValueOnce({ response: { data: { message: 'You do not share a band with this user.' } } });
		const w = mount(NewMessageDialog, { props: { visible: true }, global: { stubs } });
		await flushPromises();
		await w.find('[data-test="contact-row"]').trigger('click');
		await flushPromises();
		expect(w.text()).toContain('You do not share a band with this user.');
	});
});
