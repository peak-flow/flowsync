# FlowSync

A peer-to-peer remote work collaboration tool that combines video chat, screen sharing, text chat, and synchronized pomodoro timer functionality for small teams (2-10 people).

## Features

- **Video Chat** - WebRTC-based peer-to-peer video/audio calls
- **Screen Sharing** - Share your screen with other participants
- **Text Chat** - Real-time messaging with message persistence
- **Pomodoro Timer** - Synchronized work/break timer across all participants
- **Room Management** - Create password-protected rooms with auto-expiry
- **No Account Required** - Join rooms instantly with just a display name

## Tech Stack

| Layer | Technology |
|-------|------------|
| Backend | Laravel 12 (PHP 8.4) |
| Frontend | Alpine.js 3.x + Blade |
| Styling | Tailwind CSS 4.x |
| Build | Vite |
| Signaling | Node.js + Socket.io |
| WebRTC | SimplePeer |
| Database | PostgreSQL |
| Cache/State | Redis |
| Testing | Pest |

## Architecture

FlowSync uses a hybrid Laravel + Node.js architecture:

- **Laravel** - Room management, API endpoints, chat persistence, authentication tokens
- **Node.js Signaling Server** - WebRTC signaling, real-time presence, timer sync
- **Redis** - Shared state between Laravel and Node.js (tokens, participants, timer state)

See [pf-docs/01-architecture-overview.md](pf-docs/01-architecture-overview.md) for detailed architecture documentation.

## Requirements

- PHP 8.4+
- Composer
- Node.js 20.x+
- PostgreSQL
- Redis

## Installation

### 1. Clone and install dependencies

```bash
git clone <repository-url>
cd cowork-claude

# PHP dependencies
composer install

# Node dependencies
npm install
```

### 2. Environment setup

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your configuration:

```env
APP_NAME=FlowSync
APP_URL=https://your-domain.com

# Database
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=flowsync
DB_USERNAME=your_user
DB_PASSWORD=your_password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379

# Signaling Server
SIGNALING_URL=wss://your-signaling-server.com

# TURN Server (optional but recommended for production)
TURN_URL=turn:your-turn-server.com:3478
TURNS_URL=turns:your-turn-server.com:5349
TURN_USERNAME=your_turn_user
TURN_CREDENTIAL=your_turn_password
```

### 3. Database setup

```bash
php artisan migrate
```

### 4. Build frontend assets

```bash
npm run build
```

### 5. Start development servers

```bash
# Laravel (via Herd, Valet, or built-in server)
php artisan serve

# Vite dev server (for hot reload)
npm run dev
```

## Signaling Server

The signaling server is deployed separately. See the [signaling server repository](https://github.com/peak-flow/flowsync-signaling) for setup instructions.

For local development, a reference copy exists in `signaling/`:

```bash
cd signaling
cp .env.example .env
npm install
node server.js
```

Configure `signaling/.env`:

```env
PORT=3001
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
REDIS_PASSWORD=
REDIS_PREFIX=flowsync-database-
```

**Important:** The `REDIS_PREFIX` must match Laravel's Redis prefix (based on `APP_NAME`).

## Usage

1. Visit the home page to create a room
2. Optionally set a room name and password
3. Share the room code or URL with participants
4. Participants enter their display name and join via the lobby
5. Use the video controls to toggle camera/mic
6. Use the timer controls (room creator only) to start pomodoro sessions

## API Endpoints

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/api/rooms` | Create a new room |
| GET | `/api/rooms/{code}` | Get room details |
| POST | `/api/rooms/{code}/join` | Join a room |
| DELETE | `/api/rooms/{code}` | Delete a room (creator only) |
| GET | `/api/rooms/{code}/messages` | Get chat messages |
| POST | `/api/rooms/{code}/messages` | Send a chat message |

## Testing

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/ExampleTest.php

# Run with filter
php artisan test --filter=testName
```

## Development

### Code Style

```bash
# Format PHP code
vendor/bin/pint
```

### Local HTTPS (required for WebRTC)

WebRTC requires HTTPS for camera/microphone access. For local development:

- Use [Laravel Herd](https://herd.laravel.com/) (automatic HTTPS)
- Or use [ngrok](https://ngrok.com/) to tunnel your local server

## License

MIT
