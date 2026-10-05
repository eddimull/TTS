import { ref, watch } from 'vue';

/**
 * Host-page state for the comments drawer: open flag, a local unread count
 * that mirrors the Inertia prop but can be zeroed immediately when the thread
 * acks a read, and auto-open from the bell deep link (`?comments=1`).
 */
export function useCommentsDrawer(unreadRef) {
	const startsOpen = typeof window !== 'undefined'
		&& new URLSearchParams(window.location.search).get('comments') === '1';

	const open = ref(startsOpen);
	const unread = ref(unreadRef.value ?? 0);

	watch(unreadRef, (v) => {
		unread.value = v ?? 0;
	});

	function openDrawer() {
		open.value = true;
	}

	function onRead() {
		unread.value = 0;
	}

	return { open, unread, openDrawer, onRead };
}
