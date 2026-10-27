<?php

namespace App\Http\Controllers;

use App\Mail\ResetPasswordMail;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    private const TABLE = 'password_reset_tokens';
    private const TTL_MINUTES = 60;

    public function sendResetLink(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $email = Str::lower(trim($request->email));
        $user  = User::where('email', $email)->first();

        // Always respond the same way so the endpoint cannot be used to
        // enumerate registered email addresses.
        if (! $user) {
            return response()->json(['message' => 'If that address exists, a reset link has been sent.']);
        }

        $token = Str::random(64);

        DB::table(self::TABLE)->updateOrInsert(
            ['email' => $email],
            ['token' => hash('sha256', $token), 'created_at' => now()]
        );

        $link = config('app.frontend_url').'/reset-password?'.http_build_query([
            'token' => $token,
            'email' => $email,
        ]);

        try {
            Mail::to($user->email)->send(new ResetPasswordMail($user, $link, $token));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Password reset email failed: '.$e->getMessage());

            throw ValidationException::withMessages([
                'email' => ['We could not send the reset email. Please try again shortly.'],
            ]);
        }

        return response()->json(['message' => 'If that address exists, a reset link has been sent.']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token'    => ['required', 'string'],
            'email'    => ['required', 'email'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $email  = Str::lower(trim($data['email']));
        $record = DB::table(self::TABLE)->where('email', $email)->first();

        $valid = $record
            && hash_equals($record->token, hash('sha256', $data['token']))
            && \Carbon\Carbon::parse($record->created_at)->gt(now()->subMinutes(self::TTL_MINUTES));

        if (! $valid) {
            throw ValidationException::withMessages([
                'email'    => ['This password reset link is invalid or has expired.'],
                'password' => ['Please request a new reset link.'],
            ]);
        }

        $user = User::where('email', $email)->first();

        if (! $user) {
            throw ValidationException::withMessages([
                'email' => ['We could not find an account with that email address.'],
            ]);
        }

        $user->forceFill([
            'password'       => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        DB::table(self::TABLE)->where('email', $email)->delete();

        return response()->json(['message' => 'Password updated successfully.']);
    }
}
