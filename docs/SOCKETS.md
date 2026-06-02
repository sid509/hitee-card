# Real-time WebSocket Documentation (Laravel Reverb)

This project uses **Laravel Reverb** for real-time communication. Reverb is a first-party, high-performance WebSocket server for Laravel.

## 1. Server Requirements

To run WebSockets in production, your server needs:

- **PHP 8.2+**: Reverb requires a modern PHP version.
- **Node.js & NPM**: Required for building frontend assets (Laravel Echo).
- **Redis (Optional but Recommended)**: Used for horizontal scaling of Reverb.
- **Daemon Process Manager**: You MUST use a process manager like **Supervisor** or **PM2** to keep the Reverb server running in the background.
- **SSL/TLS**: Production environments require WebSockets over HTTPS (WSS). This usually involves an Nginx or Apache reverse proxy with SSL termination.
- **Open Ports**: Ensure port `8080` (or your configured Reverb port) is open in your firewall.

## 2. Server Installation & Execution

### Installation
```bash
composer require laravel/reverb
php artisan reverb:install
```

### Running the Server
In development:
```bash
php artisan reverb:start
```

In production (via Supervisor):
```ini
[program:reverb]
command=php /path/to/your/project/artisan reverb:start
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/path/to/your/project/storage/logs/reverb.log
```

## 3. Frontend Implementation (Laravel Echo)

### Installation
```bash
npm install --save-dev laravel-echo pusher-js
```

### Configuration
Update `resources/js/echo.js`:
```javascript
import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```

## 4. Available Channels and Events

### A. Notifications (Private Channel)
Used for real-time notification count updates.

- **Channel**: `private-user.{userId}`
- **Event**: `notification.unseen_count`
- **Payload**: `{ "userId": 1, "unseenCount": 5 }`

**Usage Example:**
```javascript
window.Echo.private(`user.${userId}`)
    .listen('.notification.unseen_count', (e) => {
        console.log('New unseen count:', e.unseenCount);
        // Update your UI counter
    });
```

### B. Vehicle Tracking (Public Channel)
Used for real-time bus locations on the map.

- **Channel**: `buses`
- **Event**: `bus.location_updated`
- **Payload**: `{ "busId": 12, "latitude": 27.7172, "longitude": 85.3240, "heading": 90, "speed": 40 }`

**Usage Example:**
```javascript
window.Echo.channel('buses')
    .listen('.bus.location_updated', (e) => {
        console.log('Bus moved:', e.busId, e.latitude, e.longitude);
        // Move marker on the map
    });
```

## 5. API Endpoints for Integration

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/notifications/unseen-count` | `GET` | Get current unseen count. |
| `/api/notifications/{id}/mark-seen` | `POST` | Mark notification as seen **(triggers socket)**. |
| `/api/notifications/mark-all-seen` | `POST` | Mark all as seen **(triggers socket)**. |
| `/api/buses` (Background task) | `N/A` | Bus locations are updated via CLI **(triggers socket)**. |

---
*Note: Ensure your `.env` has `BROADCAST_CONNECTION=reverb` and valid `REVERB_APP_ID`, `REVERB_APP_KEY`, and `REVERB_APP_SECRET`.*
