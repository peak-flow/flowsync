# Code Flow Recommendations: FlowSync

> Generated: 2026-01-01
> Based on: pf-docs/01-architecture-overview.md

## Summary

| Flow | Priority | Components | Effort |
|------|----------|------------|--------|
| Room Creation & Token Generation | High | 4 | Low |
| WebRTC Connection Establishment | High | 5 | High |
| Room Join with Password Validation | Medium | 3 | Low |
| Pomodoro Timer Synchronization | Medium | 3 | Medium |
| Chat Message Flow (Dual Path) | Medium | 4 | Medium |
| Screen Share Toggle | Low | 3 | Low |

---

## Recommended Flows

### 1. WebRTC Connection Establishment (Priority: High)

**Score: 12/12** (Frequency: 3, Complexity: 3, Mystery: 3, Debug value: 3)

**Why document this?**
This is the core "magic" of FlowSync - how two browsers establish a peer-to-peer video connection through the signaling server. Debugging connection failures requires understanding this flow.

**Trigger**: User joins room and `room-joined` event received with existing participants

**Key components**:
- `room.blade.php` - SimplePeer creation, signal handling
- `signaling/server.js` - SDP/ICE relay
- SimplePeer library - WebRTC abstraction
- Redis - Participant tracking
- Browser WebRTC APIs - Actual connection

**Key files to start tracing**:
- `resources/views/room.blade.php:295-361` - `createPeer()` and signal handlers
- `signaling/server.js:87-106` - offer/answer/ice-candidate relay
- `resources/views/room.blade.php:218-234` - `room-joined` triggers peer creation

**Prompt to use**:
```
Create code flow documentation for FlowSync covering:
WebRTC Connection Establishment - from room-joined event through SimplePeer handshake to video stream

Reference the architecture overview at pf-docs/01-architecture-overview.md
Start tracing from resources/views/room.blade.php:createPeer()
```

---

### 2. Room Creation & Token Generation (Priority: High)

**Score: 11/12** (Frequency: 3, Complexity: 2, Mystery: 3, Debug value: 3)

**Why document this?**
Entry point for all sessions. The token generation and Redis storage is critical for understanding how Laravel and Node.js share authentication state.

**Trigger**: User submits "Create Room" form on home page

**Key components**:
- `home.blade.php` - Form submission via fetch
- `RoomController::store` - Room creation, token generation
- `Room` model - Code generation, persistence
- Redis - Token storage with TTL

**Key files to start tracing**:
- `resources/views/home.blade.php:65-93` - `createRoom()` Alpine method
- `app/Http/Controllers/Api/RoomController.php:16-47` - `store()` method
- `app/Models/Room.php:24-31` - `generateCode()` method
- `app/Http/Controllers/Api/RoomController.php:144-148` - `generateRoomToken()`

**Prompt to use**:
```
Create code flow documentation for FlowSync covering:
Room Creation - from form submit through code generation, Redis token storage, to lobby redirect

Reference the architecture overview at pf-docs/01-architecture-overview.md
Start tracing from resources/views/home.blade.php:createRoom()
```

---

### 3. Pomodoro Timer Synchronization (Priority: Medium)

**Score: 9/12** (Frequency: 2, Complexity: 2, Mystery: 3, Debug value: 2)

**Why document this?**
The timer must stay synchronized across all participants. Understanding how Redis stores state and how new joiners get current state is valuable.

**Trigger**: Room creator clicks "Work" or "Break" button

**Key components**:
- `room.blade.php` - Timer UI, Socket.io emit
- `signaling/server.js` - Timer state management
- Redis - Timer hash storage
- All connected clients - Broadcast receivers

**Key files to start tracing**:
- `resources/views/room.blade.php:451-460` - `startTimer()`, `toggleTimer()`
- `signaling/server.js:109-175` - Timer event handlers
- `resources/views/room.blade.php:463-498` - `updateTimer()`, countdown logic

**Prompt to use**:
```
Create code flow documentation for FlowSync covering:
Timer Synchronization - from button click through Redis state to all-participant broadcast

Reference the architecture overview at pf-docs/01-architecture-overview.md
Start tracing from resources/views/room.blade.php:startTimer()
```

---

### 4. Room Join with Password Validation (Priority: Medium)

**Score: 8/12** (Frequency: 3, Complexity: 2, Mystery: 1, Debug value: 2)

**Why document this?**
Standard Laravel flow but shows the password check and how non-creators get tokens.

**Trigger**: User submits lobby form with display name (and password if required)

**Key components**:
- `lobby.blade.php` - Join form, device preview
- `RoomController::join` - Password validation, token generation
- Redis - Token storage

**Key files to start tracing**:
- `resources/views/lobby.blade.php:196-241` - `joinRoom()` Alpine method
- `app/Http/Controllers/Api/RoomController.php:67-95` - `join()` method

**Prompt to use**:
```
Create code flow documentation for FlowSync covering:
Room Join - from lobby form through password validation to token generation

Reference the architecture overview at pf-docs/01-architecture-overview.md
Start tracing from resources/views/lobby.blade.php:joinRoom()
```

---

### 5. Chat Message Flow - Dual Path (Priority: Medium)

**Score: 8/12** (Frequency: 2, Complexity: 2, Mystery: 2, Debug value: 2)

**Why document this?**
Interesting because messages travel two paths: Socket.io for real-time delivery AND REST API for persistence.

**Trigger**: User sends a chat message

**Key components**:
- `room.blade.php` - Message input, dual emit/fetch
- `signaling/server.js` - Real-time relay
- `RoomController::storeMessage` - Persistence
- `RoomMessage` model - Storage

**Key files to start tracing**:
- `resources/views/room.blade.php:506-538` - `sendMessage()` method
- `signaling/server.js:209-220` - `chat-message` handler
- `app/Http/Controllers/Api/RoomController.php:123-142` - `storeMessage()`

**Prompt to use**:
```
Create code flow documentation for FlowSync covering:
Chat Message Dual Path - parallel Socket.io broadcast and REST API persistence

Reference the architecture overview at pf-docs/01-architecture-overview.md
Start tracing from resources/views/room.blade.php:sendMessage()
```

---

## Skip These (Low Value)

| Flow | Why Skip |
|------|----------|
| Screen Share Toggle | Simple browser API call + socket emit, minimal complexity |
| Raise Hand | Single emit/broadcast, trivial flow |
| Leave Room | Cleanup only, `disconnect` event is well-documented |
| Device Selection (Lobby) | Browser `getUserMedia` with device constraints, not FlowSync-specific |
| Get Messages History | Simple `GET` → Eloquent query, standard Laravel |

---

## Suggested Documentation Order

1. **Room Creation & Token Generation** - Foundation for understanding auth flow
2. **WebRTC Connection Establishment** - Core functionality, most complex
3. **Timer Synchronization** - Shows Redis state + broadcast pattern (reused by other features)
4. **Chat Message Dual Path** - Shows hybrid persistence pattern

Document flows 1 & 2 first as they cover the critical path. Flows 3 & 4 can be added later.

---

## Notes

- **Dependency**: WebRTC flow assumes Room Creation flow is understood (token validation)
- **Redis Prefix**: All flows touching Redis must account for `flowsync-database-` prefix
- **SimplePeer**: WebRTC flow requires understanding that SimplePeer abstracts RTCPeerConnection
- **ngrok**: Connection issues may be HTTPS/tunnel related, not flow bugs
