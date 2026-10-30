<?php

namespace App\Support;

use App\Mail\MailTestMail;
use Illuminate\Support\Facades\Mail;
use Throwable;

/**
 * Answers the question "why did the OTP / activation email never arrive?"
 *
 * Registration and login only log a one-line error when SMTP fails, which is
 * impossible to act on from a hosting dashboard. This class walks the whole
 * chain — configuration, DNS, TCP, TLS, and finally a real send — and turns
 * each failure into an actionable hint.
 *
 * It is used by `php artisan mail:diagnose` and by the token protected
 * `GET /api/mail-check` endpoint (see MailCheckController).
 */
class MailDiagnostics
{
    /**
     * Values copied from a tutorial or `.env.example` that will never work.
     */
    private const PLACEHOLDER_SECRETS = [
        'your-password',
        'your_password',
        'yourpassword',
        'your password',
        'password',
        'changeme',
        'change-me',
        'secret',
        'example',
        'xxx',
        'xxxx',
        'null',
    ];

    /**
     * Hostnames that only work when the mail server runs on the same machine.
     */
    private const LOCAL_HOSTS = ['127.0.0.1', 'localhost', '::1', '0.0.0.0'];

    /**
     * Run every check and return a machine readable report.
     *
     * @return array{ok: bool, config: array, checks: array, summary: array, sent: array|null}
     */
    public function run(bool $network = true, ?string $sendTo = null): array
    {
        $checks = $this->configChecks();

        if ($network) {
            // DNS and TCP only make sense when the caller allows outbound
            // traffic (the --no-network flag and `network=0` skip them).
            $checks = array_merge($checks, $this->dnsChecks(), $this->connectionChecks());
        }

        $sent = null;

        if ($sendTo !== null && $sendTo !== '') {
            $sent = $this->sendTest($sendTo);

            $checks[] = $this->check(
                'test message',
                $sent['ok'] ? 'ok' : 'fail',
                $sent['ok']
                    ? 'Test email handed to the mailer without error.'
                    : 'Sending the test email failed: '.$sent['error'],
                $sent['ok'] ? null : $sent['hint'],
            );
        }

        $summary = ['ok' => 0, 'warn' => 0, 'fail' => 0, 'skip' => 0];

        foreach ($checks as $check) {
            $summary[$check['status']]++;
        }

        return [
            'ok'      => $summary['fail'] === 0,
            'config'  => $this->snapshot(),
            'checks'  => $checks,
            'summary' => $summary,
            'sent'    => $sent,
        ];
    }

    /**
     * The active mail configuration with the password redacted.
     */
    public function snapshot(): array
    {
        $config = $this->mailerConfig();

        return [
            'mailer'           => $config['mailer'],
            'transport'        => $config['transport'],
            'host'             => $config['host'],
            'port'             => $config['port'],
            'scheme'           => $config['scheme'] === null || $config['scheme'] === ''
                ? '(auto: smtps for port 465, smtp otherwise)'
                : $config['scheme'],
            'username'         => $config['username'],
            'password'         => $this->describeSecret($config['password']),
            'from_address'     => config('mail.from.address'),
            'from_name'        => config('mail.from.name'),
            'queue_connection' => config('queue.default'),
            'app_env'          => config('app.env'),
        ];
    }

