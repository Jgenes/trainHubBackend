<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Provider;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Mail\ActivateAccountMail;
use App\Mail\LoginOtpMail;

class AuthController extends Controller
{
    // =====================
    // USER REGISTER
    // =====================
    public function userRegister(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phone' => 'required|string|unique:users,phone',
        'password' => 'required|min:8|confirmed'
    ]);

    $token = Str::random(64);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'phone' => $request->phone,
        'password' => Hash::make($request->password),
        'role' => 'user',
        'is_verified' => false,
        'activation_token' => $token,
        'activation_expires_at' => Carbon::now()->addHours(24),
    ]);

    $this->sendActivationEmail($user, $token);

    return response()->json([
        'message' => "User account created. Activation link sent to email."
    ], 201);
}

    // =====================
    // TENANT REGISTER
    // =====================
     public function tenantRegister(Request $request)
{
    $request->validate([
        'name' => 'required|string|max:255',
        'email' => 'required|email|unique:users,email',
        'phone' => 'required|string|unique:users,phone',
        'password' => 'required|min:8|confirmed'
    ]);

    $token = Str::random(64);

    $user = User::create([
        'name' => $request->name,
        'email' => $request->email,
        'phone' => $request->phone,
        'password' => Hash::make($request->password),
        'role' => 'tenant',
        'is_verified' => false,
        'activation_token' => $token,
        'activation_expires_at' => Carbon::now()->addHours(24),
    ]);

    $this->sendActivationEmail($user, $token);

    return response()->json([
        'message' => "User account created. Activation link sent to email."
    ], 201);
}

    // =====================
    // SEND ACTIVATION EMAIL (Helper)
    // =====================
    // Ndani ya AuthController.php
private function sendActivationEmail(User $user, string $token)
{
    // Hii itatengeneza URL sahihi kulingana na route name
    $activationLink = route('activate.account', ['token' => $token]);

    try {
        Mail::to($user->email)->queue(new ActivateAccountMail($activationLink));
    } catch (\Exception $e) {
        \Log::error("Mail Error: " . $e->getMessage());
    }
}

    // =====================
    // ACTIVATE ACCOUNT
    // =====================
 public function activateAccount(Request $request)
{
    // Prefer token-based activation (link contains ?token=...), fallback to ?email=...
    $token = $request->query('token');
    $email = $request->query('email');

    if (!$token && !$email) {
        return response()->json(['message' => 'Activation token or email not provided in link.'], 400);
    }

    // Find user by token first, otherwise by email
    if ($token) {
        $user = \App\Models\User::where('activation_token', $token)->first();
    } else {
        $user = \App\Models\User::where('email', $email)->first();
    }

    if (!$user) {
        return response()->json(['message' => 'Invalid or expired activation link.'], 404);
    }

    // If token existed, verify expiry
    if ($token && $user->activation_expires_at && now()->gt($user->activation_expires_at)) {
        return response()->json(['message' => 'Activation link has expired.'], 400);
    }

    // If already verified, redirect to frontend login with flag
    if ($user->email_verified_at !== null) {
        $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
        return redirect($frontend . '/login?activated=true');
    }

    // Mark verified and clear token fields
    try {
        $user->email_verified_at = now();
        $user->is_verified = true;
        $user->activation_token = null;
        $user->activation_expires_at = null;
        $user->save();
    } catch (\Exception $e) {
        Log::error('Account activation save error: ' . $e->getMessage());
    }

    $frontend = rtrim(env('FRONTEND_URL', 'http://localhost:5173'), '/');
    return redirect($frontend . '/login?activated=true');
}

    // =====================
    // LOGIN → SEND OTP
    // =====================
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) return response()->json(['message' => 'Email not found'], 404);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Wrong password'], 401);
        }

        if (!$user->is_verified) {
            return response()->json(['message' => 'Please verify your account first'], 403);
        }

        // Generate OTP
        $otp = rand(100000, 999999);
        $user->update([
            'login_otp' => $otp,
            'login_otp_expires_at' => now()->addMinutes(5)
        ]);

        try {
            Mail::to($user->email)->send(new LoginOtpMail($otp, 'Your Login OTP', 'login'));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Failed to send OTP email. Please try again later.'], 500);
        }

        return response()->json([
            'message' => 'OTP sent to your email',
            'email' => $user->email
        ]);
    }


    //resend OTP
    public function resendOtp(Request $request)
{
    $request->validate([
        'email' => 'required|email'
    ]);

    $user = User::where('email', $request->email)->first();

    if (!$user) {
        return response()->json(['message' => 'User not found'], 404);
    }

    // Generate new OTP na expiry
    $otp = rand(100000, 999999);
    $user->update([
        'login_otp' => $otp,
        'login_otp_expires_at' => now()->addMinutes(5)
    ]);

    // Send OTP email
    try {
        Mail::to($user->email)->queue(new LoginOtpMail($otp, 'Your Login OTP', 'login'));
    } catch (\Exception $e) {
        return response()->json(['message' => 'Failed to send OTP email'], 500);
    }

    return response()->json(['message' => 'OTP sent again to your email']);
}



    public function verifyLoginOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || (string)$user->login_otp !== (string)$request->otp) {
            return response()->json(['message' => 'OTP is incorrect'], 400);
        }

        if ($user->login_otp_expires_at && now()->gt($user->login_otp_expires_at)) {
            return response()->json(['message' => 'OTP has expired'], 400);
        }

        $user->update([
            'login_otp' => null,
            'login_otp_expires_at' => null
        ]);

        $token = $user->createToken('training-hub')->plainTextToken;
        $redirectPath = $this->getRedirectPath($user);

        return response()->json([
            'token' => $token,
            'user' => $user,
            'redirect' => $redirectPath
        ]);
    }

    // =====================
    // REDIRECT LOGIC
    // =====================
    private function getRedirectPath($user)
    {
        return match ($user->role) {
            'tenant' => $this->handleTenantRedirect($user),
            'user' => '/',
            'admin' => '/admin/dashboard',
            default => '/login',
        };
    }

 private function handleTenantRedirect($user)
{
    // Tafuta kama huyu user ana data kwenye table ya providers
    $provider = Provider::where('created_by', $user->id)->first();

    if (!$provider) {
        // Ikiwa hana data kabisa, mpeleke onboarding
        return '/tenant/onboarding';
    }

    // Convert status kuwa uppercase na ondoa nafasi (trim) ili kuzuia makosa ya spelling
    $status = strtoupper(trim($provider->status ?? 'PENDING')); 

    // Logic ya kuelekeza kulingana na status
    if ($status === 'APPROVED') {
        return '/provider/dashboard';
    } elseif ($status === 'PENDING') {
        return '/provider/verification';
    } elseif ($status === 'REJECTED') {
        return '/tenant/blocked';
    }

    // Ikiwa ana data (provider ipo) lakini status haijulikani, 
    // mpeleke dashboard badala ya kumrudisha onboarding
    return '/provider/dashboard';
}

}
