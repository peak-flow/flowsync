# Room Creation Code Flow

## Metadata
| Field | Value |
|-------|-------|
| Repository | `cowork-claude` |
| Commit | `NO_COMMITS` (uncommitted) |
| Documented | `2026-01-01` |
| Trigger | Form submit on home page "Create Room" button |
| End State | Room record in DB, signaling token in Redis, browser redirect to lobby |

## Verification Summary
- [VERIFIED]: 14 claims
- [INFERRED]: 2 claims
- [NOT_FOUND]: 0 items

---

## Flow Diagram

```
[User clicks "Create Room" button]
           │
           ▼
    homeApp.createRoom()
           │
           ▼
    fetch POST /api/rooms
           │
           ▼
    ┌─────────────────────────────────────┐
    │        Laravel API Layer            │
    │                                     │
    │  routes/api.php                     │
    │         │                           │
    │         ▼                           │
    │  RoomController::store()            │
    │         │                           │
    │         ├──→ validate request       │
    │         │                           │
    │         ├──→ Room::generateCode()   │
    │         │         │                 │
    │         │         ▼                 │
    │         │    Str::random(6)         │
    │         │    unique check loop      │
    │         │                           │
    │         ├──→ Room::create()         │
    │         │         │                 │
    │         │         ▼                 │
    │         │    [INSERT rooms table]   │
    │         │                           │
    │         ├──→ generateRoomToken()    │
    │         │         │                 │
    │         │         ▼                 │
    │         │    Str::uuid()            │
    │         │         │                 │
    │         │         ▼                 │
    │         │    Redis::setex()         │
    │         │                           │
    │         ▼                           │
    │    return JSON response             │
    └─────────────────────────────────────┘
           │
           ▼
    Browser receives response
           │
           ├──→ sessionStorage.setItem(token)
           │
           ├──→ sessionStorage.setItem(creator=true)
           │
           ▼
    window.location.href = /room/{code}/lobby
           │
           ▼
    ┌─────────────────────────────────────┐
    │       Laravel Web Layer             │
    │                                     │
    │  routes/web.php                     │
    │         │                           │
    │         ▼                           │
    │  RoomViewController::lobby()        │
    │         │                           │
    │         ├──→ Room::where()->first() │
    │         │                           │
    │         ├──→ isExpired() check      │
    │         │                           │
    │         ▼                           │
    │    return view('lobby')             │
    └─────────────────────────────────────┘
           │
           ▼
    [Lobby page rendered with room data]
```

---

## Detailed Flow

### Step 1: Entry Point - Form Submit
[VERIFIED: resources/views/home.blade.php:12]
```html
<form @submit.prevent="createRoom">
```

User clicks "Create Room" button which triggers Alpine.js `createRoom()` method.

**Data in:** Form fields `{ name: string, password: string }`

---

### Step 2: JavaScript createRoom() Method
[VERIFIED: resources/views/home.blade.php:65-94]
```javascript
async createRoom() {
    this.creating = true;
    this.error = null;

    try {
        const res = await fetch('/api/rooms', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
            },
            body: JSON.stringify(this.newRoom),
        });

        const data = await res.json();

        if (!res.ok) {
            throw new Error(data.error || 'Failed to create room');
        }

        // Store token and redirect to lobby
        sessionStorage.setItem(`room_${data.room_code}_token`, data.signaling_token);
        sessionStorage.setItem(`room_${data.room_code}_creator`, 'true');
        window.location.href = `/room/${data.room_code}/lobby`;
    } catch (e) {
        this.error = e.message;
    } finally {
        this.creating = false;
    }
}
```

**Data in:** `this.newRoom = { name: '', password: '' }`
**Calls:** `POST /api/rooms`
**Side effects:**
- Sets `creating` state to true (disables button)
- On success: stores token in sessionStorage, redirects to lobby
- On error: displays error message

---

### Step 3: API Route Match
[VERIFIED: routes/api.php:12]
```php
Route::post('/rooms', [RoomController::class, 'store']);
```

