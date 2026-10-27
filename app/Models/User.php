<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'phone', 'role', 'is_verified',
        'activation_token', 'activation_expires_at', 'login_otp',
        'login_otp_expires_at', 'provider_id', 'email_verified_at',
    ];

    protected $hidden = [
        'password', 'remember_token', 'login_otp', 'activation_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at'      => 'datetime',
            'activation_expires_at'  => 'datetime',
            'login_otp_expires_at'   => 'datetime',
            'password'               => 'hashed',
            'is_verified'            => 'boolean',
        ];
    }

    /** Normalise Tanzanian phone numbers to +255XXXXXXXXX before saving. */
    public static function normalizePhone(?string $phone): ?string
    {
        if ($phone === null || trim($phone) === '') {
            return null;
        }

        $digits = preg_replace('/[^0-9]/', '', $phone);

        if (str_starts_with($digits, '0')) {
            $digits = '255' . substr($digits, 1);
        } elseif (!str_starts_with($digits, '255')) {
            $digits = '255' . $digits;
        }

        return '+' . $digits;
    }

    public function setPhoneAttribute($phone): void
    {
        $this->attributes['phone'] = static::normalizePhone($phone);
    }

    /** The provider record linked through users.provider_id. */
    public function provider()
    {
        return $this->hasOne(Provider::class, 'id', 'provider_id');
    }

    /** The provider application this user submitted. */
    public function providerProfile()
    {
        return $this->hasOne(Provider::class, 'created_by');
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
