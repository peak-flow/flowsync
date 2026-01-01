<x-layouts.app title="FlowSync - Remote Work Together">
    <div class="min-h-full flex flex-col items-center justify-center p-4" x-data="homeApp()">
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold mb-2">FlowSync</h1>
            <p class="text-gray-400">Remote work collaboration with video, chat & pomodoro</p>
        </div>

        <div class="w-full max-w-md space-y-6">
            <!-- Create Room -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Create a Room</h2>
                <form @submit.prevent="createRoom">
                    <div class="mb-4">
                        <label class="block text-sm text-gray-400 mb-1">Room Name (optional)</label>
                        <input type="text" x-model="newRoom.name"
                            class="w-full bg-gray-700 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="My Work Session">
                    </div>
                    <div class="mb-4">
                        <label class="block text-sm text-gray-400 mb-1">Password (optional)</label>
                        <input type="password" x-model="newRoom.password"
                            class="w-full bg-gray-700 rounded px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-500"
                            placeholder="Leave empty for no password">
                    </div>
                    <button type="submit" :disabled="creating"
                        class="w-full bg-blue-600 hover:bg-blue-700 disabled:bg-blue-800 rounded py-2 font-medium transition">
                        <span x-show="!creating">Create Room</span>
                        <span x-show="creating">Creating...</span>
                    </button>
                </form>
            </div>

            <!-- Join Room -->
            <div class="bg-gray-800 rounded-lg p-6">
                <h2 class="text-xl font-semibold mb-4">Join a Room</h2>
                <form @submit.prevent="joinRoom">
                    <div class="mb-4">
                        <label class="block text-sm text-gray-400 mb-1">Room Code</label>
                        <input type="text" x-model="joinCode"
                            class="w-full bg-gray-700 rounded px-3 py-2 uppercase tracking-widest text-center text-xl focus:outline-none focus:ring-2 focus:ring-green-500"
                            placeholder="ABC123" maxlength="6">
                    </div>
                    <button type="submit" :disabled="!joinCode || joinCode.length !== 6"
                        class="w-full bg-green-600 hover:bg-green-700 disabled:bg-gray-600 rounded py-2 font-medium transition">
                        Join Room
                    </button>
                </form>
            </div>
        </div>

        <!-- Error message -->
        <div x-show="error" x-cloak class="mt-4 bg-red-900/50 text-red-200 px-4 py-2 rounded">
            <span x-text="error"></span>
        </div>
    </div>

    <script>
        function homeApp() {
            return {
                newRoom: { name: '', password: '' },
                joinCode: '',
                creating: false,
                error: null,

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
                },

                joinRoom() {
                    if (this.joinCode && this.joinCode.length === 6) {
                        window.location.href = `/room/${this.joinCode.toUpperCase()}/lobby`;
                    }
                }
            };
        }
    </script>
</x-layouts.app>
