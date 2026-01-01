# FlowSync Architecture Overview

## Metadata
| Field | Value |
|-------|-------|
| Repository | `cowork-claude` |
| Commit | `NO_COMMITS` (uncommitted) |
| Documented | `2026-01-01` |
| Verification Status | `Verified` |

## Verification Summary
- [VERIFIED]: 52 claims
- [INFERRED]: 3 claims
- [NOT_FOUND]: 2 items
- [ASSUMED]: 2 items

---

## 0. System Classification
| Field | Value |
|-------|-------|
| Category | Traditional Code |
| Type | Framework backend (Hybrid Laravel + Node.js) |
| Evidence | `composer.json` with `laravel/framework`, `signaling/server.js` with Socket.io |
| Overlay Loaded | No |
| Confidence | `[VERIFIED]` |

## Example Reference
| Field | Value |
|-------|-------|
| Example Read | `.pf-agent-system-mapper/examples/laravel/good-architecture-doc-example.md` |
| Key Format Elements Noted | Tables for data flow, verification tags on all claims, Section 3 defers to code-flows |

---

## 1. System Purpose

FlowSync is a peer-to-peer remote work collaboration tool that combines video chat, screen sharing, text chat, and synchronized pomodoro timer functionality for small teams (2-10 people). It uses a hybrid Laravel + Node.js architecture where Laravel handles room management, persistence, and API endpoints while Node.js provides WebSocket-based real-time signaling for WebRTC peer connections.

[VERIFIED: prd.md:1-4] The PRD describes the system as:
```
FlowSync is a peer-to-peer remote work collaboration tool that combines video chat,
screen sharing, text chat, and pomodoro timer functionality for small teams (2-10 people).
```

---

## 2. Component Map

| Component | Location | Responsibility | Verified |
|-----------|----------|----------------|----------|
| Room Model | `app/Models/Room.php` | Room entity with code generation, expiry, relationships | [VERIFIED] |
| RoomMessage Model | `app/Models/RoomMessage.php` | Chat message persistence | [VERIFIED] |
| RoomSession Model | `app/Models/RoomSession.php` | User session tracking (analytics) | [VERIFIED] |
| RoomController (API) | `app/Http/Controllers/Api/RoomController.php` | REST API for room CRUD, join, messages | [VERIFIED] |
| RoomViewController | `app/Http/Controllers/RoomViewController.php` | Web views for home, lobby, room | [VERIFIED] |
| Signaling Server | `signaling/server.js` | Socket.io WebRTC signaling, timer sync, presence | [VERIFIED] |
| Home View | `resources/views/home.blade.php` | Create/join room UI | [VERIFIED] |
| Lobby View | `resources/views/lobby.blade.php` | Pre-join camera preview, device selection | [VERIFIED] |
| Room View | `resources/views/room.blade.php` | Main collaboration UI with video grid, chat, timer | [VERIFIED] |
| App Layout | `resources/views/components/layouts/app.blade.php` | Base layout + SimplePeer CDN load | [VERIFIED] |
| JS Entry | `resources/js/app.js` | Alpine.js + Socket.io client initialization | [VERIFIED] |
| Migrations | `database/migrations/` | Schema for rooms, room_messages, room_sessions | [VERIFIED] |

[NOT_FOUND: searched "Event", "Listener", "Job" in app/] No event/listener/job classes exist yet.
[NOT_FOUND: searched "FormRequest" in app/] No form request classes; validation is inline in controller.

---

## 3. Execution Surfaces & High-Level Data Movement

### 3.1 Primary Execution Surfaces

