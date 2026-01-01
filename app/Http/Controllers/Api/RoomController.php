<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Room;
use App\Models\RoomMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class RoomController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:4',
        ]);

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

        $token = $this->generateRoomToken($room->code);

        return response()->json([
            'room_code' => $room->code,
            'signaling_url' => config('app.signaling_url', 'ws://localhost:3000'),
            'signaling_token' => $token,
            'has_password' => $room->hasPassword(),
            'ice_servers' => $this->getIceServers(),
        ], 201);
    }

    public function show(string $code): JsonResponse
    {
        $room = Room::where('code', $code)->firstOrFail();

        if ($room->isExpired()) {
            return response()->json(['error' => 'Room has expired'], 410);
        }

        return response()->json([
            'code' => $room->code,
            'name' => $room->name,
            'has_password' => $room->hasPassword(),
            'max_participants' => $room->max_participants,
            'settings' => $room->settings,
            'expires_at' => $room->expires_at,
        ]);
    }

    public function join(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'display_name' => 'required|string|max:100',
            'password' => 'nullable|string',
        ]);

        $room = Room::where('code', $code)->firstOrFail();

        if ($room->isExpired()) {
            return response()->json(['error' => 'Room has expired'], 410);
        }

        if ($room->hasPassword() && !Hash::check($request->password, $room->password_hash)) {
            return response()->json(['error' => 'Invalid password'], 401);
        }

        $token = $this->generateRoomToken($room->code);

        // Extend room expiry on join
        $room->update(['expires_at' => now()->addHours(24)]);

        return response()->json([
            'room_code' => $room->code,
            'signaling_url' => config('app.signaling_url', 'ws://localhost:3000'),
            'signaling_token' => $token,
            'ice_servers' => $this->getIceServers(),
        ]);
    }

    public function destroy(Request $request, string $code): JsonResponse
    {
        $room = Room::where('code', $code)->firstOrFail();

        // Simple IP-based creator check for MVP
        if ($room->creator_ip !== $request->ip()) {
            return response()->json(['error' => 'Only the room creator can end the room'], 403);
        }

        $room->delete();

        return response()->json(['message' => 'Room ended']);
    }

    public function messages(string $code): JsonResponse
    {
        $room = Room::where('code', $code)->firstOrFail();

        $messages = $room->messages()
            ->orderBy('created_at', 'asc')
            ->limit(100)
            ->get();

        return response()->json($messages);
    }

    public function storeMessage(Request $request, string $code): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'sender_name' => 'required|string|max:100',
            'sender_session_id' => 'required|string',
            'type' => 'in:text,emoji',
        ]);

        $room = Room::where('code', $code)->firstOrFail();

        $message = $room->messages()->create([
            'sender_name' => $request->sender_name,
            'sender_session_id' => $request->sender_session_id,
            'message' => $request->message,
            'type' => $request->type ?? 'text',
        ]);

        return response()->json($message, 201);
    }

    private function generateRoomToken(string $roomCode): string
    {
        $token = Str::uuid()->toString();
        Redis::setex("room:{$roomCode}:token:{$token}", 86400, '1');
        return $token;
    }

    private function getIceServers(): array
    {
        return [
            ['urls' => 'stun:stun.l.google.com:19302'],
            ['urls' => 'stun:stun1.l.google.com:19302'],
        ];
    }
}
