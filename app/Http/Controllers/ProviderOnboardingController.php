<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Models\Provider;

class ProviderOnboardingController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'legal_name'=>'required|min:3',
            'provider_type'=>'required',
            'region'=>'required',
            'contact_name'=>'required',
            'contact_phone'=>'required',
            'contact_email'=>'nullable|email'
        ]);

        $user = $request->user();

        DB::transaction(function() use($request,$user){
            $provider = Provider::create([
                'id'=>Str::uuid(),
                'legal_name'=>trim($request->legal_name),
                'brand_name'=>$request->brand_name,
                'provider_type'=>$request->provider_type,
                'registration_ref'=>$request->registration_ref,
                'tin'=>$request->tin,
                'website'=>$request->website,
                'country'=>$request->country ?? 'Tanzania',
                'region'=>$request->region,
                'district'=>$request->district,
                'physical_address'=>$request->physical_address,
                'google_maps_link'=>$request->google_maps_link,
                'contact_name'=>$request->contact_name,
                'contact_role'=>$request->contact_role,
                'contact_phone'=>$request->contact_phone,
                'contact_email'=>$request->contact_email,
                'status'=>'PENDING',
                'provider_slug'=>Str::slug($request->brand_name ?? $request->legal_name),
                'created_by'=>$user->id
            ]);

            $user->update([
                'provider_id'=>$provider->id,
                'role'=>'PROVIDER_ADMIN'
            ]);
        });

        return response()->json([
            'message'=>'Your onboarding has been submitted successfully. Please wait for verification.',
            'redirect'=>'/provider/wait-verification'
        ],201);
    }
}
