<?php
namespace App\Http\Controllers;

use App\Models\Provider;
use App\Models\AuditLog;
use Illuminate\Http\Request;

class ProviderGovernanceController extends Controller
{
    // View zote: Pending, Approved, nk.
    public function index(Request $request) {
        return Provider::with('user')
            ->when($request->status, fn($q) => $q->where('status', $request->status))
            ->paginate(20);
    }

    public function updateStatus(Request $request, $id) {
        $provider = Provider::findOrFail($id);
        $oldStatus = $provider->status;
        
        $provider->update(['status' => $request->status]); // APPROVED, SUSPENDED, REJECTED

        // LOG ACTION
        AuditLog::create([
            'admin_id' => auth()->id(),
            'action' => 'PROVIDER_STATUS_CHANGE',
            'target_type' => 'Provider',
            'target_id' => $provider->id,
            'reason' => $request->reason,
            'old_values' => ['status' => $oldStatus],
            'new_values' => ['status' => $request->status],
        ]);

        return response()->json(['message' => 'Status updated and logged']);
    }
}