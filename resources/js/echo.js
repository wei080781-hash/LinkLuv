import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 8081,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 8081,
    forceTLS: false,
    enabledTransports: ['ws', 'wss'],
});

// ❗ 加入這段，強制確認 Echo 是否在運作
window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ WebSocket 連線已建立！');
});

window.Echo.connector.pusher.connection.bind('error', (err) => {
    console.error('❌ WebSocket 連線錯誤:', err);
});
