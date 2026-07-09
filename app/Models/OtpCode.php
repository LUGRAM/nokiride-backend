<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'user_id', 'phone_number', 'purpose', 'code', 'expires_at',
    'attempt_count', 'max_attempts', 'verified_at', 'verification_token_hash',
    'consumed_at',
])]
class OtpCode extends Model
{
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'verified_at' => 'datetime',
            'consumed_at' => 'datetime',
        ];
    }
}
