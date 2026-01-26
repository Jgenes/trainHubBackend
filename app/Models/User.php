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
        'name','email','password','phone','role','is_verified',
        'activation_token','activation_expires_at','login_otp','login_otp_expires_at',
        'provider_id'
    ];

    protected $hidden = ['password','remember_token','login_otp'];

    protected $casts = ['email_verified_at'=>'datetime','password'=>'hashed'];

    public function provider()
    {
        return $this->hasOne(Provider::class, 'id', 'provider_id');
    }

    public function setPhoneAttribute($phone)
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        if (substr($phone,0,1) == '0') $phone = '255'.substr($phone,1);
        $this->attributes['phone'] = '+' . $phone;
    }
    public function user()
{
    return $this->belongsTo(User::class, 'user_id');
}

}
