<?php

namespace App\Http\Controllers;

use App\Models\Provider;
use App\Support\Cors;
use Illuminate\Http\Request;

class PublicController extends Controller
{
    /**
     * Canary used to diagnose browser CORS failures.
     *
     *     curl -i -H "Origin: https://traininghubtz.co.tz" \
     *       https://<api-host>/api/cors-check
     *
     * A JSON body means the request reached Laravel; the Access-Control-*
     * response headers (added by HandleCors) then show whether the browser
     * will accept it. An HTML body means something in front of Laravel
     * answered instead — typically an offline ngrok tunnel (ERR_NGROK_3200)
     * or a host pointing at the wrong port. Browsers report both cases as
     * "No 'Access-Control-Allow-Origin' header is present", which is why
     * they are easy to confuse.
     */
    public function corsCheck(Request $request)
    {
        $origin = (string) $request->headers->get('Origin', '');

        return response()->json([
            'ok'                   => true,
            'origin'               => $origin === '' ? null : $origin,
            'origin_allowed'       => $origin === '' ? null : Cors::originAllowed($origin),
            'allowed_origins'      => config('cors.allowed_origins'),
            'allowed_patterns'     => config('cors.allowed_origins_patterns', []),
            'supports_credentials' => (bool) config('cors.supports_credentials'),
            'request_host'         => $request->getHost(),
            'request_scheme'       => $request->getScheme(),
            'server_time'          => now()->toIso8601String(),
        ]);
    }

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
