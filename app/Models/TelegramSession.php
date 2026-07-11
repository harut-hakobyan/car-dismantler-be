<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TelegramSession extends Model
{
    protected $fillable = [
        'telegram_chat_id',
        'telegram_user_id',
        'flow',
        'step',
        'payload',
        'last_message_id',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
