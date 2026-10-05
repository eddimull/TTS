import { describe, it, expect, vi } from 'vitest';
import { describeAssociation } from '../../utils/rehearsalAssociation';

function fakeRoute() {
	return vi.fn((name, params) => `/r/${name}/${JSON.stringify(params)}`);
}

describe('describeAssociation', () => {
	it('describes an event association with the event page link', () => {
		const route = fakeRoute();
		const out = describeAssociation({
			associable_type: 'App\\Models\\Events',
			associable: { id: 330, key: 'abc-123', title: 'Thompson Wedding', date: '2026-10-09', venue_name: 'St Helena' },
		}, route);

		expect(out).toEqual({
			title: 'Thompson Wedding',
			date: '2026-10-09',
			venue: 'St Helena',
			href: '/r/events.show/"abc-123"',
			linkLabel: 'View Event →',
		});
		expect(route).toHaveBeenCalledWith('events.show', 'abc-123');
	});

	it('describes a booking association with the booking page link', () => {
		const route = fakeRoute();
		const out = describeAssociation({
			associable_type: 'App\\Models\\Bookings',
			associable: { id: 7, band_id: 1, name: 'Vaughan Wedding', start_date: '2026-10-03', venue_summary: 'TBD' },
		}, route);

		expect(out.title).toBe('Vaughan Wedding');
		expect(out.date).toBe('2026-10-03');
		expect(out.venue).toBe('TBD');
		expect(out.linkLabel).toBe('View Booking →');
		expect(route).toHaveBeenCalledWith('Booking Details', { band: 1, booking: 7 });
	});

	it('returns null when the associated item was deleted', () => {
		expect(describeAssociation({ associable_type: 'App\\Models\\Events', associable: null }, fakeRoute())).toBeNull();
		expect(describeAssociation(null, fakeRoute())).toBeNull();
	});

	it('falls back to name/title and tolerates missing fields', () => {
		const out = describeAssociation({
			associable_type: 'App\\Models\\Events',
			associable: { id: 1, key: 'k', name: 'Legacy Name' },
		}, fakeRoute());
		expect(out.title).toBe('Legacy Name');
		expect(out.date).toBeNull();
		expect(out.venue).toBeNull();
	});
});
