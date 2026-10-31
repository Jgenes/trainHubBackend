<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Services\ProviderKycDocumentStorage;
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

        return response()->json($this->profileData($provider));
    }

    public function update(Request $request, ProviderKycDocumentStorage $documents)
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
            'brela_doc'        => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'tin_doc'          => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $provider->update(array_merge($validated, $documents->store($provider, $request)));

        return response()->json([
            'message' => 'Profile updated successfully.',
            'data' => $this->profileData($provider),
        ]);
    }

    private function profileData(Provider $provider): array
    {
        return array_merge($provider->toArray(), [
            'kyc_documents' => [
                'brela' => $provider->brela_doc ? route('provider.kyc.documents.download', 'brela') : null,
                'tin' => $provider->tin_doc ? route('provider.kyc.documents.download', 'tin') : null,
            ],
        ]);
    }

    private function currentProvider(): ?Provider
    {
        return Provider::where('created_by', Auth::id())
            ->orWhere('user_id', Auth::id())
            ->first();
    }
}
