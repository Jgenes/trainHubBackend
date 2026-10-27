<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProviderProfileController extends Controller
{
    public function index()
    {
        $provider = $this->currentProvider();

        if (! $provider) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        return response()->json($provider);
    }

    public function update(Request $request)
    {
        $provider = $this->currentProvider();

        if (! $provider) {
            return response()->json(['message' => 'Profile not found'], 404);
        }

        $validated = $request->validate([
            'legal_name'       => ['required', 'string', 'max:255'],
            'brand_name'       => ['nullable', 'string', 'max:255'],
            'tin'              => ['nullable', 'string', 'max:20'],
            'website'          => ['nullable', 'string', 'max:255'],
            'region'           => ['required', 'string', 'max:255'],
            'district'         => ['nullable', 'string', 'max:255'],
            'physical_address' => ['nullable', 'string'],
            'contact_name'     => ['required', 'string', 'max:255'],
            'contact_phone'    => ['required', 'string', 'max:50'],
            'contact_email'    => ['required', 'email', 'max:255'],
        ]);

        $provider->update($validated);

        return response()->json(['message' => 'Profile updated successfully.', 'data' => $provider]);
    }

    private function currentProvider(): ?Provider
    {
        return Provider::where('created_by', Auth::id())
            ->orWhere('user_id', Auth::id())
            ->first();
    }
}
