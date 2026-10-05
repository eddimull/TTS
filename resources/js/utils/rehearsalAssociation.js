// Presentation for a rehearsal's `associations[]` rows (RehearsalAssociation
// morphs). The controller only ever associates App\Models\Events, but the
// morph can in principle hold a booking, so both shapes are handled and the
// caller never has to know which fields each type carries.

function isBookingType(type) {
	return typeof type === 'string' && type.endsWith('Bookings');
}

/**
 * @param {object|null} association  { associable_type, associable }
 * @param {Function} route           Ziggy route() (passed in for testability)
 * @returns {{ title: string, date: string|null, venue: string|null, href: string, linkLabel: string } | null}
 *          null when the associated item no longer exists.
 */
export function describeAssociation(association, route) {
	const item = association?.associable;
	if (!item) return null;

	if (isBookingType(association.associable_type)) {
		return {
			title: item.name ?? item.title ?? 'Booking',
			date: item.start_date ?? item.date ?? null,
			venue: item.venue_summary ?? item.venue_name ?? null,
			href: route('Booking Details', { band: item.band_id, booking: item.id }),
			linkLabel: 'View Booking →',
		};
	}

	return {
		title: item.title ?? item.name ?? 'Event',
		date: item.date ?? null,
		venue: item.venue_name ?? null,
		href: route('events.show', item.key),
		linkLabel: 'View Event →',
	};
}
