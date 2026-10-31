<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Services\ProviderKycDocumentStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProviderOnboardingController extends Controller
{
    public function store(Request $request, ProviderKycDocumentStorage $documents)
    {
        $data = $request->validate([
            'legal_name'      => ['required', 'string', 'min:3', 'max:255'],
            'brand_name'      => ['nullable', 'string', 'max:255'],
            'provider_type'   => ['required', 'string', 'max:255'],
            'registration_ref'=> ['nullable', 'string', 'max:255'],
            'tin'             => ['nullable', 'string', 'max:255'],
            'website'         => ['nullable', 'string', 'max:255'],
            'country'         => ['nullable', 'string', 'max:100'],
            'region'          => ['required', 'string', 'max:255'],
            'district'        => ['nullable', 'string', 'max:255'],
            'physical_address'=> ['nullable', 'string'],
            'google_maps_link'=> ['nullable', 'string', 'max:255'],
            'contact_name'    => ['required', 'string', 'max:255'],
            'contact_role'    => ['nullable', 'string', 'max:255'],
            'contact_phone'   => ['required', 'string', 'max:50'],
            'contact_email'   => ['nullable', 'email', 'max:255'],
            'brela_doc'       => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
            'tin_doc'         => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);

        $user = $request->user();

        $provider = DB::transaction(function () use ($data, $request, $user, $documents) {
            $slug = Str::slug($data['brand_name'] ?? $data['legal_name']);
            if (Provider::where('provider_slug', $slug)->where('created_by', '!=', $user->id)->exists()) {
                $slug .= '-'.Str::lower(Str::random(5));
            }

            $attributes = [
                'legal_name'       => trim($data['legal_name']),
                'brand_name'       => $data['brand_name'] ?? null,
                'provider_type'    => $data['provider_type'],
                'registration_ref' => $data['registration_ref'] ?? null,
                'tin'              => $data['tin'] ?? null,
                'website'          => $data['website'] ?? null,
                'country'          => $data['country'] ?? 'Tanzania',
                'region'           => $data['region'],
                'district'         => $data['district'] ?? null,
                'physical_address' => $data['physical_address'] ?? null,
                'google_maps_link' => $data['google_maps_link'] ?? null,
                'contact_name'     => $data['contact_name'],
                'contact_role'     => $data['contact_role'] ?? null,
                'contact_phone'    => $data['contact_phone'],
                'contact_email'    => ($data['contact_email'] ?? null) ?: $user->email,
                'status'           => 'PENDING',
                'provider_slug'    => $slug,
                'created_by'       => $user->id,
                'user_id'          => $user->id,
            ];

            $provider = Provider::where('created_by', $user->id)->first();

            if ($provider) {
                unset($attributes['provider_slug'], $attributes['status']);
                $provider->update($attributes);
            } else {
                $attributes['id'] = Str::uuid()->toString();
                $provider = Provider::create($attributes);
            }

            $provider->update($documents->store($provider, $request));

            $user->update([
                'provider_id' => $provider->id,
                'role'        => 'tenant',
            ]);

            return $provider;
        });

        return response()->json([
            'message'  => 'Your onboarding has been submitted successfully. Please wait for verification.',
            'provider' => $provider,
            'redirect' => '/provider/verification',
        ], 201);
    }
}
