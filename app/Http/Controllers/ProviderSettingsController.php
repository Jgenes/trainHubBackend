<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;

class ProviderSettingsController extends Controller
{
    public function updatePassword(Request $request)
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = Auth::user();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return response()->json(['message' => 'Password updated successfully!']);
    }

    public function updateEmail(Request $request)
    {
        $user = Auth::user();
        
        $request->validate([
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        $user->update(['email' => $request->email]);

        return response()->json(['message' => 'Email updated successfully!']);
    }
}