    /**
     * Static checks: no network access, so they also work behind a firewall.
     */
    public function configChecks(): array
    {
        $config = $this->mailerConfig();
        $checks = [];

        $mailer = $config['mailer'];

        if (in_array($mailer, ['log', 'array'], true)) {
            $checks[] = $this->check(
                'MAIL_MAILER',
                'fail',
                "MAIL_MAILER={$mailer} never delivers email — ".($mailer === 'log'
                    ? 'messages are only written to storage/logs/laravel.log.'
                    : 'messages are only kept in memory for the current request.'),
                'Set MAIL_MAILER=smtp (or http). On Render the blueprint sets MAIL_MAILER=log, '
                .'and a dashboard environment variable overrides .env — delete it there.',
            );
        } else {
            $checks[] = $this->check('MAIL_MAILER', 'ok', "Using the [{$mailer}] mailer.");
        }

        if ($mailer === 'http') {
            $http     = (array) config('mail.mailers.http', []);
            $key      = (string) ($http['api_key'] ?? '');
            $provider = strtolower((string) ($http['provider'] ?? 'resend'));
            $known    = in_array($provider, ['resend', 'brevo', 'sendgrid'], true)
                || (string) ($http['endpoint'] ?? '') !== '';

            $checks[] = $this->check(
                'MAIL_HTTP_PROVIDER',
                $known ? 'ok' : 'fail',
                $known ? $provider : "Unknown provider [{$provider}].",
                $known ? null : 'Use resend, brevo or sendgrid, or set MAIL_HTTP_ENDPOINT.',
            );

            $checks[] = $this->check(
                'MAIL_HTTP_API_KEY',
                $key === '' ? 'fail' : 'ok',
                $key === '' ? 'MAIL_HTTP_API_KEY is empty; the provider will reject every message.' : $this->describeSecret($key),
                $key === '' ? 'Create an API key in the provider dashboard and paste it into MAIL_HTTP_API_KEY.' : null,
            );

            return $checks;
        }

        if ($mailer !== 'smtp') {
            // Nothing below applies to the log/array mailers.
            return $checks;
        }

        $host    = (string) ($config['host'] ?? '');
        $port    = (int) ($config['port'] ?? 0);
        $scheme  = $this->effectiveScheme($config);
        $user    = (string) ($config['username'] ?? '');
        $pass    = (string) ($config['password'] ?? '');

        if ($host === '') {
            $checks[] = $this->check('MAIL_HOST', 'fail', 'MAIL_HOST is empty.', 'Use the mail server hostname from your hosting panel, e.g. mail.example.com.');
        } elseif (str_contains($host, '://') || str_contains($host, '/') || str_contains($host, '<') || str_contains($host, '>')) {
            $checks[] = $this->check(
                'MAIL_HOST',
                'fail',
                "MAIL_HOST must be a bare hostname but looks like [{$host}].",
                'Use mail.example.com — no https://, no slashes, no angle brackets.',
            );
        } elseif (in_array(strtolower($host), self::LOCAL_HOSTS, true)) {
            $checks[] = $this->check(
                'MAIL_HOST',
                'fail',
                "MAIL_HOST={$host} only works when a mail server runs inside the same container.",
                'Point MAIL_HOST at the real mail server hostname.',
            );
        } else {
            $checks[] = $this->check('MAIL_HOST', 'ok', $host);
        }

        if ($port === 0) {
            $checks[] = $this->check('MAIL_PORT', 'fail', 'MAIL_PORT is empty.', 'Use 465 (implicit TLS) or 587 (STARTTLS).');
        } elseif ($port === 465 && $scheme !== 'smtps') {
            $checks[] = $this->check(
                'MAIL_PORT / MAIL_SCHEME',
                'fail',
                "Port 465 needs implicit TLS but the effective scheme is [{$scheme}].",
                'Set MAIL_SCHEME=smtps (or leave MAIL_SCHEME empty and use port 465, which Laravel treats as smtps).',
            );
        } elseif ($port !== 465 && $scheme === 'smtps') {
            $checks[] = $this->check(
                'MAIL_PORT / MAIL_SCHEME',
                'fail',
                "MAIL_SCHEME=smtps forces implicit TLS, which does not work on port {$port}.",
                'Use MAIL_SCHEME=smtp (STARTTLS) on port 587, or port 465 with smtps.',
            );
        } else {
            $checks[] = $this->check('MAIL_PORT / MAIL_SCHEME', 'ok', "Port {$port} over {$scheme}.");
        }

        if ($user === '') {
            $checks[] = $this->check('MAIL_USERNAME', 'fail', 'MAIL_USERNAME is empty; most SMTP servers reject unauthenticated mail.', 'cPanel/WHM mailboxes use the full address, e.g. info@example.com.');
        } else {
            $checks[] = $this->check('MAIL_USERNAME', 'ok', $user);
        }

        if ($pass === '') {
            $checks[] = $this->check('MAIL_PASSWORD', 'fail', 'MAIL_PASSWORD is empty.', 'Set the mailbox password (the one used to log in to webmail).');
        } elseif ($this->isPlaceholderSecret($pass)) {
            $checks[] = $this->check(
                'MAIL_PASSWORD',
                'fail',
                'MAIL_PASSWORD still holds the example value "'.(strlen($pass) > 40 ? substr($pass, 0, 40).'…' : $pass).'".',
                'Replace it with the real mailbox password.',
            );
        } elseif (str_starts_with($pass, '$$')) {
            $checks[] = $this->check(
                'MAIL_PASSWORD',
                'warn',
                'The password starts with "$$", which .env parsing turns into a single "$".',
                'In a .env file a literal $ must be written as \\$ or wrapped in single quotes.',
            );
        } else {
            $checks[] = $this->check('MAIL_PASSWORD', 'ok', $this->describeSecret($pass));
        }

        $from = (string) config('mail.from.address', '');

        if ($from === '' || str_ends_with($from, '@example.com') || str_ends_with($from, '@example.test')) {
            $checks[] = $this->check(
                'MAIL_FROM_ADDRESS',
                'fail',
                "MAIL_FROM_ADDRESS is still [{$from}], which no mail server will accept.",
                'Set it to a mailbox that exists on the mail server, normally the same address as MAIL_USERNAME.',
            );
        } elseif ($user !== '' && strcasecmp($from, $user) !== 0) {
            $checks[] = $this->check(
                'MAIL_FROM_ADDRESS',
                'warn',
                "MAIL_FROM_ADDRESS [{$from}] differs from MAIL_USERNAME [{$user}].",
                'Many shared hosts reject a From address the authenticated mailbox does not own (error 550 "Sender address rejected").',
            );
        } else {
            $checks[] = $this->check('MAIL_FROM_ADDRESS', 'ok', $from);
        }

        return $checks;
    }