| Entry Surface | Type | Primary Components Involved | Evidence |
|--------------|------|-----------------------------|----------|
| `GET /` | Web | RoomViewController, home.blade.php | [VERIFIED: routes/web.php:6] |
| `GET /room/{code}/lobby` | Web | RoomViewController, Room model, lobby.blade.php | [VERIFIED: routes/web.php:8] |
| `GET /room/{code}` | Web | RoomViewController, Room model, room.blade.php | [VERIFIED: routes/web.php:7] |
| `POST /api/rooms` | API | RoomController::store, Room model, Redis | [VERIFIED: routes/api.php:12] |
| `GET /api/rooms/{code}` | API | RoomController::show, Room model | [VERIFIED: routes/api.php:13] |
| `POST /api/rooms/{code}/join` | API | RoomController::join, Room model, Redis | [VERIFIED: routes/api.php:14] |
| `DELETE /api/rooms/{code}` | API | RoomController::destroy, Room model | [VERIFIED: routes/api.php:15] |
| `GET /api/rooms/{code}/messages` | API | RoomController::messages, RoomMessage model | [VERIFIED: routes/api.php:18] |
| `POST /api/rooms/{code}/messages` | API | RoomController::storeMessage, RoomMessage model | [VERIFIED: routes/api.php:19] |
| Socket.io connection | WebSocket | signaling/server.js, Redis | [VERIFIED: signaling/server.js:34] |

### 3.2 High-Level Data Movement

| Stage | Input Type | Output Type | Participating Components |
|-------|------------|-------------|--------------------------|
| Room Creation | HTTP POST with name/password | JSON with room_code, signaling_token | RoomController, Room model, Redis |
| Room Join | HTTP POST with display_name/password | JSON with signaling_token, ICE servers | RoomController, Room model, Redis |
| WebSocket Auth | Token from Laravel API | Validated session | Node.js server, Redis token lookup |
| WebRTC Signaling | SDP offers/answers, ICE candidates | Relayed to target peer | Node.js server, Socket.io rooms |
| Timer Sync | Timer commands (start/pause/reset) | Broadcast timer state | Node.js server, Redis hash |
| Chat Persistence | Message via REST API | Stored message | RoomController, RoomMessage model |
| Chat Realtime | Message via Socket.io | Broadcast to room | Node.js server |

### 3.3 Pointers to Code Flow Documentation

The following operations are candidates for detailed flow tracing in `02-code-flows.md`:

- **Room Creation Flow** - Laravel API creates room, generates Redis token
- **Room Join Flow** - Password validation, token generation, expiry extension
- **WebRTC Connection Flow** - Socket.io signaling between SimplePeer instances
- **Pomodoro Timer Sync Flow** - Timer state management across participants

---

## 3b. Frontend to Backend Interaction Map

| Frontend Source | Trigger Type | Backend Target | Handler / Method | Evidence |
|-----------------|--------------|----------------|------------------|----------|
| home.blade.php | form submit (fetch) | `/api/rooms` | RoomController::store | [VERIFIED: home.blade.php:70-78] |
| home.blade.php | form submit (redirect) | `/room/{code}/lobby` | RoomViewController::lobby | [VERIFIED: home.blade.php:88] |
| lobby.blade.php | form submit (fetch) | `/api/rooms/{code}/join` | RoomController::join | [VERIFIED: lobby.blade.php:206-216] |
| room.blade.php | Socket.io emit | Node.js server | `join-room` handler | [VERIFIED: room.blade.php:210-215] |
| room.blade.php | Socket.io emit | Node.js server | `offer/answer/ice-candidate` | [VERIFIED: room.blade.php:327-332] |
| room.blade.php | Socket.io emit | Node.js server | `start-timer/pause-timer/resume-timer` | [VERIFIED: room.blade.php:451-460] |
| room.blade.php | Socket.io emit + fetch | Node.js + Laravel API | `chat-message` + POST messages | [VERIFIED: room.blade.php:517-532] |

---

## 4. File/Folder Conventions

| Pattern | Location | Example |
|---------|----------|---------|
| Models | `app/Models/` | `Room.php`, `RoomMessage.php`, `RoomSession.php` |
| API Controllers | `app/Http/Controllers/Api/` | `RoomController.php` |
| Web Controllers | `app/Http/Controllers/` | `RoomViewController.php` |
| Migrations | `database/migrations/` | `2026_01_01_043631_create_rooms_table.php` |
| Views | `resources/views/` | `home.blade.php`, `lobby.blade.php`, `room.blade.php` |
| Blade Components | `resources/views/components/` | `layouts/app.blade.php` |
| JavaScript | `resources/js/` | `app.js`, `bootstrap.js` |
| CSS | `resources/css/` | `app.css` |
| Signaling Server | `signaling/` | `server.js`, `package.json`, `.env.example`, `.gitignore` |
| Tests | `tests/Feature/`, `tests/Unit/` | `ExampleTest.php` |

