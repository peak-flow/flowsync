<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Room extends Model
{
    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'expires_at' => 'datetime',
        'max_participants' => 'integer',
    ];

    protected $hidden = [
        'password_hash',
        'creator_ip',
    ];

    public static function generateCode(): string
    {
        do {
            $code = strtoupper(Str::random(6));
        } while (self::where('code', $code)->exists());

        return $code;
    }

    public function messages(): HasMany
    {
        return $this->hasMany(RoomMessage::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(RoomSession::class);
    }

    public function hasPassword(): bool
    {
        return !empty($this->password_hash);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }
}
