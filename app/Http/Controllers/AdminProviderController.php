<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\User;
use App\Mail\ProviderApproved;
use App\Mail\ProviderSuspended;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class AdminProviderController extends Controller
{
    /**
     * List all providers
     */
    public function index()
    {
        try {
            $providers = Provider::with('user')->get();
            return response()->json($providers, 200);
        } catch (\Exception $e) {
            Log::error("Fetch Providers Error: " . $e->getMessage());
            return response()->json(['error' => 'Unable to fetch providers'], 500);
        }
    }

    /**
     * Approve a provider account
     */
    public function approveStatus(Request $request, $id)
    {
        try {
            $provider = Provider::with('user')->findOrFail($id);

            $provider->status = 'APPROVED';
            $provider->save();

            if ($provider->user) {
                $provider->user->update(['is_verified' => true]);

                // Try sending mail but don't break request if it fails
                try {
                    Mail::to($provider->user->email)->send(new ProviderApproved($provider));
                } catch (\Exception $mailEx) {
                    Log::error("ProviderApproved Mail Error for provider {$provider->id}: " . $mailEx->getMessage());
                }
            }

            return response()->json([
                'message' => 'Provider approved successfully.',
                'provider_id' => $provider->id
            ]);
        } catch (\Exception $e) {
            Log::error("Approve Provider Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to approve provider.'], 500);
        }
    }

    /**
     * Suspend a provider account
     */
    public function suspendStatus(Request $request, $id)
    {
        $request->validate([
            'reason' => 'required|string|min:5'
        ]);

        try {
            $provider = Provider::with('user')->findOrFail($id);

            $provider->status = 'SUSPENDED';
            $provider->save();

            if ($provider->user) {
                $provider->user->update(['is_verified' => false]);

                // Try sending suspension email
                try {
                    Mail::to($provider->user->email)->send(new ProviderSuspended($provider, $request->reason));
                } catch (\Exception $mailEx) {
                    Log::error("ProviderSuspended Mail Error for provider {$provider->id}: " . $mailEx->getMessage());
                }
            }

            return response()->json([
                'message' => 'Provider suspended successfully.',
                'provider_id' => $provider->id
            ]);
        } catch (\Exception $e) {
            Log::error("Suspend Provider Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to suspend provider.'], 500);
        }
    }

    /**
 * Reject a provider account
 */
public function rejectStatus(Request $request, $id)
{
    $request->validate([
        'reason' => 'required|string|min:5'
    ]);

    try {
        $provider = Provider::with('user')->findOrFail($id);

        $provider->status = 'REJECTED';
        $provider->save();

        if ($provider->user) {
            // Zuia ashindwe kulogin kabisa
            $provider->user->update(['is_verified' => false]);

            // Unaweza kutumia Mail class ya Suspended au ukatengeneza mpya 'ProviderRejected'
            try {
                Mail::to($provider->user->email)->send(new ProviderSuspended($provider, $request->reason));
            } catch (\Exception $mailEx) {
                Log::error("ProviderRejected Mail Error: " . $mailEx->getMessage());
            }
        }

        return response()->json([
            'message' => 'Provider rejected successfully.',
            'provider_id' => $provider->id
        ]);
    } catch (\Exception $e) {
        Log::error("Reject Provider Error: " . $e->getMessage());
        return response()->json(['error' => 'Failed to reject provider.'], 500);
    }
}
}
