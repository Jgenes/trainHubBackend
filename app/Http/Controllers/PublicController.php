<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Provider;
use App\Support\Cors;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

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

    /**
     * Diagnose an empty catalogue without exposing course records.
     *
     *     curl https://api-host/api/catalogue-health
     *
     * A missing endpoint / an old `code` means the deployment has not picked
     * up this build. `total_courses: 0` means the database genuinely has no
     * courses; a positive total with `public_courses: 0` means every row has a
     * withdrawn status.
     */
    public function catalogueHealth()
    {
        if (! Schema::hasTable('courses') || ! Schema::hasColumn('courses', 'status')) {
            return response()->json([
                'ok'    => false,
                'code'  => 'public-scope-v2',
                'error' => 'The courses table/status column is missing. Run php artisan migrate --force.',
            ], 503);
        }

        $statusCounts = DB::table('courses')
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->get()
            ->mapWithKeys(fn ($row) => [
                $row->status ?? '(null)' => (int) $row->total,
            ])
            ->all();

        $total  = array_sum($statusCounts);
        $public = Course::published()->count();

        $hint = match (true) {
            $total === 0  => 'No course rows exist. Create a course in the provider workspace or seed real catalogue data.',
            $public === 0 => 'Courses exist, but all of them have a hidden/withdrawn status. Normalise or publish their statuses.',
            default       => null,
        };

        return response()->json([
            'ok'                 => $public > 0,
            'code'               => 'public-scope-v2',
            'request_host'       => request()->getHost(),
            'database_driver'    => DB::connection()->getDriverName(),
            'total_courses'      => $total,
            'public_courses'     => $public,
            'hidden_courses'     => max(0, $total - $public),
            'status_counts'      => $statusCounts,
            'approved_providers' => Provider::where('status', 'APPROVED')->count(),
            'hint'               => $hint,
        ]);
    }

    /** Verified providers shown on the public home page. */
    public function topProviders()
    {
        // `user_id` is the local key of Provider::courses(). It must be selected
        // for withCount() to work, but it is hidden again before the response.
        $providers = Provider::query()
            ->where('status', 'APPROVED')
            ->withCount(['courses' => fn ($q) => $q->published()])
            ->orderByDesc('courses_count')
            ->limit(12)
            ->get([
                'id', 'legal_name', 'brand_name', 'provider_slug',
                'region', 'country', 'status', 'user_id',
            ])
            ->makeHidden(['user_id']);

        return response()->json($providers);
    }
}