**Middleware:** `api` (rate limiting, no CSRF)
**Calls:** `RoomController@store`

---

### Step 4: Controller Request Validation
[VERIFIED: app/Http/Controllers/Api/RoomController.php:16-21]
```php
public function store(Request $request): JsonResponse
{
    $request->validate([
        'name' => 'nullable|string|max:255',
        'password' => 'nullable|string|min:4',
    ]);
```

**Data in:** `{ name?: string, password?: string }`
**Validation rules:**
- `name`: optional, string, max 255 chars
- `password`: optional, string, min 4 chars

[INFERRED] On validation failure, Laravel returns HTTP 422 with validation errors.

---

### Step 5: Generate Unique Room Code
[VERIFIED: app/Models/Room.php:24-31]
```php
public static function generateCode(): string
{
    do {
        $code = strtoupper(Str::random(6));
    } while (self::where('code', $code)->exists());

    return $code;
}
```

**Output:** 6-character uppercase alphanumeric string (e.g., `ABC123`)
**Database query:** `SELECT * FROM rooms WHERE code = ?` (loops until unique)

---

### Step 6: Create Room Record
[VERIFIED: app/Http/Controllers/Api/RoomController.php:23-36]
```php
$room = Room::create([
    'code' => Room::generateCode(),
    'name' => $request->name,
    'password_hash' => $request->password ? Hash::make($request->password) : null,
    'creator_ip' => $request->ip(),
    'expires_at' => now()->addHours(24),
    'settings' => [
        'timer_intervals' => [
            'work' => 25,
            'short_break' => 5,
            'long_break' => 15,
        ],
    ],
]);
```

**Database INSERT:**
```sql
INSERT INTO rooms (code, name, password_hash, creator_ip, expires_at, settings, created_at, updated_at)
VALUES (?, ?, ?, ?, ?, ?, ?, ?)
```

**Schema:** [VERIFIED: database/migrations/2026_01_01_043631_create_rooms_table.php:14-23]
| Column | Type | Notes |
|--------|------|-------|
| id | bigint | auto-increment |
| code | varchar(6) | unique, indexed |
| name | varchar(255) | nullable |
| password_hash | varchar(255) | nullable, bcrypt hash |
| max_participants | int | default 10 |
| settings | json | nullable |
| creator_ip | varchar(45) | IPv4/IPv6 |
| expires_at | timestamp | nullable, indexed |
| created_at | timestamp | |
| updated_at | timestamp | |

---

### Step 7: Generate Signaling Token
[VERIFIED: app/Http/Controllers/Api/RoomController.php:144-149]
```php
private function generateRoomToken(string $roomCode): string
{
    $token = Str::uuid()->toString();
    Redis::setex("room:{$roomCode}:token:{$token}", 86400, '1');
    return $token;
}
```

**Redis SETEX:**
- Key: `room:{code}:token:{uuid}` (with Laravel prefix becomes `flowsync-database-room:{code}:token:{uuid}`)
- Value: `'1'`
- TTL: 86400 seconds (24 hours)

**Redis prefix configuration:** [VERIFIED: config/database.php:151]
```php
'prefix' => env('REDIS_PREFIX', Str::slug((string) env('APP_NAME', 'laravel')).'-database-'),
```

With `APP_NAME=FlowSync`, the full Redis key pattern is:
`flowsync-database-room:ABC123:token:550e8400-e29b-41d4-a716-446655440000`

---

### Step 8: Return JSON Response
[VERIFIED: app/Http/Controllers/Api/RoomController.php:40-46]
```php
return response()->json([
    'room_code' => $room->code,
    'signaling_url' => config('app.signaling_url', 'ws://localhost:3000'),
    'signaling_token' => $token,
    'has_password' => $room->hasPassword(),
    'ice_servers' => $this->getIceServers(),
], 201);
```