[ASSUMED: Laravel convention] Routes in `routes/web.php` for web, `routes/api.php` for API.

---

## 5. External Dependencies

### Redis
[VERIFIED: app/Http/Controllers/Api/RoomController.php:147]
```php
Redis::setex("room:{$roomCode}:token:{$token}", 86400, '1');
```

**Configuration:**
- Laravel Redis: `config/database.php:145-181` (port from `REDIS_PORT` env, default 6379)
- Signaling Redis: `signaling/.env` (port 6378) [VERIFIED: signaling/.env:3]

Used for:
- Session token storage (Laravel generates, Node.js validates)
- Timer state persistence
- Presenter tracking
- Participant count

### STUN Servers
[VERIFIED: app/Http/Controllers/Api/RoomController.php:153-156]
```php
return [
    ['urls' => 'stun:stun.l.google.com:19302'],
    ['urls' => 'stun:stun1.l.google.com:19302'],
];
```

**Also configured in frontend:**
[VERIFIED: resources/views/room.blade.php:310-313]
```javascript
config: {
    iceServers: [
        { urls: 'stun:stun.l.google.com:19302' },
        { urls: 'stun:stun1.l.google.com:19302' },
    ]
}
```

### NPM Packages (Frontend)
[VERIFIED: package.json:20-23]
- `socket.io-client` - WebSocket communication (bundled via Vite)
- `simple-peer` - WebRTC abstraction (in package.json but **loaded via CDN**)
- `alpinejs` - Reactive UI
- `tailwindcss` - Styling

**SimplePeer Loading:**
[VERIFIED: resources/views/components/layouts/app.blade.php:8]
```html
<script src="https://unpkg.com/simple-peer@9.11.1/simplepeer.min.js"></script>
```
SimplePeer is loaded via CDN rather than bundled because it requires Node.js polyfills that Vite doesn't provide by default.

### NPM Packages (Signaling Server)
[VERIFIED: signaling/package.json:17-23]
- `socket.io` - WebSocket server
- `ioredis` - Redis client
- `dotenv` - Environment config

### Local Development Requirements

**ngrok (or similar tunnel):**
WebRTC requires HTTPS for `getUserMedia()`. Local development uses ngrok to tunnel the signaling server over HTTPS.

- `SIGNALING_URL` in Laravel `.env` should point to ngrok tunnel URL
- Signaling server runs on port 3001 [VERIFIED: signaling/.env:1]

**Signaling Server CORS:**
[VERIFIED: signaling/server.js:13-15]
```javascript
cors: {
    origin: '*',  // Allow all origins for ngrok
    methods: ['GET', 'POST'],
},
```
CORS is configured to allow all origins to support ngrok tunnels with dynamic subdomains.

### Signaling Server Configuration Files

| File | Purpose | Evidence |
|------|---------|----------|
| `signaling/.env` | Runtime config (PORT, REDIS_HOST, REDIS_PORT, LARAVEL_URL) | [VERIFIED: signaling/.env] |
| `signaling/.env.example` | Template for .env | [VERIFIED: signaling/.env.example] |
| `signaling/.gitignore` | Excludes node_modules/, .env, certs/ | [VERIFIED: signaling/.gitignore] |

---

## 6. Known Issues & Risks

### Missing Form Request Validation Classes
[VERIFIED: app/Http/Controllers/Api/RoomController.php:17-21]
```php
$request->validate([
    'name' => 'nullable|string|max:255',
    'password' => 'nullable|string|min:4',
]);
```
Validation is inline in controller methods. Should use Form Request classes per Laravel conventions.

### Direct env() Usage in Controllers
[VERIFIED: app/Http/Controllers/RoomViewController.php:26-27]
```php
'signalingUrl' => env('SIGNALING_URL', 'http://localhost:3001'),
```
Uses `env()` directly instead of config value. Should use `config('app.signaling_url')`.

