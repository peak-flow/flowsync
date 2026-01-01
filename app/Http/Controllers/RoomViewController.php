<?php

namespace App\Http\Controllers;

use App\Models\Room;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RoomViewController extends Controller
{
    public function home(): View
    {
        return view('home');
    }

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

    public function room(string $code): View
    {
        $room = Room::where('code', $code)->firstOrFail();

        if ($room->isExpired()) {
            abort(410, 'Room has expired');
        }

        return view('room', [
            'room' => $room,
            'signalingUrl' => env('SIGNALING_URL', 'http://localhost:3001'),
        ]);
    }
}
