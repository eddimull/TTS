import { DateTime } from 'luxon';

// Same rules as the Flutter message_time.dart so both clients group identically.

export function needsDateSeparator(prevIso, currIso) {
	if (!prevIso) return true;
	const prev = DateTime.fromISO(prevIso);
	const curr = DateTime.fromISO(currIso);
	return !prev.hasSame(curr, 'day') || curr.diff(prev, 'hours').hours > 1;
}

export function dateSeparatorLabel(iso, now = DateTime.now()) {
	const d = DateTime.fromISO(iso);
	const time = d.toFormat('h:mm a').replace(/[  ]/g, ' ');
	if (d.hasSame(now, 'day')) return `Today ${time}`;
	if (d.hasSame(now.minus({ days: 1 }), 'day')) return `Yesterday ${time}`;
	if (now.diff(d, 'days').days < 7) return `${d.toFormat('cccc')} ${time}`;
	return d.toFormat('MMM d, yyyy h:mm a').replace(/[  ]/g, ' ');
}

export function bubbleTimeLabel(iso) {
	return DateTime.fromISO(iso).toFormat('h:mm a').replace(/[  ]/g, ' ');
}
