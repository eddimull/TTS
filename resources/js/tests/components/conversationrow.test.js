import { describe, it, expect, vi } from 'vitest';
import { mount } from '@vue/test-utils';
import ConversationRow from '@/Pages/Messages/Components/ConversationRow.vue';

function row(overrides = {}) {
	return {
		id: 1, type: 'dm', band_id: null, title: 'Taylor Campo', topic_type: null,
		last_message_preview: 'see you at 5', last_message_at: new Date(Date.now() - 60_000).toISOString(),
		unread_count: 0, can_moderate: false, ...overrides,
	};
}

describe('ConversationRow', () => {
	it('renders title, preview, relative time and no pill at zero', () => {
		const w = mount(ConversationRow, { props: { conversation: row() } });
		expect(w.text()).toContain('Taylor Campo');
		expect(w.text()).toContain('see you at 5');
		expect(w.text()).toMatch(/minute|ago/);
		expect(w.find('[data-test="unread-pill"]').exists()).toBe(false);
		expect(w.find('i').classes()).toContain('pi-user');
	});

	it('maps icons by type and shows the pill', () => {
		const cases = [
			[{ type: 'band' }, 'pi-users'],
			[{ type: 'topic', topic_type: 'booking' }, 'pi-briefcase'],
			[{ type: 'topic', topic_type: 'event' }, 'pi-calendar'],
			[{ type: 'topic', topic_type: 'rehearsal' }, 'pi-headphones'],
			[{ type: 'topic', topic_type: null }, 'pi-comment'],
		];
		for (const [o, icon] of cases) {
			const w = mount(ConversationRow, { props: { conversation: row({ ...o, unread_count: 3 }) } });
			expect(w.find('i').classes(), icon).toContain(icon);
			expect(w.find('[data-test="unread-pill"]').text()).toBe('3');
		}
	});

	it('falls back for empty previews and emits select on click', async () => {
		const onSelect = vi.fn();
		const w = mount(ConversationRow, { props: { conversation: row({ id: 9, last_message_preview: null, last_message_at: null }), onSelect } });
		expect(w.text()).toContain('No messages yet');
		await w.find('button').trigger('click');
		expect(onSelect).toHaveBeenCalledWith(9);
	});

	it('highlights when selected', () => {
		const w = mount(ConversationRow, { props: { conversation: row(), selected: true } });
		expect(w.find('button').attributes('aria-current')).toBe('true');
	});
});
