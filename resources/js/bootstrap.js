import _ from 'lodash';

/**
 * We'll load the axios HTTP library which allows us to easily issue requests
 * to our Laravel back-end. This library automatically handles sending the
 * CSRF token as a header based on the value of the "XSRF" token cookie.
 */

import axios from 'axios';

window._ = _;
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

/**
 * Echo exposes an expressive API for subscribing to channels and listening
 * for events that are broadcast by Laravel. Echo and event broadcasting
 * allows your team to easily build robust real-time web applications.
 */

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

// When VITE_PUSHER_HOST is set (self-hosted soketi, e.g. tts.band), pin Echo to
// that host/port. When it is blank/absent, omit wsHost/wsPort entirely so
// pusher-js routes to Pusher Cloud via `cluster` (ws-<cluster>.pusher.com).
// This must track the server's broadcast target in config/broadcasting.php: the
// browser and the backend have to terminate at the same broker. The mobile app
// (pusher_channels_flutter) can only reach Pusher Cloud, so when the server moves
// to Pusher Cloud the web client must move with it by blanking VITE_PUSHER_HOST.
const pusherHost = import.meta.env.VITE_PUSHER_HOST;

window.Echo = new Echo({
    broadcaster: 'pusher',
    key: import.meta.env.VITE_PUSHER_APP_KEY,
    cluster: import.meta.env.VITE_PUSHER_APP_CLUSTER || 'mt1',
    forceTLS: true,
    disableStats: true,
    enabledTransports: ['ws', 'wss'],
    ...(pusherHost
        ? {
              wsHost: pusherHost,
              wsPort: import.meta.env.VITE_PUSHER_PORT,
              wssPort: import.meta.env.VITE_PUSHER_PORT,
          }
        : {}),
});