### IP-Based Creator Authorization
[VERIFIED: app/Http/Controllers/Api/RoomController.php:102]
```php
if ($room->creator_ip !== $request->ip()) {
```
Room deletion uses IP address for authorization, which is unreliable (proxies, dynamic IPs).

### No CSRF Protection on API Routes
[INFERRED] API routes don't use CSRF middleware (standard for Sanctum APIs, but no auth on room endpoints).

### Redis Prefix Mismatch Documentation
[VERIFIED: signaling/server.js:41]
```javascript
const valid = await redis.get(`flowsync-database-room:${room_code}:token:${token}`);
```
Node.js expects `flowsync-database-` prefix. Laravel Redis prefix must match.

### Unused RoomSession Model
[VERIFIED: app/Models/RoomSession.php] Model exists but no code creates RoomSession records.
[INFERRED] Intended for analytics but not yet implemented.

### No Tests for Room Functionality
[VERIFIED: tests/] Only example tests exist. No tests for RoomController or Room model.

---

## 7. Entry Points Summary

| Route/Entry | Method | Handler | Middleware | Verified |
|-------------|--------|---------|------------|----------|
| `/` | GET | RoomViewController@home | web | [VERIFIED: routes/web.php:6] |
| `/room/{code}` | GET | RoomViewController@room | web | [VERIFIED: routes/web.php:7] |
| `/room/{code}/lobby` | GET | RoomViewController@lobby | web | [VERIFIED: routes/web.php:8] |
| `/api/user` | GET | Closure | auth:sanctum | [VERIFIED: routes/api.php:7-9] |
| `/api/rooms` | POST | RoomController@store | api | [VERIFIED: routes/api.php:12] |
| `/api/rooms/{code}` | GET | RoomController@show | api | [VERIFIED: routes/api.php:13] |
| `/api/rooms/{code}/join` | POST | RoomController@join | api | [VERIFIED: routes/api.php:14] |
| `/api/rooms/{code}` | DELETE | RoomController@destroy | api | [VERIFIED: routes/api.php:15] |
| `/api/rooms/{code}/messages` | GET | RoomController@messages | api | [VERIFIED: routes/api.php:18] |
| `/api/rooms/{code}/messages` | POST | RoomController@storeMessage | api | [VERIFIED: routes/api.php:19] |
| Socket.io (port 3001) | WebSocket | signaling/server.js | - | [VERIFIED: signaling/.env:1] |

---

## 8. Technology Stack Summary

| Layer | Technology |
|-------|------------|
| Backend Framework | Laravel 12 |
| Frontend Framework | Alpine.js 3.x + Blade |
| CSS Framework | Tailwind CSS 4.x |
| Build Tool | Vite 7.x |
| Signaling Server | Node.js + Socket.io 4.x |
| WebRTC Library | SimplePeer 9.x |
| Primary Database | PostgreSQL [VERIFIED: .env.example:23] |
| Cache/Session | Redis |
| Testing | Pest 4.x |
| PHP Version | 8.4 |
| Node.js Version | 20.x LTS (recommended) |

---

## 9. Data Models

### Room (`app/Models/Room.php`)

[VERIFIED: database/migrations/2026_01_01_043631_create_rooms_table.php:14-27]
```php
$table->id();
$table->string('code', 6)->unique();
$table->string('name')->nullable();
$table->string('password_hash')->nullable();
$table->integer('max_participants')->default(10);
$table->json('settings')->nullable();
$table->string('creator_ip', 45)->nullable();
$table->timestamp('expires_at')->nullable();
$table->timestamps();
```

Relationships:
- [VERIFIED: app/Models/Room.php:33-36] `hasMany(RoomMessage::class)`
- [VERIFIED: app/Models/Room.php:38-41] `hasMany(RoomSession::class)`

Key Methods:
- [VERIFIED: app/Models/Room.php:24-31] `generateCode()` - Creates unique 6-char uppercase code
- [VERIFIED: app/Models/Room.php:43-46] `hasPassword()` - Checks if room is password protected
- [VERIFIED: app/Models/Room.php:48-51] `isExpired()` - Checks if room has expired

