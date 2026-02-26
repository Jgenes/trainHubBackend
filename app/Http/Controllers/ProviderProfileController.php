<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProviderProfileController extends Controller
{
public function index()
{
    // Hakikisha Model ya Provider ina uhusiano na User
    $provider = Provider::where('created_by', Auth::id())->first();

    if (!$provider) {
        return response()->json(['message' => 'Profile not found'], 404);
    }

    // Usiiweke ndani ya 'data' => [] ili React isipate undefined
    return response()->json($provider);
}

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
            'message' => 'Profile imesasishwa!',
            'data' => $provider
        ]);
    }
}