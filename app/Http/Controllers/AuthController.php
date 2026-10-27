<?php

namespace App\Http\Controllers;

use App\Mail\ActivateAccountMail;
use App\Mail\LoginOtpMail;
use App\Models\Provider;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    public function userRegister(Request $request)
    {
        return $this->register($request, 'user');
    }

    public function tenantRegister(Request $request)
    {
        return $this->register($request, 'tenant');
    }

    private function register(Request $request, string $role)
    {
        // Normalise the phone before the unique rule so "+255..." and "0..." forms
        // cannot create duplicate accounts.
        $request->merge(['phone' => User::normalizePhone($request->input('phone'))]);

        $request->validate([
            'name'     => ['required', 'string', 'max:255'],
            'email'    => ['required', 'email', 'unique:users,email'],
            'phone'    => ['required', 'string', 'unique:users,phone'],
            'password' => ['required', 'min:8', 'confirmed'],
        ]);

        $token = Str::random(64);

        $user = User::create([
            'name'                   => $request->name,
            'email'                  => Str::lower(trim($request->email)),
            'phone'                  => $request->phone,
            'password'               => Hash::make($request->password),
            'role'                   => $role,
            'is_verified'            => false,
            'activation_token'       => $token,
            'activation_expires_at'  => Carbon::now()->addHours(24),
        ]);

        $this->sendActivationEmail($user, $token);

        return response()->json([
            'message' => 'Account created. An activation link has been sent to your email.',
        ], 201);
    }

    private function sendActivationEmail(User $user, string $token): void
    {
        $link = route('activate.account', ['token' => $token]);

        try {
            Mail::to($user->email)->send(new ActivateAccountMail($link));
        } catch (\Throwable $e) {
            // A broken SMTP server must not prevent account creation. The link is
            // logged so an administrator can still activate the account.
            Log::error('Activation email failed: '.$e->getMessage(), ['link' => $link]);
        }
    }

    public function activateAccount(Request $request)
    {
        $token = $request->query('token');
        $email = $request->query('email');

        if (! $token && ! $email) {
            return response()->json(['message' => 'Activation token or email is missing.'], 400);
        }

        $user = $token
            ? User::where('activation_token', $token)->first()
            : User::where('email', $email)->first();

        if (! $user) {
            return response()->json(['message' => 'Invalid or expired activation link.'], 404);
        }

        if ($token && $user->activation_expires_at && now()->gt($user->activation_expires_at)) {
            return response()->json(['message' => 'Activation link has expired.'], 400);
        }

        if ($user->email_verified_at === null) {
            try {
                $user->forceFill([
                    'email_verified_at'      => now(),
                    'is_verified'            => true,
                    'activation_token'       => null,
                    'activation_expires_at'  => null,
                ])->save();
            } catch (\Throwable $e) {
                Log::error('Account activation failed: '.$e->getMessage());

                return response()->json(['message' => 'Could not activate the account.'], 500);
            }
        }

        return redirect()->away(config('app.frontend_url').'/login?activated=true');
    }

    public function login(Request $request)
    {
        $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required'],
        ]);

        $user = User::where('email', Str::lower(trim($request->email)))->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Invalid email or password.'], 401);
        }

        if (! $user->is_verified) {
            return response()->json(['message' => 'Please verify your account first.'], 403);
        }

        $otp = random_int(100000, 999999);

        $user->forceFill([
            'login_otp'            => (string) $otp,
            'login_otp_expires_at' => now()->addMinutes(5),
        ])->save();

        try {
            Mail::to($user->email)->send(new LoginOtpMail($otp, 'Your Login OTP', 'login'));
        } catch (\Throwable $e) {
            Log::error('Login OTP email failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to send the verification code. Please try again later.'], 500);
        }

        return response()->json([
            'message' => 'OTP sent to your email',
            'email'   => $user->email,
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate(['email' => ['required', 'email']]);

        $user = User::where('email', Str::lower(trim($request->email)))->first();

        if (! $user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        $otp = random_int(100000, 999999);

        $user->forceFill([
            'login_otp'            => (string) $otp,
            'login_otp_expires_at' => now()->addMinutes(5),
        ])->save();

        try {
            Mail::to($user->email)->send(new LoginOtpMail($otp, 'Your Login OTP', 'login'));
        } catch (\Throwable $e) {
            Log::error('Resend OTP email failed: '.$e->getMessage());

            return response()->json(['message' => 'Failed to send the verification code.'], 500);
        }

        return response()->json(['message' => 'A new verification code has been sent.']);
    }

    public function verifyLoginOtp(Request $request)
    {
        $request->validate([
            'email' => ['required', 'email'],
            'otp'   => ['required', 'digits:6'],
        ]);

        $user = User::where('email', Str::lower(trim($request->email)))->first();

        if (! $user || ! hash_equals((string) $user->login_otp, (string) $request->otp)) {
            return response()->json(['message' => 'The verification code is incorrect.'], 400);
        }

        if (! $user->login_otp_expires_at || now()->gt($user->login_otp_expires_at)) {
            return response()->json(['message' => 'The verification code has expired.'], 400);
        }

        $user->forceFill([
            'login_otp'            => null,
            'login_otp_expires_at' => null,
        ])->save();

        $token = $user->createToken('training-hub')->plainTextToken;

        return response()->json([
            'token'    => $token,
            'user'     => $user,
            'redirect' => $this->getRedirectPath($user),
        ]);
    }

    private function getRedirectPath(User $user): string
    {
        return match ($user->role) {
            'admin'  => '/admin/dashboard',
            'tenant' => $this->handleTenantRedirect($user),
            default  => '/',
        };
    }

    private function handleTenantRedirect(User $user): string
    {
        $provider = Provider::where('created_by', $user->id)
            ->orWhere('user_id', $user->id)
            ->first();

        if (! $provider) {
            return '/tenant/onboarding';
        }

        return match (strtoupper(trim((string) $provider->status))) {
            'APPROVED' => '/provider/dashboard',
            'PENDING', 'DRAFT' => '/provider/verification',
            'REJECTED' => '/tenant/blocked',
            default    => '/provider/dashboard',
        };
    }
}
