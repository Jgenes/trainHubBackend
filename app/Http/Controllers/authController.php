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
    private function sendActivationEmail(User $user, string $token)
    {
        $activationLink = env('FRONTEND_URL') . '/activate-account?token=' . $token;

        try {
            Mail::to($user->email)->queue(new ActivateAccountMail($activationLink));
        } catch (\Exception $e) {
            // Optional: log error
        }
    }

    // =====================
    // ACTIVATE ACCOUNT
    // =====================
 public function activateAccount(Request $request)
{
    // 1. Chukuwa email kutoka kwenye URL (?email=...)
    $email = $request->query('email');

    if (!$email) {
        return response()->json(['message' => 'Email haijapatikana kwenye link.'], 400);
    }

    // 2. Tafuta user
    $user = \App\Models\User::where('email', $email)->first();

    if (!$user) {
        return response()->json(['message' => 'Mtumiaji huyu hayupo.'], 404);
    }

    // 3. Kama tayari asha-activate, usipoteze muda
    if ($user->email_verified_at !== null) {
        return response()->json(['message' => 'Account ilishakuwa active.'], 200);
    }

    // 4. Update Database
    $user->email_verified_at = now();
    $user->status = 'active'; // Hakikisha column hii ipo, kama huna ifute hii line
    $user->save();

    // 5. MUHIMU: Mpeleke mtumiaji kwenye Login Page ya React
    // Badilisha 5173 iwe port ya React yako (mfano 3000)
    return redirect('http://localhost:5173/login?activated=true');
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

        if (!$user) return response()->json(['message' => 'Email haijapatikana'], 404);

        if (!Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Nenosiri si sahihi'], 401);
        }

        if (!$user->is_verified) {
            return response()->json(['message' => 'Tafadhali amilisha akaunti yako kwanza'], 403);
        }

        // Generate OTP
        $otp = rand(100000, 999999);
        $user->update([
            'login_otp' => $otp,
            'login_otp_expires_at' => now()->addMinutes(5)
        ]);

        try {
            Mail::to($user->email)->send(new LoginOtpMail($otp));
        } catch (\Exception $e) {
            return response()->json(['message' => 'Imeshindwa kutuma barua pepe, jaribu tena.'], 500);
        }

        return response()->json([
            'message' => 'OTP imetumwa kwenye barua pepe yako',
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
        Mail::to($user->email)->queue(new LoginOtpMail($otp));
    } catch (\Exception $e) {
        return response()->json(['message' => 'Failed to send OTP email'], 500);
    }

    return response()->json(['message' => 'OTP imetumwa tena']);
}



    public function verifyLoginOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'otp' => 'required|digits:6'
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user || (string)$user->login_otp !== (string)$request->otp) {
            return response()->json(['message' => 'OTP si sahihi'], 400);
        }

        if ($user->login_otp_expires_at && now()->gt($user->login_otp_expires_at)) {
            return response()->json(['message' => 'OTP imekwisha muda wake'], 400);
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
    $provider = Provider::where('user_id', $user->id)->first();

    if (!$provider) {
        // Ikiwa tenant hana profile bado, mpeleke onboarding
        return '/tenant/onboarding';
    }

    // Hakikisha status inasomeka vizuri (kama ni null au tofauti)
    $status = strtoupper($provider->status ?? 'PENDING'); 

    return match ($status) {
        'PENDING'  => '/provider/verification',
        'APPROVED' => '/provider/dashboard',
        'REJECTED' => '/tenant/blocked',
        default    => '/tenant/onboarding', // Badala ya login, mrudishe kuanza upya
    };
}

}