    /**
     * DNS checks: does the mail host exist, and can the domain receive mail?
     */
    public function dnsChecks(): array
    {
        $config = $this->mailerConfig();

        if ($config['mailer'] !== 'smtp') {
            return [];
        }

        $checks  = [];
        $host    = (string) ($config['host'] ?? '');
        $from    = (string) config('mail.from.address', '');
        $domain  = str_contains($from, '@') ? substr($from, strpos($from, '@') + 1) : '';

        if ($host !== '' && ! filter_var($host, FILTER_VALIDATE_IP)) {
            $addresses = $this->lookupAddresses($host);

            if ($addresses === []) {
                $checks[] = $this->check(
                    "DNS A/AAAA {$host}",
                    'fail',
                    "{$host} does not resolve — the SMTP connection cannot even be opened "
                    .'(PHP error: "php_network_getaddresses: getaddrinfo failed").',
                    'Add an A record for '.$host.' in your DNS zone (it usually points at the same server as the web host), '
                    .'or use the mail hostname your provider documents.',
                );
            } else {
                $checks[] = $this->check("DNS A/AAAA {$host}", 'ok', implode(', ', $addresses));
            }
        }

        if ($domain !== '') {
            $mx = $this->lookupRecords($domain, DNS_MX);

            if ($mx === []) {
                $checks[] = $this->check(
                    "DNS MX {$domain}",
                    'warn',
                    "{$domain} has no MX record, so mail addressed to @{$domain} cannot be delivered — "
                    .'bounces, and spam filters penalise the domain.',
                    'Add the MX record your mail host lists. Without it neither info@'.$domain
                    .' nor replies to your notifications arrive, regardless of the API configuration.',
                );
            } else {
                $checks[] = $this->check("DNS MX {$domain}", 'ok', implode(', ', $mx));
            }

            $txt = implode(' ', $this->lookupRecords($domain, DNS_TXT));

            if (stripos($txt, 'v=spf1') === false) {
                $checks[] = $this->check(
                    "SPF {$domain}",
                    'warn',
                    "No SPF record on {$domain}.",
                    'Add a TXT record such as "v=spf1 a mx include:<provider> ~all". Without SPF/DKIM, OTP mail lands in spam.',
                );
            } else {
                $checks[] = $this->check("SPF {$domain}", 'ok', 'SPF record present.');
            }
        }

        return $checks;
    }

