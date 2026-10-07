import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import MessageComposer from '@/Components/Chat/MessageComposer.vue';

function mountComposer(extra = {}) {
	const onSend = vi.fn();
	const onTyping = vi.fn();
	const wrapper = mount(MessageComposer, {
		props: { onSend, onTyping, ...extra },
		global: { stubs: { Button: { template: '<button :disabled="disabled" @click="$emit(\'click\')"><slot /></button>', props: ['disabled', 'icon', 'label', 'loading', 'rounded', 'text'] } } },
	});
	return { wrapper, onSend, onTyping };
}

async function type(wrapper, text) {
	const ta = wrapper.find('textarea');
	await ta.setValue(text);
	return ta;
}

describe('MessageComposer', () => {
	it('send button is disabled while empty and enabled once there is text', async () => {
		const { wrapper } = mountComposer();
		expect(wrapper.find('[data-test="send"]').attributes('disabled')).toBeDefined();
		await type(wrapper, 'hello');
		expect(wrapper.find('[data-test="send"]').attributes('disabled')).toBeUndefined();
	});

	it('Enter sends trimmed text and clears; Shift+Enter does not send', async () => {
		const { wrapper, onSend } = mountComposer();
		const ta = await type(wrapper, '  hi there  ');

		await ta.trigger('keydown', { key: 'Enter', shiftKey: true });
		expect(onSend).not.toHaveBeenCalled();

		await ta.trigger('keydown', { key: 'Enter' });
		expect(onSend).toHaveBeenCalledWith({ body: 'hi there', files: [] });
		expect(wrapper.find('textarea').element.value).toBe('');
	});

	it('emits typing while the user types, not for empty input', async () => {
		const { wrapper, onTyping } = mountComposer();
		await type(wrapper, '');
		expect(onTyping).not.toHaveBeenCalled();
		await type(wrapper, 'x');
		expect(onTyping).toHaveBeenCalledTimes(1);
	});

	it('caps attachments at maxImages and allows image-only sends', async () => {
		const { wrapper, onSend } = mountComposer({ maxImages: 2 });
		const files = [1, 2, 3].map((i) => new File(['x'], `${i}.jpg`, { type: 'image/jpeg' }));
		const input = wrapper.find('input[type="file"]');
		Object.defineProperty(input.element, 'files', { value: files });
		await input.trigger('change');

		expect(wrapper.findAll('[data-test="preview"]')).toHaveLength(2);
		expect(wrapper.text()).toContain('Up to 2 images');

		await wrapper.find('[data-test="send"]').trigger('click');
		expect(onSend).toHaveBeenCalledTimes(1);
		expect(onSend.mock.calls[0][0].body).toBe('');
		expect(onSend.mock.calls[0][0].files).toHaveLength(2);
	});

	it('wording follows the noun prop', () => {
		expect(mountComposer().wrapper.find('textarea').attributes('placeholder')).toBe('Add a comment…');
		expect(mountComposer({ noun: 'message' }).wrapper.find('textarea').attributes('placeholder')).toBe('Add a message…');
	});

	it('does nothing while disabled', async () => {
		const { wrapper, onSend } = mountComposer({ disabled: true });
		const ta = await type(wrapper, 'hello');
		await ta.trigger('keydown', { key: 'Enter' });
		expect(onSend).not.toHaveBeenCalled();
	});

	it('Enter during IME composition does not send', async () => {
		const { wrapper, onSend } = mountComposer();
		const ta = await type(wrapper, 'hi there');

		await ta.trigger('keydown', { key: 'Enter', isComposing: true });
		expect(onSend).not.toHaveBeenCalled();

		await ta.trigger('keydown', { key: 'Enter' });
		expect(onSend).toHaveBeenCalledWith({ body: 'hi there', files: [] });
	});

	it('paste is ignored while disabled', async () => {
		const { wrapper } = mountComposer({ disabled: true });
		const ta = wrapper.find('textarea');
		await ta.trigger('paste', {
			clipboardData: { items: [{ type: 'image/png', getAsFile: () => new File(['x'], 'p.png', { type: 'image/png' }) }] },
		});
		expect(wrapper.findAll('[data-test="preview"]')).toHaveLength(0);
	});
});
