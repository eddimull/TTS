import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import CommentsButton from '@/Components/Chat/CommentsButton.vue';

describe('CommentsButton', () => {
	it('shows no pill at zero and the count otherwise', () => {
		const zero = mount(CommentsButton, { props: { unreadCount: 0 } });
		expect(zero.find('[data-test="unread-pill"]').exists()).toBe(false);
		expect(zero.text()).toContain('Comments');

		const some = mount(CommentsButton, { props: { unreadCount: 3 } });
		expect(some.find('[data-test="unread-pill"]').text()).toBe('3');
	});

	it('emits click', async () => {
		const onClick = vi.fn();
		const w = mount(CommentsButton, { props: { unreadCount: 0, onClick } });
		await w.find('button').trigger('click');
		expect(onClick).toHaveBeenCalledTimes(1);
	});
});
