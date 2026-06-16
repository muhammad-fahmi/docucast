import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

const rawHost = import.meta.env.VITE_REVERB_HOST;
const wsHost = (rawHost === 'localhost' || !rawHost) ? window.location.hostname : rawHost;

const rawScheme = import.meta.env.VITE_REVERB_SCHEME;
const reverbScheme = (rawScheme && rawScheme !== '') 
    ? rawScheme.toLowerCase() 
    : (window.location.protocol === 'https:' ? 'https' : 'http');
const isSecureWebSocket = reverbScheme === 'https';

const rawPort = import.meta.env.VITE_REVERB_PORT;
const wsPort = rawPort ? parseInt(rawPort) : (isSecureWebSocket ? 443 : 8080);

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: wsHost,
    wsPort: wsPort,
    wssPort: wsPort,
    forceTLS: isSecureWebSocket,
    enabledTransports: ['ws', 'wss'],
});
