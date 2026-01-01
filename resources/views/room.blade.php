<x-layouts.app title="{{ $room->name ?? $room->code }} - FlowSync">
    <div class="h-full flex flex-col" x-data="roomApp()" x-init="init()">
        <!-- Header -->
        <header class="bg-gray-800 px-4 py-2 flex items-center justify-between">
            <div class="flex items-center gap-4">
                <h1 class="font-semibold">{{ $room->name ?? 'FlowSync Room' }}</h1>
                <span class="text-gray-400 text-sm font-mono">{{ $room->code }}</span>
                <span class="text-sm" :class="connected ? 'text-green-400' : 'text-red-400'">
                    <span x-text="connected ? 'Connected' : 'Connecting...'"></span>
                </span>
            </div>

            <!-- Timer -->
            <div class="flex items-center gap-2 bg-gray-700 rounded px-3 py-1">
                <span class="text-2xl font-mono" x-text="formatTime(timer.remaining)"></span>
                <span class="text-xs text-gray-400" x-text="timer.type"></span>
                <template x-if="isCreator">
                    <div class="flex gap-1 ml-2">
                        <button @click="startTimer('work')" class="text-xs bg-blue-600 hover:bg-blue-700 px-2 py-1 rounded">Work</button>
                        <button @click="startTimer('short_break')" class="text-xs bg-green-600 hover:bg-green-700 px-2 py-1 rounded">Break</button>
                        <button @click="toggleTimer" class="text-xs bg-gray-600 hover:bg-gray-500 px-2 py-1 rounded" x-text="timer.status === 'running' ? 'Pause' : 'Start'"></button>
                    </div>
                </template>
            </div>

            <div class="flex items-center gap-2">
                <span class="text-sm text-gray-400"><span x-text="Object.keys(peers).length + 1"></span> participants</span>
                <button @click="leaveRoom" class="bg-red-600 hover:bg-red-700 px-3 py-1 rounded text-sm">Leave</button>
            </div>
        </header>

        <!-- Main Content -->
        <div class="flex-1 flex overflow-hidden">
            <!-- Video Grid -->
            <div class="flex-1 p-4 overflow-auto">
                <div class="grid gap-4" :class="gridClass">
                    <!-- Local Video -->
                    <div class="relative bg-gray-800 rounded-lg overflow-hidden aspect-video">
                        <video x-ref="localVideo" autoplay muted playsinline class="w-full h-full object-cover"></video>
                        <div class="absolute bottom-2 left-2 bg-black/50 px-2 py-1 rounded text-sm">
                            <span x-text="displayName"></span> (You)
                        </div>
                        <div x-show="!videoEnabled" class="absolute inset-0 flex items-center justify-center bg-gray-900">
                            <div class="w-16 h-16 bg-gray-700 rounded-full flex items-center justify-center text-2xl" x-text="displayName.charAt(0).toUpperCase()"></div>
                        </div>
                    </div>

                    <!-- Remote Videos -->
                    <template x-for="(peer, peerId) in peers" :key="peerId">
                        <div class="relative bg-gray-800 rounded-lg overflow-hidden aspect-video">
                            <video :id="'video-' + peerId" autoplay playsinline class="w-full h-full object-cover"></video>
                            <div class="absolute bottom-2 left-2 bg-black/50 px-2 py-1 rounded text-sm">
                                <span x-text="peer.displayName"></span>
                                <span x-show="peer.handRaised" class="ml-1">&#9995;</span>
                            </div>
                            <div x-show="presenter === peerId" class="absolute top-2 right-2 bg-blue-600 px-2 py-1 rounded text-xs">
                                Presenting
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Sidebar (Chat) -->
            <div class="w-80 bg-gray-800 flex flex-col" x-show="showChat">
                <div class="p-3 border-b border-gray-700 font-semibold">Chat</div>
                <div class="flex-1 overflow-auto p-3 space-y-2" x-ref="chatMessages">
                    <template x-for="msg in messages" :key="msg.timestamp">
                        <div :class="msg.type === 'system' ? 'text-gray-500 text-sm italic' : ''">
                            <template x-if="msg.type !== 'system'">
                                <span class="font-semibold text-blue-400" x-text="msg.sender_name + ': '"></span>
                            </template>
                            <span x-text="msg.message"></span>
                        </div>
                    </template>
                </div>
                <form @submit.prevent="sendMessage" class="p-3 border-t border-gray-700">
                    <div class="flex gap-2">
                        <input type="text" x-model="newMessage" placeholder="Type a message..."
                            class="flex-1 bg-gray-700 rounded px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                        <button type="submit" class="bg-blue-600 hover:bg-blue-700 px-3 py-2 rounded">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                            </svg>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Controls Bar -->
        <div class="bg-gray-800 px-4 py-3 flex items-center justify-center gap-4">
            <button @click="toggleAudio" :class="audioEnabled ? 'bg-gray-700' : 'bg-red-600'"
                class="p-3 rounded-full hover:bg-opacity-80 transition" title="Toggle Microphone">
                <svg x-show="audioEnabled" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                </svg>
                <svg x-show="!audioEnabled" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                </svg>
            </button>

            <button @click="toggleVideo" :class="videoEnabled ? 'bg-gray-700' : 'bg-red-600'"
                class="p-3 rounded-full hover:bg-opacity-80 transition" title="Toggle Camera">
                <svg x-show="videoEnabled" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                </svg>
                <svg x-show="!videoEnabled" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                </svg>
            </button>

            <button @click="toggleScreenShare" :class="isPresenting ? 'bg-blue-600' : 'bg-gray-700'"
                class="p-3 rounded-full hover:bg-opacity-80 transition" title="Share Screen">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </button>

            <button @click="toggleHand" :class="handRaised ? 'bg-yellow-600' : 'bg-gray-700'"
                class="p-3 rounded-full hover:bg-opacity-80 transition" title="Raise Hand">
                <span class="text-xl">&#9995;</span>
            </button>

            <button @click="showChat = !showChat" :class="showChat ? 'bg-blue-600' : 'bg-gray-700'"
                class="p-3 rounded-full hover:bg-opacity-80 transition" title="Toggle Chat">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </button>
        </div>
    </div>

    <script>
        // io and SimplePeer are available globally from app.js

        function roomApp() {
            return {
                socket: null,
                connected: false,
                displayName: sessionStorage.getItem('room_{{ $room->code }}_name') || 'Guest',
                token: sessionStorage.getItem('room_{{ $room->code }}_token'),
                isCreator: sessionStorage.getItem('room_{{ $room->code }}_creator') === 'true',

                localStream: null,
                videoEnabled: sessionStorage.getItem('room_{{ $room->code }}_video') !== 'false',
                audioEnabled: sessionStorage.getItem('room_{{ $room->code }}_audio') !== 'false',

                peers: {},
                peerConnections: {},
                presenter: null,
                isPresenting: false,
                handRaised: false,

                timer: { remaining: 0, type: 'work', status: 'stopped' },

                messages: [],
                newMessage: '',
                showChat: true,

                get gridClass() {
                    const count = Object.keys(this.peers).length + 1;
                    if (count === 1) return 'grid-cols-1 max-w-2xl mx-auto';
                    if (count === 2) return 'grid-cols-2';
                    if (count <= 4) return 'grid-cols-2';
                    if (count <= 6) return 'grid-cols-3';
                    return 'grid-cols-4';
                },

                async init() {
                    if (!this.token) {
                        window.location.href = '/room/{{ $room->code }}/lobby';
                        return;
                    }

                    await this.startLocalStream();
                    this.connectSocket();
                },

                async startLocalStream() {
                    try {
                        // Check if mediaDevices is available (requires HTTPS or localhost)
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            console.warn('getUserMedia not available - site must be served over HTTPS or localhost');
                            this.videoEnabled = false;
                            this.audioEnabled = false;
                            return;
                        }

                        this.localStream = await navigator.mediaDevices.getUserMedia({
                            video: this.videoEnabled,
                            audio: this.audioEnabled,
                        });
                        this.$refs.localVideo.srcObject = this.localStream;
                    } catch (e) {
                        console.error('Failed to get local stream:', e);
                        this.videoEnabled = false;
                        this.audioEnabled = false;
                    }
                },

                connectSocket() {
                    this.socket = io('{{ $signalingUrl }}', {
                        path: '/socket.io/',
                        transports: ['websocket', 'polling'],
                    });

                    this.socket.on('connect', () => {
                        console.log('Socket connected');
                        this.socket.emit('join-room', {
                            room_code: '{{ $room->code }}',
                            token: this.token,
                            display_name: this.displayName,
                        });
                    });

                    this.socket.on('room-joined', (data) => {
                        this.connected = true;
                        console.log('Joined room with participants:', data.participants);

                        // Update timer state
                        if (data.timer_state) {
                            this.updateTimer(data.timer_state);
                        }

                        this.presenter = data.presenter;

                        // Connect to existing participants
                        data.participants.forEach(p => {
                            if (p.socket_id !== this.socket.id) {
                                this.createPeer(p.socket_id, p.display_name, true);
                            }
                        });
                    });

                    this.socket.on('user-joined', (data) => {
                        console.log('User joined:', data);
                        // Don't create peer here - wait for their offer
                        // Just track them in peers list
                        if (!this.peers[data.socket_id]) {
                            this.peers[data.socket_id] = { displayName: data.display_name, handRaised: false };
                        }
                        this.addSystemMessage(`${data.display_name} joined the room`);
                    });

                    this.socket.on('user-left', (data) => {
                        console.log('User left:', data.socket_id);
                        const peer = this.peers[data.socket_id];
                        if (peer) {
                            this.addSystemMessage(`${peer.displayName} left the room`);
                        }
                        this.removePeer(data.socket_id);
                    });

                    this.socket.on('offer', (data) => {
                        this.handleOffer(data.from, data.offer);
                    });

                    this.socket.on('answer', (data) => {
                        this.handleAnswer(data.from, data.answer);
                    });

                    this.socket.on('ice-candidate', (data) => {
                        this.handleIceCandidate(data.from, data.candidate);
                    });

                    this.socket.on('timer-update', (data) => {
                        this.updateTimer(data);
                    });

                    this.socket.on('presenter-changed', (data) => {
                        this.presenter = data.presenter_id;
                    });

                    this.socket.on('hand-raised', (data) => {
                        if (this.peers[data.socket_id]) {
                            this.peers[data.socket_id].handRaised = data.raised;
                        }
                    });

                    this.socket.on('chat-message', (data) => {
                        this.messages.push(data);
                        this.$nextTick(() => {
                            this.$refs.chatMessages.scrollTop = this.$refs.chatMessages.scrollHeight;
                        });
                    });

                    this.socket.on('error', (data) => {
                        console.error('Socket error:', data);
                        alert(data.message);
                    });
                },

                createPeer(peerId, displayName, initiator) {
                    // Don't create duplicate peer connections
                    if (this.peerConnections[peerId]) {
                        console.log('Peer connection already exists:', peerId);
                        return this.peerConnections[peerId];
                    }

                    console.log('Creating peer:', peerId, 'initiator:', initiator, 'hasStream:', !!this.localStream);

                    this.peers[peerId] = { displayName: displayName || 'Unknown', handRaised: false };

                    const config = {
                        initiator,
                        trickle: true,
                        config: {
                            iceServers: [
                                { urls: 'stun:stun.l.google.com:19302' },
                                { urls: 'stun:stun1.l.google.com:19302' },
                            ]
                        }
                    };

                    // Only add stream if we have one
                    if (this.localStream) {
                        config.stream = this.localStream;
                    }

                    const peer = new SimplePeer(config);

                    peer.on('signal', (signal) => {
                        if (peer.destroyed) return;
                        if (signal.type === 'offer') {
                            this.socket.emit('offer', { to: peerId, offer: signal });
                        } else if (signal.type === 'answer') {
                            this.socket.emit('answer', { to: peerId, answer: signal });
                        } else if (signal.candidate) {
                            this.socket.emit('ice-candidate', { to: peerId, candidate: signal });
                        }
                    });

                    peer.on('stream', (stream) => {
                        console.log('Got stream from:', peerId);
                        this.$nextTick(() => {
                            const video = document.getElementById('video-' + peerId);
                            if (video) {
                                video.srcObject = stream;
                            }
                        });
                    });

                    peer.on('connect', () => {
                        console.log('Peer connected:', peerId);
                    });

                    peer.on('error', (err) => {
                        console.error('Peer error:', peerId, err);
                    });

                    peer.on('close', () => {
                        console.log('Peer closed:', peerId);
                        // Clean up the peer connection reference
                        delete this.peerConnections[peerId];
                    });

                    this.peerConnections[peerId] = peer;
                    return peer;
                },

                handleOffer(from, offer) {
                    console.log('Received offer from:', from);
                    if (!this.peerConnections[from]) {
                        this.createPeer(from, this.peers[from]?.displayName || 'Unknown', false);
                    }
                    const peer = this.peerConnections[from];
                    if (peer && !peer.destroyed) {
                        peer.signal(offer);
                    }
                },

                handleAnswer(from, answer) {
                    console.log('Received answer from:', from);
                    const peer = this.peerConnections[from];
                    if (peer && !peer.destroyed) {
                        peer.signal(answer);
                    }
                },

                handleIceCandidate(from, candidate) {
                    const peer = this.peerConnections[from];
                    if (peer && !peer.destroyed) {
                        peer.signal(candidate);
                    }
                },

                removePeer(peerId) {
                    if (this.peerConnections[peerId]) {
                        this.peerConnections[peerId].destroy();
                        delete this.peerConnections[peerId];
                    }
                    delete this.peers[peerId];
                },

                toggleAudio() {
                    this.audioEnabled = !this.audioEnabled;
                    if (this.localStream) {
                        this.localStream.getAudioTracks().forEach(t => t.enabled = this.audioEnabled);
                    }
                },

                toggleVideo() {
                    this.videoEnabled = !this.videoEnabled;
                    if (this.localStream) {
                        this.localStream.getVideoTracks().forEach(t => t.enabled = this.videoEnabled);
                    }
                },

                async toggleScreenShare() {
                    if (this.isPresenting) {
                        // Stop screen share
                        await this.startLocalStream();
                        this.replaceTrackForPeers(this.localStream.getVideoTracks()[0]);
                        this.socket.emit('stop-presenting');
                        this.isPresenting = false;
                    } else {
                        try {
                            const screenStream = await navigator.mediaDevices.getDisplayMedia({ video: true });
                            const screenTrack = screenStream.getVideoTracks()[0];

                            screenTrack.onended = () => {
                                this.toggleScreenShare();
                            };

                            this.replaceTrackForPeers(screenTrack);
                            this.$refs.localVideo.srcObject = screenStream;
                            this.socket.emit('start-presenting');
                            this.isPresenting = true;
                        } catch (e) {
                            console.error('Screen share failed:', e);
                        }
                    }
                },

                replaceTrackForPeers(newTrack) {
                    Object.values(this.peerConnections).forEach(peer => {
                        const sender = peer._pc?.getSenders()?.find(s => s.track?.kind === 'video');
                        if (sender) {
                            sender.replaceTrack(newTrack);
                        }
                    });
                },

                toggleHand() {
                    this.handRaised = !this.handRaised;
                    this.socket.emit('raise-hand', { raised: this.handRaised });
                },

                startTimer(type) {
                    this.socket.emit('start-timer', { type });
                },

                toggleTimer() {
                    if (this.timer.status === 'running') {
                        this.socket.emit('pause-timer');
                    } else {
                        this.socket.emit('resume-timer');
                    }
                },

                updateTimer(data) {
                    if (data.status) this.timer.status = data.status;
                    if (data.type) this.timer.type = data.type;
                    if (data.remaining !== undefined) this.timer.remaining = parseInt(data.remaining);

                    if (data.status === 'running' && data.started_at) {
                        this.startTimerCountdown(parseInt(data.started_at));
                    }
                },

                startTimerCountdown(startedAt) {
                    if (this._timerInterval) clearInterval(this._timerInterval);

                    this._timerInterval = setInterval(() => {
                        if (this.timer.status === 'running') {
                            const elapsed = Math.floor((Date.now() - startedAt) / 1000);
                            this.timer.remaining = Math.max(0, this.timer.remaining - 1);

                            if (this.timer.remaining <= 0) {
                                clearInterval(this._timerInterval);
                                // Play notification sound
                                this.playNotification();
                            }
                        }
                    }, 1000);
                },

                playNotification() {
                    // Simple beep using Web Audio API
                    const ctx = new (window.AudioContext || window.webkitAudioContext)();
                    const osc = ctx.createOscillator();
                    osc.frequency.value = 440;
                    osc.connect(ctx.destination);
                    osc.start();
                    setTimeout(() => osc.stop(), 200);
                },

                formatTime(seconds) {
                    const m = Math.floor(seconds / 60);
                    const s = seconds % 60;
                    return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
                },

                sendMessage() {
                    if (!this.newMessage.trim()) return;

                    const msg = {
                        message: this.newMessage,
                        sender_name: this.displayName,
                        type: 'text',
                        timestamp: Date.now(),
                    };

                    this.messages.push(msg);
                    this.socket.emit('chat-message', msg);

                    // Also persist to API
                    fetch('/api/rooms/{{ $room->code }}/messages', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                        },
                        body: JSON.stringify({
                            message: this.newMessage,
                            sender_name: this.displayName,
                            sender_session_id: this.socket.id,
                            type: 'text',
                        }),
                    });

                    this.newMessage = '';
                    this.$nextTick(() => {
                        this.$refs.chatMessages.scrollTop = this.$refs.chatMessages.scrollHeight;
                    });
                },

                addSystemMessage(text) {
                    this.messages.push({
                        type: 'system',
                        message: text,
                        timestamp: Date.now(),
                    });
                },

                leaveRoom() {
                    if (confirm('Are you sure you want to leave?')) {
                        // Clean up
                        Object.values(this.peerConnections).forEach(p => p.destroy());
                        if (this.localStream) {
                            this.localStream.getTracks().forEach(t => t.stop());
                        }
                        if (this.socket) {
                            this.socket.disconnect();
                        }
                        sessionStorage.removeItem('room_{{ $room->code }}_token');
                        window.location.href = '/';
                    }
                },
            };
        };
    </script>
</x-layouts.app>
