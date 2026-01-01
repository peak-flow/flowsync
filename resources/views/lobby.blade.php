<x-layouts.app title="Join {{ $room->name ?? $room->code }} - FlowSync">
    <div class="min-h-full flex flex-col items-center justify-center p-4" x-data="lobbyApp()">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold">{{ $room->name ?? 'FlowSync Room' }}</h1>
            <p class="text-gray-400">Room Code: <span class="font-mono">{{ $room->code }}</span></p>
        </div>

        <div class="w-full max-w-2xl grid md:grid-cols-2 gap-6">
            <!-- Video Preview -->
            <div class="bg-gray-800 rounded-lg p-4">
                <h2 class="text-lg font-semibold mb-3">Camera Preview</h2>
                <div class="relative aspect-video bg-gray-900 rounded-lg overflow-hidden mb-4">
                    <video x-ref="preview" autoplay muted playsinline class="w-full h-full object-cover"></video>
                    <div x-show="!videoEnabled" class="absolute inset-0 flex items-center justify-center bg-gray-900">
                        <svg class="w-16 h-16 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                    </div>
                </div>

                <!-- Device Controls -->
                <div class="flex gap-2 justify-center">
                    <button @click="toggleVideo" :class="videoEnabled ? 'bg-gray-700' : 'bg-red-600'"
                        class="p-3 rounded-full hover:bg-opacity-80 transition">
                        <svg x-show="videoEnabled" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/>
                        </svg>
                        <svg x-show="!videoEnabled" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                    </button>
                    <button @click="toggleAudio" :class="audioEnabled ? 'bg-gray-700' : 'bg-red-600'"
                        class="p-3 rounded-full hover:bg-opacity-80 transition">
                        <svg x-show="audioEnabled" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11a7 7 0 01-7 7m0 0a7 7 0 01-7-7m7 7v4m0 0H8m4 0h4m-4-8a3 3 0 01-3-3V5a3 3 0 116 0v6a3 3 0 01-3 3z"/>
                        </svg>
                        <svg x-show="!audioEnabled" class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5.586 15H4a1 1 0 01-1-1v-4a1 1 0 011-1h1.586l4.707-4.707C10.923 3.663 12 4.109 12 5v14c0 .891-1.077 1.337-1.707.707L5.586 15z"/>
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2"/>
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Join Form -->
            <div class="bg-gray-800 rounded-lg p-4">
                <h2 class="text-lg font-semibold mb-3">Join Settings</h2>
                <form @submit.prevent="joinRoom" class="space-y-4">
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Your Display Name</label>
                        <input type="text" x-model="displayName" required
                            class="w-full bg-gray-700 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter your name">
                    </div>

                    @if($room->hasPassword())
                    <div>
                        <label class="block text-sm text-gray-400 mb-1">Room Password</label>
                        <input type="password" x-model="password" required
                            class="w-full bg-gray-700 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Enter room password">
                    </div>
                    @endif

                    <!-- Device Selection -->
                    <div x-show="devices.video.length > 1">
                        <label class="block text-sm text-gray-400 mb-1">Camera</label>
                        <select x-model="selectedVideo" @change="updateStream"
                            class="w-full bg-gray-700 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <template x-for="device in devices.video" :key="device.deviceId">
                                <option :value="device.deviceId" x-text="device.label || 'Camera'"></option>
                            </template>
                        </select>
                    </div>

                    <div x-show="devices.audio.length > 1">
                        <label class="block text-sm text-gray-400 mb-1">Microphone</label>
                        <select x-model="selectedAudio" @change="updateStream"
                            class="w-full bg-gray-700 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <template x-for="device in devices.audio" :key="device.deviceId">
                                <option :value="device.deviceId" x-text="device.label || 'Microphone'"></option>
                            </template>
                        </select>
                    </div>

                    <button type="submit" :disabled="joining || !displayName"
                        class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-600 rounded py-3 font-medium transition">
                        <span x-show="!joining">Join Room</span>
                        <span x-show="joining">Joining...</span>
                    </button>
                </form>

                <!-- Error message -->
                <div x-show="error" x-cloak class="mt-4 bg-red-900/50 text-red-200 px-4 py-2 rounded text-sm">
                    <span x-text="error"></span>
                </div>
            </div>
        </div>

        <a href="/" class="mt-6 text-gray-400 hover:text-white transition">
            &larr; Back to Home
        </a>
    </div>

    <script>
        function lobbyApp() {
            return {
                displayName: localStorage.getItem('flowsync_name') || '',
                password: '',
                videoEnabled: true,
                audioEnabled: true,
                joining: false,
                error: null,
                stream: null,
                devices: { video: [], audio: [] },
                selectedVideo: null,
                selectedAudio: null,

                async init() {
                    await this.getDevices();
                    await this.startPreview();
                },

                async getDevices() {
                    try {
                        // Check if mediaDevices is available (requires HTTPS or localhost)
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            this.error = 'Camera/microphone requires HTTPS. Use https:// or localhost.';
                            this.videoEnabled = false;
                            this.audioEnabled = false;
                            return;
                        }

                        // Need to get permission first
                        await navigator.mediaDevices.getUserMedia({ video: true, audio: true });
                        const devices = await navigator.mediaDevices.enumerateDevices();
                        this.devices.video = devices.filter(d => d.kind === 'videoinput');
                        this.devices.audio = devices.filter(d => d.kind === 'audioinput');

                        if (this.devices.video.length) this.selectedVideo = this.devices.video[0].deviceId;
                        if (this.devices.audio.length) this.selectedAudio = this.devices.audio[0].deviceId;
                    } catch (e) {
                        console.error('Failed to get devices:', e);
                        this.error = 'Camera/microphone access denied. Please allow access to continue.';
                    }
                },

                async startPreview() {
                    try {
                        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                            return;
                        }

                        const constraints = {
                            video: this.videoEnabled ? { deviceId: this.selectedVideo } : false,
                            audio: this.audioEnabled ? { deviceId: this.selectedAudio } : false,
                        };

                        if (this.stream) {
                            this.stream.getTracks().forEach(t => t.stop());
                        }

                        this.stream = await navigator.mediaDevices.getUserMedia(constraints);
                        this.$refs.preview.srcObject = this.stream;
                    } catch (e) {
                        console.error('Failed to start preview:', e);
                    }
                },

                async toggleVideo() {
                    this.videoEnabled = !this.videoEnabled;
                    if (this.stream) {
                        const videoTrack = this.stream.getVideoTracks()[0];
                        if (videoTrack) {
                            videoTrack.enabled = this.videoEnabled;
                        } else if (this.videoEnabled) {
                            await this.updateStream();
                        }
                    }
                },

                async toggleAudio() {
                    this.audioEnabled = !this.audioEnabled;
                    if (this.stream) {
                        const audioTrack = this.stream.getAudioTracks()[0];
                        if (audioTrack) {
                            audioTrack.enabled = this.audioEnabled;
                        }
                    }
                },

                async updateStream() {
                    await this.startPreview();
                },

                async joinRoom() {
                    this.joining = true;
                    this.error = null;

                    try {
                        // Check if we already have a token (creator)
                        let token = sessionStorage.getItem(`room_{{ $room->code }}_token`);

                        if (!token) {
                            // Need to join via API
                            const res = await fetch('/api/rooms/{{ $room->code }}/join', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'Accept': 'application/json',
                                },
                                body: JSON.stringify({
                                    display_name: this.displayName,
                                    password: this.password,
                                }),
                            });

                            const data = await res.json();

                            if (!res.ok) {
                                throw new Error(data.error || 'Failed to join room');
                            }

                            token = data.signaling_token;
                            sessionStorage.setItem(`room_{{ $room->code }}_token`, token);
                        }

                        // Save preferences
                        localStorage.setItem('flowsync_name', this.displayName);
                        sessionStorage.setItem(`room_{{ $room->code }}_name`, this.displayName);
                        sessionStorage.setItem(`room_{{ $room->code }}_video`, this.videoEnabled);
                        sessionStorage.setItem(`room_{{ $room->code }}_audio`, this.audioEnabled);

                        // Redirect to room
                        window.location.href = '/room/{{ $room->code }}';
                    } catch (e) {
                        this.error = e.message;
                    } finally {
                        this.joining = false;
                    }
                }
            };
        }
    </script>
</x-layouts.app>