    /**
     * TCP/TLS checks against the configured SMTP endpoint.
     */
    public function connectionChecks(): array
    {
        $config = $this->mailerConfig();

        if ($config['mailer'] !== 'smtp') {
            return [];
        }

        $host   = (string) ($config['host'] ?? '');
        $port   = (int) ($config['port'] ?? 0);
        $scheme = $this->effectiveScheme($config);

        if ($host === '' || $port === 0) {
            return [$this->check('SMTP connection', 'skip', 'Host or port is missing, so no connection was attempted.')];
        }

        $probe = $this->probe($host, $port, $scheme);

        $checks = [];

        if (! $probe['connected']) {
            $checks[] = $this->check(
                "TCP {$host}:{$port}",
                'fail',
                'Could not connect: '.$probe['error'].'.',
                $this->explain($probe['error']),
            );

            return $checks;
        }

        $checks[] = $this->check("TCP {$host}:{$port}", 'ok', 'Connection established.');

        if ($probe['banner'] !== null) {
            $checks[] = $this->check('SMTP banner', 'ok', trim(substr($probe['banner'], 0, 120)));
        }

        if ($probe['tls'] !== null) {
            $checks[] = $probe['tls']
                ? $this->check('TLS handshake', 'ok', 'Encrypted channel established.')
                : $this->check(
                    'TLS handshake',
                    'fail',
                    'TLS negotiation failed: '.($probe['tls_error'] ?? 'unknown error').'.',
                    $this->explain($probe['tls_error']),
                );
        }

        if ($probe['auth_mechanisms'] !== []) {
            $checks[] = $this->check('SMTP AUTH', 'ok', 'Server advertises: '.implode(', ', $probe['auth_mechanisms']).'.');
        }

        return $checks;
    }

    /**
     * Send a real message and capture the exact SMTP error if it fails.
     *
     * @return array{ok: bool, to: string, error: string|null, hint: string|null}
     */
    public function sendTest(string $to): array
    {
        try {
            Mail::to($to)->send(new MailTestMail());

            return ['ok' => true, 'to' => $to, 'error' => null, 'hint' => null];
        } catch (Throwable $e) {
            $error = $e->getMessage();

            return [
                'ok'    => false,
                'to'    => $to,
                'error' => $error,
                'hint'  => $this->explain($error),
            ];
        }
    }

    /**
     * Translate a transport error into something a human can act on.
     */
    public function explain(?string $error): ?string
    {
        if ($error === null || trim($error) === '') {
            return null;
        }

        $e = strtolower($error);

        $hints = [
            ['getaddrinfo', 'MAIL_HOST does not resolve in DNS. Add an A record for the mail hostname, or use the hostname your mail provider documents.'],
            ['name or service not known', 'MAIL_HOST does not resolve in DNS. Add an A record for the mail hostname (a "mail." subdomain usually points at the web server).'],
            ['php_network_getaddresses', 'The SMTP hostname could not be resolved. Check MAIL_HOST for typos and that the DNS record exists.'],
            ['no such host', 'MAIL_HOST does not resolve in DNS. Check the spelling and add the missing A record.'],
            ['connection refused', 'Nothing is listening on that host/port. Check MAIL_PORT (465 implicit TLS, 587 STARTTLS) and that the mail service is running.'],
            ['timed out', 'The connection timed out — usually a firewall. Render\'s free plan blocks outbound ports 25, 465 and 587: use port 2525 if your provider supports it, or the HTTP mailer (MAIL_MAILER=http).'],
            ['network is unreachable', 'Outbound SMTP is blocked by the host. On Render free instances ports 25/465/587 are blocked — switch to MAIL_MAILER=http or a provider port such as 2525.'],
            ['no route to host', 'Outbound SMTP is blocked on this network/host. Use the HTTP mailer (MAIL_MAILER=http) instead.'],
            ['certificate', 'The TLS certificate does not match MAIL_HOST, or the server uses a self-signed one. Use the exact hostname from the certificate.'],
            ['ssl', 'TLS negotiation failed. Port 465 needs MAIL_SCHEME=smtps; port 587 needs MAIL_SCHEME=smtp (STARTTLS). A certificate-name mismatch means MAIL_HOST differs from the certificate.'],
            ['tls', 'TLS negotiation failed. Check MAIL_PORT/MAIL_SCHEME (465+smtps or 587+smtp) and that MAIL_HOST matches the certificate.'],
            ['535', 'The mail server rejected the credentials. MAIL_USERNAME is normally the full mailbox address and MAIL_PASSWORD the webmail password.'],
            ['5.7.8', 'Authentication failed — check MAIL_USERNAME/MAIL_PASSWORD.'],
            ['authentication', 'Authentication failed. On cPanel/WHM use the full mailbox address as MAIL_USERNAME.'],
            ['sender address rejected', 'The mail server refuses to send as MAIL_FROM_ADDRESS. Use the authenticated mailbox as the From address.'],
            ['550', 'The server rejected the sender or recipient. Usually MAIL_FROM_ADDRESS must be a mailbox owned by MAIL_USERNAME.'],
            ['554', 'Relaying denied — the server does not accept this sender. Align MAIL_FROM_ADDRESS with MAIL_USERNAME.'],
            ['421', 'The server is throttling or refusing service. Try again later or lower the sending rate.'],
            ['too many', 'The mail server is rate limiting this mailbox.'],
        ];

        foreach ($hints as [$needle, $hint]) {
            if (str_contains($e, $needle)) {
                return $hint;
            }
        }

        return null;
    }

