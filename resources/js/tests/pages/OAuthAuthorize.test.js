import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';

vi.mock('@inertiajs/vue3', () => ({
	Head: { template: '<div />' },
	Link: { template: '<a><slot /></a>' },
}));

import Authorize from '@/Pages/OAuth/Authorize.vue';

const props = {
	client: { id: 'client-uuid', name: 'Claude' },
	user: { name: 'Olive Owner', email: 'olive@example.com' },
	scopes: [
		{ id: 'bookings:read', description: "View your bands' bookings and booked dates" },
		{ id: 'pricing:read', description: 'Get price estimates (band owners only)' },
	],
	authToken: 'auth-token-xyz',
	csrfToken: 'csrf-abc',
	approveUrl: 'https://tts.test/oauth/authorize',
	denyUrl: 'https://tts.test/oauth/authorize',
};

const hidden = (form, name) => form.find(`input[name="${name}"]`).element.value;

describe('OAuth/Authorize', () => {
	it('names the client and the user it will act as', () => {
		const wrapper = mount(Authorize, { props });

		expect(wrapper.text()).toContain('Allow Claude to access TTS Bandmate?');
		expect(wrapper.text()).toContain('Olive Owner');
		expect(wrapper.text()).toContain('olive@example.com');
	});

	it('lists the requested scopes', () => {
		const items = mount(Authorize, { props }).findAll('[data-test="scopes"] li');

		expect(items.map((li) => li.text())).toEqual(props.scopes.map((s) => s.description));
	});

	it('approves with a native POST carrying the CSRF and auth tokens', () => {
		const form = mount(Authorize, { props }).find('[data-test="approve-form"]');

		expect(form.attributes('method')).toBe('POST');
		expect(form.attributes('action')).toBe(props.approveUrl);
		expect(hidden(form, '_token')).toBe('csrf-abc');
		expect(hidden(form, 'auth_token')).toBe('auth-token-xyz');
		expect(hidden(form, 'client_id')).toBe('client-uuid');
		expect(form.find('input[name="_method"]').exists()).toBe(false);
	});

	it('denies with a spoofed DELETE', () => {
		const form = mount(Authorize, { props }).find('[data-test="deny-form"]');

		expect(form.attributes('action')).toBe(props.denyUrl);
		expect(hidden(form, '_method')).toBe('DELETE');
		expect(hidden(form, '_token')).toBe('csrf-abc');
		expect(hidden(form, 'auth_token')).toBe('auth-token-xyz');
	});
});