**Response (HTTP 201):**
```json
{
    "room_code": "ABC123",
    "signaling_url": "ws://localhost:3001",
    "signaling_token": "550e8400-e29b-41d4-a716-446655440000",
    "has_password": false,
    "ice_servers": [
        {"urls": "stun:stun.l.google.com:19302"},
        {"urls": "stun:stun1.l.google.com:19302"}
    ]
}
```

---

### Step 9: Browser Token Storage
[VERIFIED: resources/views/home.blade.php:86-87]
```javascript
sessionStorage.setItem(`room_${data.room_code}_token`, data.signaling_token);
sessionStorage.setItem(`room_${data.room_code}_creator`, 'true');
```

**sessionStorage keys created:**
- `room_ABC123_token` → `"550e8400-e29b-41d4-a716-446655440000"`
- `room_ABC123_creator` → `"true"`

[INFERRED] Creator flag used later to show room management controls.

---

### Step 10: Redirect to Lobby
[VERIFIED: resources/views/home.blade.php:88]
```javascript
window.location.href = `/room/${data.room_code}/lobby`;
```

Browser navigates to `/room/ABC123/lobby`

---

### Step 11: Lobby Route Match
[VERIFIED: routes/web.php:8]
```php
Route::get('/room/{code}/lobby', [RoomViewController::class, 'lobby'])->name('lobby');
```

**Calls:** `RoomViewController@lobby`

---

### Step 12: Lobby Controller
[VERIFIED: app/Http/Controllers/RoomViewController.php:16-28]
```php
public function lobby(string $code): View
{
    $room = Room::where('code', $code)->firstOrFail();

    if ($room->isExpired()) {
        abort(410, 'Room has expired');
    }

    return view('lobby', [
        'room' => $room,
        'signalingUrl' => env('SIGNALING_URL', 'http://localhost:3001'),
    ]);
}
```

**Database query:** `SELECT * FROM rooms WHERE code = ? LIMIT 1`
**Expiry check:** [VERIFIED: app/Models/Room.php:48-51]
```php
public function isExpired(): bool
{
    return $this->expires_at && $this->expires_at->isPast();
}
```

**Returns:** `lobby.blade.php` view with room data

---

## External Calls

### Database
| Operation | Table | Query | Location |
|-----------|-------|-------|----------|
| SELECT | rooms | Check code uniqueness | Room.php:28 |
| INSERT | rooms | Create room record | RoomController.php:23-36 |
| SELECT | rooms | Load room for lobby | RoomViewController.php:18 |

### Redis
| Operation | Key Pattern | TTL | Location |
|-----------|-------------|-----|----------|
| SETEX | `flowsync-database-room:{code}:token:{uuid}` | 24h | RoomController.php:147 |

---

## Data Transformations

| Stage | Data Shape |
|-------|------------|
| Form input | `{ name: string, password: string }` |
| API request body | `{ name?: string, password?: string }` |
| Room model | `{ id, code, name, password_hash, creator_ip, expires_at, settings, ... }` |
| API response | `{ room_code, signaling_url, signaling_token, has_password, ice_servers }` |
| sessionStorage | `room_{code}_token`, `room_{code}_creator` |
| View data | `{ room: Room, signalingUrl: string }` |

---

## Events Fired

None. This flow does not dispatch any Laravel events.

---

## Known Issues

1. **Direct env() usage in controller**
   [VERIFIED: app/Http/Controllers/RoomViewController.php:26]
   ```php
   'signalingUrl' => env('SIGNALING_URL', 'http://localhost:3001'),
   ```
   Should use `config('app.signaling_url')` per Laravel conventions.

2. **No Form Request validation class**
   [VERIFIED: app/Http/Controllers/Api/RoomController.php:18-21]
   Inline validation in controller. Should use a dedicated `StoreRoomRequest` class.

3. **No rate limiting on room creation**
   [INFERRED] API routes use default rate limiting but no explicit protection against room creation spam.

4. **Race condition in code generation**
   [VERIFIED: app/Models/Room.php:26-28]
   The uniqueness check and insert are not atomic. Under high concurrency, duplicate code collision is theoretically possible (though unlikely with 6-char alphanumeric space).