    /* ------------------------------------------------------------------ */
    /* Internals                                                          */
    /* ------------------------------------------------------------------ */

    /**
     * @return array{mailer: string, transport: string|null, host: string|null, port: int|null, scheme: string|null, username: string|null, password: string|null}
     */
    private function mailerConfig(): array
    {
        $mailer = (string) config('mail.default', 'smtp');
        $config = (array) config('mail.mailers.'.$mailer, []);

        return [
            'mailer'    => $mailer,
            'transport' => isset($config['transport']) ? (string) $config['transport'] : null,
            'host'      => isset($config['host']) ? (string) $config['host'] : null,
            'port'      => isset($config['port']) ? (int) $config['port'] : null,
            'scheme'    => isset($config['scheme']) ? (string) $config['scheme'] : null,
            'username'  => isset($config['username']) ? (string) $config['username'] : null,
            'password'  => isset($config['password']) ? (string) $config['password'] : null,
        ];
    }

    /**
     * Mirror Laravel's own rule: an empty scheme means smtps on 465, smtp
     * everywhere else (MailManager::createSmtpTransport).
     */
    private function effectiveScheme(array $config): string
    {
        $scheme = strtolower(trim((string) ($config['scheme'] ?? '')));

        if ($scheme !== '') {
            // Accept "smtps://" style values and the tls/ssl aliases.
            $scheme = rtrim($scheme, ':/');

            return match ($scheme) {
                'ssl', 'tls', 'smtps' => 'smtps',
                default               => 'smtp',
            };
        }

        return ((int) ($config['port'] ?? 0)) === 465 ? 'smtps' : 'smtp';
    }

    private function isPlaceholderSecret(string $value): bool
    {
        return in_array(strtolower(trim($value)), self::PLACEHOLDER_SECRETS, true);
    }

    private function describeSecret(?string $value): string
    {
        if ($value === null || $value === '') {
            return 'missing';
        }

        if ($this->isPlaceholderSecret($value)) {
            return 'placeholder value';
        }

        return 'set ('.strlen($value).' characters)';
    }

    /**
     * @return string[]
     */
    private function lookupAddresses(string $host): array
    {
        $addresses = [];

        if (function_exists('dns_get_record')) {
            foreach ([DNS_A, DNS_AAAA] as $type) {
                $records = @dns_get_record($host, $type);

                if (is_array($records)) {
                    foreach ($records as $record) {
                        $address = $record['ip'] ?? $record['ipv6'] ?? null;

                        if (is_string($address) && $address !== '') {
                            $addresses[] = $address;
                        }
                    }
                }
            }
        }

        if ($addresses === []) {
            $ip = gethostbyname($host);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                $addresses[] = $ip;
            }
        }

