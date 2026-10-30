<?php

namespace App\Http\Controllers;

use App\Support\MailDiagnostics;
use Illuminate\Http\Request;

/**
 * HTTP twin of `php artisan mail:diagnose`, for hosts where opening a shell is
 * awkward. It stays invisible (404) until MAIL_CHECK_TOKEN is set, and every
 * call must present that token.
 *
 *     curl -s "https://api.example.com/api/mail-check?token=$TOKEN" | jq
 *     curl -s "https://api.example.com/api/mail-check?token=$TOKEN&send=you@example.com" | jq
 */
class MailCheckController extends Controller
{
    public function __invoke(Request $request, MailDiagnostics $diagnostics)
    {
        $expected = (string) config('mail.diagnostics.token');

        if ($expected === '') {
            // Not configured: behave as if the route does not exist.
            abort(404);
        }

        $provided = (string) ($request->query('token') ?? $request->header('X-Mail-Check-Token', ''));

        if ($provided === '' || ! hash_equals($expected, $provided)) {
            abort(403, 'Invalid or missing mail-check token.');
        }

        $send = trim((string) $request->query('send', ''));

        if ($send !== '' && ! filter_var($send, FILTER_VALIDATE_EMAIL)) {
            return response()->json([
                'ok'      => false,
                'message' => 'The [send] parameter must be a valid email address.',
            ], 422);
        }

        $report = $diagnostics->run($request->boolean('network', true), $send !== '' ? $send : null);

        return response()->json($report, $report['ok'] ? 200 : 503);
    }
}
