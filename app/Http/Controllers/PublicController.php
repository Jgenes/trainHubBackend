<?php

namespace App\Http\Controllers;

use App\Models\Provider;

class PublicController extends Controller
{
    /** Verified providers shown on the public home page. */
    public function topProviders()
    {
        $providers = Provider::query()
            ->where('status', 'APPROVED')
            ->withCount(['courses' => fn ($q) => $q->where('status', 'Published')])
            ->orderByDesc('courses_count')
            ->limit(12)
            ->get(['id', 'legal_name', 'brand_name', 'provider_slug', 'region', 'country', 'status']);

        return response()->json($providers);
    }
}
