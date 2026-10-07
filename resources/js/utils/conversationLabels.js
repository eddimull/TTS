/** Secondary line for a conversation summary row (`type` + `topic_type`). */
export function conversationSubtitle(c) {
	if (!c) return '';
	if (c.type === 'dm') return 'Direct message';
	if (c.type === 'band') return 'Band channel';
	return { booking: 'Booking thread', event: 'Event thread', rehearsal: 'Rehearsal thread' }[c.topic_type] ?? 'Thread';
}
