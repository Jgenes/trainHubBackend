<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Provider;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ProviderGovernanceController extends Controller
{
    public function index(Request $request)
    {
        return Provider::with('user')
            ->when($request->status, fn ($q) => $q->where('status', $request->status))
            ->paginate(20);
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => ['required', 'in:DRAFT,PENDING,APPROVED,SUSPENDED,REJECTED'],
            'reason' => ['nullable', 'string'],
        ]);

        $provider   = Provider::findOrFail($id);
        $oldStatus  = $provider->status;

        $provider->update(['status' => $data['status']]);

        AuditLog::create([
            'admin_id'    => Auth::id(),
            'action'      => 'PROVIDER_STATUS_CHANGE',
            'target_type' => 'Provider',
            'target_id'   => (string) $provider->id,
            'reason'      => $data['reason'] ?? null,
            'old_values'  => ['status' => $oldStatus],
            'new_values'  => ['status' => $data['status']],
        ]);

        return response()->json(['message' => 'Status updated and logged']);
    }
}
