import Echo from 'laravel-echo';

import Pusher from 'pusher-js';
window.Pusher = Pusher;

// 動態判斷：當 .env 中的 VITE_REVERB_SCHEME 為 'https' 時啟用加密
const isSecure = (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https';

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: isSecure,
    enabledTransports: ['ws', 'wss'],
});

// ❗ 加入這段，強制確認 Echo 是否在運作
window.Echo.connector.pusher.connection.bind('connected', () => {
    console.log('✅ WebSocket 連線已建立！');
});

window.Echo.connector.pusher.connection.bind('error', (err) => {
    console.error('❌ WebSocket 連線錯誤:', err);
});
