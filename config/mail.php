<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send all email
    | messages unless another mailer is explicitly specified when sending
    | the message. All additional mailers can be configured within the
    | "mailers" array. Examples of each type of mailer are provided.
    |
    */

    'default' => env('MAIL_MAILER', 'log'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers that can be used
    | when delivering an email. You may specify which one you're using for
    | your mailers below. You may also add additional mailers if needed.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses", "ses-v2",
    |            "postmark", "resend", "log", "array",
    |            "failover", "roundrobin"
    |
    */

    'mailers' => [

        'smtp' => [
            'transport' => 'smtp',
            'scheme' => env('MAIL_SCHEME'),
            'url' => env('MAIL_URL'),
            'host' => env('MAIL_HOST', '127.0.0.1'),
            'port' => env('MAIL_PORT', 2525),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            // Without a timeout a blocked SMTP port hangs a request for the
            // PHP default (60s) before registration/login returns.
            'timeout' => env('MAIL_TIMEOUT') !== null ? (int) env('MAIL_TIMEOUT') : 15,
            'local_domain' => env('MAIL_EHLO_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST)),
        ],

        /*
         * Sends over HTTPS through a transactional email API instead of SMTP.
         * Needed on hosts that block outbound ports 25/465/587 (Render's free
         * plan, for example).
         *
         *   MAIL_MAILER=http
         *   MAIL_HTTP_PROVIDER=resend|brevo|sendgrid
         *   MAIL_HTTP_API_KEY=...
         */
        'http' => [
            'transport' => 'http',
            'provider'  => env('MAIL_HTTP_PROVIDER', 'resend'),
            'api_key'   => env('MAIL_HTTP_API_KEY', env('RESEND_API_KEY')),
            'endpoint'  => env('MAIL_HTTP_ENDPOINT'),
            'timeout'   => (int) env('MAIL_HTTP_TIMEOUT', 15),
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'postmark' => [
            'transport' => 'postmark',
            // 'message_stream_id' => env('POSTMARK_MESSAGE_STREAM_ID'),
            // 'client' => [
            //     'timeout' => 5,
            // ],
        ],

        'resend' => [
            'transport' => 'resend',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => env('MAIL_SENDMAIL_PATH', '/usr/sbin/sendmail -bs -i'),
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],

        'failover' => [
            'transport' => 'failover',
            'mailers' => [
                'smtp',
                'log',
            ],
            'retry_after' => 60,
        ],

        'roundrobin' => [
            'transport' => 'roundrobin',
            'mailers' => [
                'ses',
                'postmark',
            ],
            'retry_after' => 60,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all emails sent by your application to be sent from
    | the same address. Here you may specify a name and address that is
    | used globally for all emails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Mail diagnostics
    |--------------------------------------------------------------------------
    |
    | Backs `php artisan mail:diagnose` and the `GET /api/mail-check` endpoint.
    | The endpoint answers 404 until MAIL_CHECK_TOKEN is set; when it is set,
    | callers must pass it as ?token=… or the X-Mail-Check-Token header.
    |
    */

    'diagnostics' => [
        'token'   => env('MAIL_CHECK_TOKEN'),
        'timeout' => (int) env('MAIL_CHECK_TIMEOUT', 5),
    ],

];
