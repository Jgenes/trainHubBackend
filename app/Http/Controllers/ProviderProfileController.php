<?php

namespace App\Http\Controllers;
 // Hakikisha folder structure ni app/Http/Controllers/Provider
use App\Http\Controllers\Controller;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProviderProfileController extends Controller
{
    // Kupata taarifa za profile
    public function index()
    {
        // Auth::id() itafanya kazi kama umepita kwenye Sanctum middleware
        $provider = Provider::where('user_id', Auth::id())->first();

        if (!$provider) {
            return response()->json(['message' => 'Provider profile not found'], 404);
        }

        return response()->json($provider);
    }

    // Kupasasisha taarifa (Update)
    public function update(Request $request)
    {
        $provider = Provider::where('user_id', Auth::id())->firstOrFail();

        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'brand_name' => 'nullable|string|max:255',
            'tin' => 'nullable|string|max:20',
            'website' => 'nullable|url',
            'region' => 'required|string',
            'district' => 'nullable|string',
            'physical_address' => 'nullable|string',
            'contact_name' => 'required|string',
            'contact_phone' => 'required|string',
            'contact_email' => 'required|email',
        ]);

        $provider->update($validated);

        return response()->json([
            'message' => 'Profile updated successfully!',
            'data' => $provider
        ]);
    }
}