### RoomMessage (`app/Models/RoomMessage.php`)

[VERIFIED: database/migrations/2026_01_01_043632_create_room_messages_table.php:14-23]
```php
$table->id();
$table->foreignId('room_id')->constrained()->cascadeOnDelete();
$table->string('sender_name', 100)->nullable();
$table->string('sender_session_id')->nullable();
$table->text('message')->nullable();
$table->enum('type', ['text', 'system', 'emoji'])->default('text');
$table->timestamp('created_at')->useCurrent();
```

### RoomSession (`app/Models/RoomSession.php`)

[VERIFIED: database/migrations/2026_01_01_043633_create_room_sessions_table.php:14-21]
```php
$table->id();
$table->foreignId('room_id')->constrained()->cascadeOnDelete();
$table->string('session_id');
$table->string('display_name', 100)->nullable();
$table->timestamp('joined_at')->useCurrent();
$table->timestamp('left_at')->nullable();
```

[ASSUMED: Not yet used] This model appears designed for tracking participant sessions for analytics but is not populated by current code.

---

## 10. Redis Data Structures

[VERIFIED: signaling/server.js and app/Http/Controllers/Api/RoomController.php]

**Important:** Laravel applies a prefix to all Redis keys. The prefix is configured in `config/database.php:151`:
```php
'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
```

With `APP_NAME=FlowSync`, keys become `flowsync-database-{key}`. The signaling server must use this prefix.

| Key Pattern (without prefix) | Full Key (with prefix) | Type | TTL | Purpose |
|------------------------------|------------------------|------|-----|---------|
| `room:{code}:token:{uuid}` | `flowsync-database-room:{code}:token:{uuid}` | String | 24h | Session token validation |
| `room:{code}:participants` | `flowsync-database-room:{code}:participants` | Set | - | Active participant socket IDs |
| `room:{code}:timer` | `flowsync-database-room:{code}:timer` | Hash | - | Timer state (status, type, remaining, started_at) |
| `room:{code}:presenter` | `flowsync-database-room:{code}:presenter` | String | - | Current presenter socket ID |

[VERIFIED: signaling/server.js:41] Node.js validates with prefix: `flowsync-database-room:${room_code}:token:${token}`

---

## Appendix: Socket.io Events

### Client to Server
| Event | Payload | Handler |
|-------|---------|---------|
| `join-room` | `{room_code, token, display_name}` | [VERIFIED: signaling/server.js:37-84] |
| `offer` | `{to, offer}` | [VERIFIED: signaling/server.js:87-92] |
| `answer` | `{to, answer}` | [VERIFIED: signaling/server.js:94-99] |
| `ice-candidate` | `{to, candidate}` | [VERIFIED: signaling/server.js:101-106] |
| `start-timer` | `{type}` | [VERIFIED: signaling/server.js:109-126] |
| `pause-timer` | `{}` | [VERIFIED: signaling/server.js:128-148] |
| `resume-timer` | `{}` | [VERIFIED: signaling/server.js:150-167] |
| `reset-timer` | `{}` | [VERIFIED: signaling/server.js:169-175] |
| `start-presenting` | `{}` | [VERIFIED: signaling/server.js:178-184] |
| `stop-presenting` | `{}` | [VERIFIED: signaling/server.js:186-195] |
| `raise-hand` | `{raised}` | [VERIFIED: signaling/server.js:198-206] |
| `chat-message` | `{message, type}` | [VERIFIED: signaling/server.js:209-220] |

### Server to Client
| Event | Payload |
|-------|---------|
| `room-joined` | `{participants, timer_state, presenter}` |
| `user-joined` | `{socket_id, display_name}` |
| `user-left` | `{socket_id}` |
| `offer` | `{from, offer}` |
| `answer` | `{from, answer}` |
| `ice-candidate` | `{from, candidate}` |
| `timer-update` | `{status, type, remaining, started_at}` |
| `presenter-changed` | `{presenter_id}` |
| `hand-raised` | `{socket_id, raised}` |
| `chat-message` | `{sender_id, sender_name, message, type, timestamp}` |
| `error` | `{message, code}` |
