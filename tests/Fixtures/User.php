<?php

namespace AdesinFr\Mail2FA\Tests\Fixtures;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    protected $table = 'users';

    protected $fillable = [
        'name',
        'email',
        'password',
        'mfa_code',
        'mfa_expires_at',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'mfa_code',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mfa_expires_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
}
