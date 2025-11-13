import Pusher from 'pusher-js'; // Echo uses the Pusher client library
import Echo from "laravel-echo";

window.Pusher = Pusher;

window.Echo = new Echo({
    // Make sure this matches the BROADCAST_DRIVER in .env
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST, // Usually 127.0.0.1 or localhost
    wsPort: import.meta.env.VITE_REVERB_PORT, // Usually 8080
    wssPort: import.meta.env.VITE_REVERB_PORT,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
    enabledTransports: ['ws', 'wss'],
});

// Optional: Test connection to public channel (works without login)
window.Echo.channel('upload-channel')
    .listen('UploadEvent', (e) => {
        console.log("Public event received:", e);
    });
