import { describe, it, expect, beforeEach, vi } from 'vitest';
import { mount, flushPromises } from '@vue/test-utils';
import { h } from 'vue';

vi.mock('axios', () => ({ default: { get: vi.fn(), post: vi.fn() } }));
import axios from 'axios';
import NewMessageDialog from '@/Pages/Messages/Components/NewMessageDialog.vue';

// Dialog/Tag are resolved globally (app.js registers them, NewMessageDialog.vue
// references them by bare tag with no local import), so `global.stubs` won't
// reach them: VTU's `global.stubs` auto-stubbing relies on Vue's dev-only
// `transformVNodeArgs` hook around `createVNode`, which the production Vue
// build (`@vue/runtime-core/dist/runtime-core.cjs.prod.js`) compiles out
// entirely — `createVNode` there is the raw, untransformed function, so no
// stub substitution happens and the REAL Dialog (which teleports to
// `document.body`) renders instead, leaving the mounted wrapper empty.
// `global.components` registers on the app's component registry instead,
// which `resolveComponent` consults directly in both dev and prod builds.
// Render functions (not template strings) keep these stubs independent of
// any particular compile path.
const globalOpts = {
	components: {
		Dialog: {
			name: 'Dialog',
			props: ['visible'],
			render() {
				return this.visible ? h('div', [this.$slots.header?.(), this.$slots.default?.()]) : null;
			},
		},
		Tag: {
			name: 'Tag',
			props: ['value'],
			render() {
				return h('span', this.value);
			},
		},
	},
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
		const w = mount(NewMessageDialog, { props: { visible: true }, global: globalOpts });
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
		const w = mount(NewMessageDialog, { props: { visible: true, onCreated, 'onUpdate:visible': onUpdateVisible }, global: globalOpts });
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
		const w = mount(NewMessageDialog, { props: { visible: true }, global: globalOpts });
		await flushPromises();
		await w.find('[data-test="contact-row"]').trigger('click');
		await flushPromises();
		expect(w.text()).toContain('You do not share a band with this user.');
	});

	it('ignores a second click while a DM is being created', async () => {
		axios.get.mockResolvedValueOnce({ data: { contacts } });
		let resolvePost;
		axios.post.mockImplementationOnce(() => new Promise((resolve) => { resolvePost = resolve; }));
		const onCreated = vi.fn();
		const w = mount(NewMessageDialog, { props: { visible: true, onCreated }, global: globalOpts });
		await flushPromises();

		const row = w.find('[data-test="contact-row"]');
		const clicks = Promise.all([row.trigger('click'), row.trigger('click')]);
		await clicks;

		resolvePost({ data: { conversation: { id: 77, type: 'dm', title: 'Taylor Campo', unread_count: 0 } } });
		await flushPromises();

		expect(axios.post).toHaveBeenCalledTimes(1);
		expect(onCreated).toHaveBeenCalledTimes(1);
	});

	it('discards a stale contacts response when reopened', async () => {
		let resolveFirst;
		let resolveSecond;
		axios.get
			.mockImplementationOnce(() => new Promise((resolve) => { resolveFirst = resolve; }))
			.mockImplementationOnce(() => new Promise((resolve) => { resolveSecond = resolve; }));

		const w = mount(NewMessageDialog, { props: { visible: false }, global: globalOpts });

		await w.setProps({ visible: true });
		await w.setProps({ visible: false });
		await w.setProps({ visible: true });

		resolveSecond({ data: { contacts: [{ id: 2, name: 'Second', avatar_url: null, context: 'Band', is_sub: false }] } });
		await flushPromises();

		resolveFirst({ data: { contacts: [{ id: 1, name: 'First', avatar_url: null, context: 'Band', is_sub: false }] } });
		await flushPromises();

		expect(w.text()).toContain('Second');
		expect(w.text()).not.toContain('First');
	});
});