        return array_values(array_unique($addresses));
    }

    /**
     * @return string[]
     */
    private function lookupRecords(string $domain, int $type): array
    {
        if (! function_exists('dns_get_record')) {
            return [];
        }

        $records = @dns_get_record($domain, $type);

        if (! is_array($records)) {
            return [];
        }

        $values = [];

        foreach ($records as $record) {
            if ($type === DNS_MX) {
                $target = $record['target'] ?? null;

                if (is_string($target) && $target !== '') {
                    $values[] = $target;
                }
            } else {
                $txt = $record['txt'] ?? null;

                if (is_string($txt)) {
                    $values[] = $txt;
                }
            }
        }

        return $values;
    }

    /**
     * Open a socket and, when possible, an encrypted channel.
     *
     * @return array{connected: bool, error: string|null, banner: string|null, tls: bool|null, tls_error: string|null, auth_mechanisms: string[]}
     */
    private function probe(string $host, int $port, string $scheme): array
    {
        $result = [
            'connected'       => false,
            'error'           => null,
            'banner'          => null,
            'tls'             => null,
            'tls_error'       => null,
            'auth_mechanisms' => [],
        ];

        $timeout = (float) (config('mail.diagnostics.timeout') ?: 5);
        $context = stream_context_create([
            'ssl' => [
                'verify_peer'      => true,
                'verify_peer_name' => true,
                'SNI_enabled'      => true,
                'peer_name'        => $host,
            ],
        ]);

        $address = ($scheme === 'smtps' ? 'ssl://' : 'tcp://').$host.':'.$port;

        $errno  = 0;
        $errstr = '';
        $stream = @stream_socket_client($address, $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT, $context);

        if ($stream === false) {
            $result['error'] = $errstr !== '' ? $errstr : 'connection failed (errno '.$errno.')';

            return $result;
        }

        stream_set_timeout($stream, (int) $timeout);

        $result['connected'] = true;
        $result['tls']       = $scheme === 'smtps' ? true : null;

        $banner = fgets($stream, 515);

        if (is_string($banner)) {
            $result['banner'] = $banner;
        }

        if ($scheme === 'smtp') {
            // Plain connection: EHLO, then STARTTLS if advertised.
            fwrite($stream, "EHLO ".(gethostname() ?: 'localhost')."\r\n");
            $capabilities = $this->readSmtpResponse($stream);

            if (stripos($capabilities, 'STARTTLS') !== false) {
                fwrite($stream, "STARTTLS\r\n");
                $reply = $this->readSmtpResponse($stream);

                if (str_starts_with(trim($reply), '220')) {
                    $ok = @stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                    $result['tls']       = $ok === true;
                    $result['tls_error'] = $ok === true ? null : 'STARTTLS negotiation failed';

                    if ($ok === true) {
                        fwrite($stream, "EHLO ".(gethostname() ?: 'localhost')."\r\n");
                        $capabilities = $this->readSmtpResponse($stream);
                    }
                } else {
                    $result['tls']       = false;
                    $result['tls_error'] = trim($reply);
                }
            } else {
                $result['tls']       = false;
                $result['tls_error'] = 'Server does not advertise STARTTLS.';
            }

            if (preg_match('/AUTH[ =]([^\r\n]+)/i', $capabilities, $matches) === 1) {
                $result['auth_mechanisms'] = array_values(array_filter(preg_split('/\s+/', trim($matches[1])) ?: []));
            }
        }

        @fclose($stream);

        return $result;
    }

    /**
     * Read a (possibly multi-line) SMTP reply as one string.
     */
    private function readSmtpResponse($stream): string
    {
        $buffer = '';

        while (($line = fgets($stream, 515)) !== false) {
            $buffer .= $line;

            // "250-DATA" continues, "250 DATA" ends the reply.
            if (preg_match('/^\d{3} /', $line) === 1) {
                break;
            }
        }

        return $buffer;
    }

    private function check(string $name, string $status, string $message, ?string $hint = null): array
    {
        return [
            'name'    => $name,
            'status'  => $status,
            'message' => $message,
            'hint'    => $hint,
        ];
    }
}
