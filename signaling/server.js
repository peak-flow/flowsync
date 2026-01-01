require('dotenv').config();

const { Server } = require('socket.io');
const Redis = require('ioredis');

const PORT = process.env.PORT || 3000;
const REDIS_HOST = process.env.REDIS_HOST || '127.0.0.1';
const REDIS_PORT = process.env.REDIS_PORT || 6378;
const LARAVEL_URL = process.env.LARAVEL_URL || 'http://cowork-claude.test';

const io = new Server(PORT, {
    cors: {
        origin: '*',  // Allow all origins for ngrok
        methods: ['GET', 'POST'],
    },
});

const redis = new Redis({
    host: REDIS_HOST,
    port: REDIS_PORT,
});

redis.on('connect', () => {
    console.log('Connected to Redis');
});

redis.on('error', (err) => {
    console.error('Redis error:', err);
});

// In-memory room tracking
const rooms = new Map();

io.on('connection', async (socket) => {
    console.log('User connected:', socket.id);

    socket.on('join-room', async (data) => {
        const { room_code, token, display_name } = data;

        // Validate token with Redis (Laravel uses prefix 'flowsync-database-')
        const valid = await redis.get(`flowsync-database-room:${room_code}:token:${token}`);
        if (!valid) {
            socket.emit('error', { message: 'Invalid room token', code: 'INVALID_TOKEN' });
            return socket.disconnect();
        }

        // Join socket.io room
        socket.join(room_code);
        socket.data.room_code = room_code;
        socket.data.display_name = display_name;

        // Track participants
        if (!rooms.has(room_code)) {
            rooms.set(room_code, new Map());
        }
        rooms.get(room_code).set(socket.id, { display_name });

        // Update Redis participant count
        await redis.sadd(`room:${room_code}:participants`, socket.id);

        // Get timer state from Redis
        const timerState = await redis.hgetall(`room:${room_code}:timer`);

        // Get presenter
        const presenter = await redis.get(`room:${room_code}:presenter`);

        // Send current state to new user
        socket.emit('room-joined', {
            participants: Array.from(rooms.get(room_code).entries()).map(([id, data]) => ({
                socket_id: id,
                display_name: data.display_name,
            })),
            timer_state: Object.keys(timerState).length ? timerState : null,
            presenter: presenter,
        });

        // Notify others
        socket.to(room_code).emit('user-joined', {
            socket_id: socket.id,
            display_name,
        });

        console.log(`${display_name} joined room ${room_code}`);
    });

    // WebRTC signaling
    socket.on('offer', (data) => {
        socket.to(data.to).emit('offer', {
            from: socket.id,
            offer: data.offer,
        });
    });

    socket.on('answer', (data) => {
        socket.to(data.to).emit('answer', {
            from: socket.id,
            answer: data.answer,
        });
    });

    socket.on('ice-candidate', (data) => {
        socket.to(data.to).emit('ice-candidate', {
            from: socket.id,
            candidate: data.candidate,
        });
    });

    // Timer controls
    socket.on('start-timer', async (data) => {
        const room_code = socket.data.room_code;
        if (!room_code) return;

        const type = data.type || 'work';
        const durations = { work: 1500, short_break: 300, long_break: 900 };
        const remaining = durations[type] || 1500;

        const timerData = {
            status: 'running',
            type: type,
            remaining: remaining.toString(),
            started_at: Date.now().toString(),
        };

        await redis.hmset(`room:${room_code}:timer`, timerData);
        io.to(room_code).emit('timer-update', timerData);
    });

    socket.on('pause-timer', async () => {
        const room_code = socket.data.room_code;
        if (!room_code) return;

        const timer = await redis.hgetall(`room:${room_code}:timer`);
        if (timer.status === 'running') {
            const elapsed = Math.floor((Date.now() - parseInt(timer.started_at)) / 1000);
            const remaining = Math.max(0, parseInt(timer.remaining) - elapsed);

            await redis.hmset(`room:${room_code}:timer`, {
                status: 'paused',
                remaining: remaining.toString(),
                paused_at: Date.now().toString(),
            });

            io.to(room_code).emit('timer-update', {
                status: 'paused',
                remaining: remaining,
            });
        }
    });

    socket.on('resume-timer', async () => {
        const room_code = socket.data.room_code;
        if (!room_code) return;

        const timer = await redis.hgetall(`room:${room_code}:timer`);
        if (timer.status === 'paused') {
            await redis.hmset(`room:${room_code}:timer`, {
                status: 'running',
                started_at: Date.now().toString(),
            });

            io.to(room_code).emit('timer-update', {
                status: 'running',
                remaining: parseInt(timer.remaining),
                started_at: Date.now(),
            });
        }
    });

    socket.on('reset-timer', async () => {
        const room_code = socket.data.room_code;
        if (!room_code) return;

        await redis.del(`room:${room_code}:timer`);
        io.to(room_code).emit('timer-update', { status: 'stopped' });
    });

    // Screen sharing
    socket.on('start-presenting', async () => {
        const room_code = socket.data.room_code;
        if (!room_code) return;

        await redis.set(`room:${room_code}:presenter`, socket.id);
        io.to(room_code).emit('presenter-changed', { presenter_id: socket.id });
    });

    socket.on('stop-presenting', async () => {
        const room_code = socket.data.room_code;
        if (!room_code) return;

        const currentPresenter = await redis.get(`room:${room_code}:presenter`);
        if (currentPresenter === socket.id) {
            await redis.del(`room:${room_code}:presenter`);
            io.to(room_code).emit('presenter-changed', { presenter_id: null });
        }
    });

    // Raise hand
    socket.on('raise-hand', (data) => {
        const room_code = socket.data.room_code;
        if (!room_code) return;

        io.to(room_code).emit('hand-raised', {
            socket_id: socket.id,
            raised: data.raised,
        });
    });

    // Chat message (real-time relay, persistence handled by Laravel)
    socket.on('chat-message', (data) => {
        const room_code = socket.data.room_code;
        if (!room_code) return;

        socket.to(room_code).emit('chat-message', {
            sender_id: socket.id,
            sender_name: socket.data.display_name,
            message: data.message,
            type: data.type || 'text',
            timestamp: Date.now(),
        });
    });

    // Cleanup on disconnect
    socket.on('disconnect', async () => {
        const room_code = socket.data.room_code;

        if (room_code && rooms.has(room_code)) {
            rooms.get(room_code).delete(socket.id);
            socket.to(room_code).emit('user-left', { socket_id: socket.id });

            // Remove from Redis
            await redis.srem(`room:${room_code}:participants`, socket.id);

            // Clear presenter if disconnected user was presenting
            const presenter = await redis.get(`room:${room_code}:presenter`);
            if (presenter === socket.id) {
                await redis.del(`room:${room_code}:presenter`);
                io.to(room_code).emit('presenter-changed', { presenter_id: null });
            }

            // Clean up empty rooms
            if (rooms.get(room_code).size === 0) {
                rooms.delete(room_code);
                // Optionally clean Redis data for empty room
                // await redis.del(`room:${room_code}:timer`);
            }

            console.log(`User ${socket.id} left room ${room_code}`);
        }
    });
});

console.log(`FlowSync signaling server running on port ${PORT}`);
