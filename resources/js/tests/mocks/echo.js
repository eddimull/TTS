// Minimal window.Echo stand-in: records private()/leave() calls and lets
// tests fire events into registered listeners.
export function installEchoMock() {
	const channels = new Map(); // name -> { listeners: Map(event -> [cb]) }

	const echo = {
		privateCalls: [],
		leaveCalls: [],
		private(name) {
			this.privateCalls.push(name);
			if (!channels.has(name)) channels.set(name, { listeners: new Map() });
			const entry = channels.get(name);
			const channel = {
				listen(event, cb) {
					if (!entry.listeners.has(event)) entry.listeners.set(event, []);
					entry.listeners.get(event).push(cb);
					return channel;
				},
				subscribed() { return channel; },
				error() { return channel; },
			};
			return channel;
		},
		leave(name) {
			this.leaveCalls.push(name);
			channels.delete(name);
		},
		fire(name, event, payload) {
			const entry = channels.get(name);
			(entry?.listeners.get(event) ?? []).forEach((cb) => cb(payload));
		},
	};

	window.Echo = echo;
	return echo;
}
