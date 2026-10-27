# TrainHub TZ — Backend API

Laravel 12 REST API for the TrainHub TZ training marketplace. It powers the
React/Vite frontend (learner catalogue, provider workspace, admin console,
PesaPal payments, reports and PDF/Excel exports).

---

## 1. Requirements

| Tool | Version |
| --- | --- |
| PHP | **8.2 or 8.3** (8.4 also works; 8.5 is *not* supported by the locked `phpoffice/phpspreadsheet 1.30`) |
| Extensions | `pdo_mysql` / `pdo_sqlite`, `mbstring`, `zip`, `gd`, `bcmath`, `dom`, `curl`, `fileinfo` |
| Composer | 2.x |
| Database | MySQL 8 (production) or SQLite (local / tests) |
| Node | 18+ — only needed if you rebuild the Vite assets (`npm install && npm run build`) |

## 2. Local setup

```bash
git clone <this-repo> && cd trainHubBackend

composer install
cp .env.example .env
php artisan key:generate

# SQLite needs no server:
touch database/database.sqlite
php artisan migrate

php artisan serve            # http://127.0.0.1:8000
curl http://127.0.0.1:8000/up   # -> 200 OK
```

For MySQL, set `DB_CONNECTION=mysql` plus `DB_HOST`, `DB_PORT`, `DB_DATABASE`,
`DB_USERNAME`, `DB_PASSWORD` in `.env` and run `php artisan migrate`.

`.env` is **git-ignored and no longer committed**. Copy `.env.example` and fill
in your own values. If you previously deployed using the committed `.env`,
create the same keys as environment variables in your hosting dashboard.

> **Security:** an earlier commit contained a `.env` with live mail, PesaPal and
> AWS credentials. Those secrets are still in git history. Rotate all of them.

## 3. Tests

```bash
php artisan test
# or: php vendor/bin/phpunit
```

`phpunit.xml` sets an in-memory SQLite database, array cache/mail, sync queue and
a fixed test `APP_KEY`, so the suite needs no external services. The feature
suite covers registration/activation/OTP login, password reset, admin
authorization, provider onboarding, course + cohort CRUD, course extras,
learning progress, reviews, reports, exports, PDF downloads and the PesaPal
initiate/callback flow (HTTP faked).

## 4. Environment variables

| Key | Purpose | Default |
| --- | --- | --- |
| `APP_KEY` | Encryption key — **required** | — |
| `APP_URL` | Public API base URL (used in generated links) | `http://localhost:8000` |
| `FRONTEND_URL` | React app URL; activation, reset and payment redirects use it | `http://localhost:5173` |
| `CORS_ALLOWED_ORIGINS` | Comma-separated browser origins allowed to call the API | `*` (any origin) |
| `SANCTUM_STATEFUL_DOMAINS` | First-party SPA domains | `localhost:5173,...` |
| `CORS_SUPPORTS_CREDENTIALS` | Send `Access-Control-Allow-Credentials` | `true` |
| `CORS_ALLOWED_ORIGIN_PATTERNS` | Optional regex for dynamic preview hosts | — |
| `CORS_MAX_AGE` | Seconds a browser may cache a preflight (OPTIONS) response | `600` |
| `DB_*` | Database connection | SQLite |
| `QUEUE_CONNECTION` | `sync` (no worker needed) or `database` + `php artisan queue:work` | `sync` in the example |
| `CACHE_STORE` / `SESSION_DRIVER` | `file` / `database` / `redis` | `file` |
| `MAIL_*` | SMTP used for activation, OTP and reset emails | `log` in the example |
| `PESAPAL_CONSUMER_KEY`, `PESAPAL_CONSUMER_SECRET` | PesaPal v3 credentials | — |
| `PESAPAL_URL`, `PESAPAL_MODE` | Gateway URL and `sandbox`/`live` | `https://pay.pesapal.com/v3`, `sandbox` |
| `PAYMENT_CALLBACK_TOKEN` | Optional shared secret for `POST /api/payment-callback` (send `X-Callback-Token`) | — |

All emails are sent synchronously, so registration and login work without a
queue worker. Mail failures are logged and never leave a half-created account.

## 5. Authentication

* `POST /api/register`, `POST /api/tenant-register` → activation email.
* `GET /api/auth/activate-account?token=…` → activates and redirects to the SPA.
* `POST /api/login` → emails a 6-digit OTP; `POST /api/verify-otp` returns a
  Sanctum bearer token plus the role-based `redirect` path.
* Send `Authorization: Bearer <token>` on every protected route.
* `POST /api/forgot-password` / `POST /api/reset-password` implement a
  token-based reset that links back to the SPA.
* `/api/admin/*` and `POST /api/courses/{id}/status` require `role = admin`.

## 6. Main endpoint groups

| Group | Examples |
| --- | --- |
| Public catalogue | `GET /api/training`, `/api/training/{id}`, `/api/public-courses`, `/api/public-reviews`, `/api/top-providers` |
| Auth | `/api/register`, `/api/login`, `/api/verify-otp`, `/api/resend-otp`, `/api/forgot-password`, `/api/reset-password` |
| Learner | `/api/my-learning`, `/api/my-enrollments`, `/api/my-payments`, `/api/courses/{id}/learning`, `/api/courses/{id}/complete-lesson`, `/api/reviews` |
| Payments | `/api/payment/send-otp`, `/api/payment/verify-otp`, `/api/payment/initiate`, `/api/pesapal/callback`, `/api/download-document/{reference}`, `/api/qr-code/{data}` |
| Provider | `/api/provider/onboarding`, `/api/provider/profile`, `/api/provider/payments`, `/api/provider/all-cohorts`, `/api/provider/cohort-students/{id}`, `/api/provider/reports/*`, `/api/provider/reviews` |
| Courses & cohorts | `/api/courses` (apiResource), `POST /api/courses/{id}` (multipart), `/api/courses/{id}/cohorts*`, `/api/courses/{id}/notes`, `/announcements`, `/tools`, `/submit-all` |
| Admin | `/api/admin/stats`, `/api/admin/financials`, `/api/admin/all-payments`, `/api/admin/reports/summary`, `/api/admin/providers/*`, `/api/admin/courses/*` |

Run `php artisan route:list` for the full list.

## 7. Deployment

A production `Dockerfile` (PHP 8.3 + Apache) is included. `render.yaml` uses
`runtime: docker`, because Render has no native PHP runtime.

```bash
docker build -t trainhub-api .
docker run -p 8000:10000 --env-file .env trainhub-api
```

Set the environment variables from the table above in the hosting dashboard.
The container entrypoint caches config/routes/views at start-up and runs
`php artisan migrate --force` (set `RUN_MIGRATIONS=false` to skip). Point the
health check at `/up`.

If you use a classic PaaS instead (`Procfile`), the start command is
`php artisan serve --host 0.0.0.0 --port $PORT`; run
`php artisan config:cache && php artisan route:cache && php artisan migrate --force`
as a release step.

## 8. Notes on the data model

* Course status is stored as `Draft` / `Published` / `Closed`; admin endpoints
  accept `PUBLISHED` / `DRAFT` and normalise them, so approved courses appear
  in the public catalogue immediately.
* A successful payment automatically creates an `enrollment` (payment observer).
* `2026_10_27_000001_repair_schema` adds columns that the original stub
  migrations never created (invoices, receipts, disputes, payment extras,
  lesson completions, password reset tokens). It is guarded by
  `Schema::hasColumn` / `hasTable`, so it is safe on new and existing databases.

## 9. Troubleshooting CORS

If the browser console says `No 'Access-Control-Allow-Origin' header is present
on the requested resource` (or the preflight fails), the API is either not
sending the header or is returning an error page / tunnel interstitial that
never reaches Laravel.

**0. Did the request reach Laravel at all?** Check this first — it is the most
common cause and the easiest to misread, because Chrome reports it as a CORS
error:

```
GET https://api-host/api/public-courses net::ERR_FAILED 404 (Not Found)
```

A **404 together with a CORS error on every endpoint** almost always means the
host in the SPA's `VITE_API_URL` / axios `baseURL` is answering, but not with
your application: a stopped tunnel, a stale ngrok URL, or a host pointed at the
frontend dev server instead of the API. One request settles it:

```bash
curl -i https://api-host/api/cors-check
```

| What comes back | Meaning |
| --- | --- |
| JSON with `"ok": true` | The request reached Laravel — go to **1**. |
| `ERR_NGROK_3200 … is offline` | The tunnel is down. Restart it with `ngrok http 8000 --url=https://<your-domain>.ngrok-free.dev`. Without a reserved domain the free tier picks a **random** URL on every restart, so a hard-coded ngrok URL in a deployed SPA breaks the moment the tunnel restarts. |
| A 404 HTML page, or a page served by Vite/nginx | The tunnel or DNS points at the wrong port. The API must be `php artisan serve --port=8000` (or the deployed container), not `npm run dev` on `:5173`. |
| `ERR_NGROK_6024` / an ngrok warning page | See **4**. |

`/api/cors-check` echoes the `Origin` it received, whether that origin is
allowed, and the active allowlist — so it also confirms the deployment is
running the current config.

**1. Check the allowlist.** `config/cors.php` allows every origin by default,
but if `CORS_ALLOWED_ORIGINS` is set, only those exact origins are allowed.
Every host that serves your SPA must be listed, including `www` and `https`:

```dotenv
CORS_ALLOWED_ORIGINS=https://traininghubtz.co.tz,https://www.traininghubtz.co.tz,http://localhost:5173
SANCTUM_STATEFUL_DOMAINS=traininghubtz.co.tz,www.traininghubtz.co.tz,localhost:5173
```

**2. Clear the config cache.** On any server using `php artisan config:cache`,
editing `.env` alone does nothing — Laravel keeps reading the cached values:

```bash
php artisan config:clear
php artisan config:cache   # optional, re-cache afterwards
php artisan optimize:clear # if you also cached routes/views
```

Restart `php artisan serve`, PHP-FPM or the container after this.

**3. Verify with curl** (no browser involved):

```bash
curl -i -H "Origin: https://traininghubtz.co.tz" https://YOUR-API/api/public-courses
curl -i -X OPTIONS -H "Origin: https://traininghubtz.co.tz" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: content-type,authorization" \
  https://YOUR-API/api/register
```

Both must return `Access-Control-Allow-Origin: https://traininghubtz.co.tz`.
If the body is HTML instead of JSON, the request never reached Laravel — see 4.

**4. ngrok.** The free tier serves an HTML interstitial (`ERR_NGROK_6024`) in
front of browser traffic. That page has no CORS headers, so the browser reports
a CORS error even though the API is fine. The header has to be sent **by the
client**, so it must be added in the React app, not in the tunnel config —
`ngrok http --request-header-add` adds the header on the way to your upstream
(and is deprecated), so it does not help here.

```js
// src/api/axio.js
api.defaults.headers.common["ngrok-skip-browser-warning"] = "true";
```

That makes every request non-simple, so each one now sends an OPTIONS preflight
first; Laravel answers it because `config/cors.php` allows all headers and
paths. Alternatively, open the ngrok URL in a browser tab and click **Visit**
once — ngrok then suppresses the interstitial for that browser for 7 days.

For anything user-facing, deploy the API to a real domain (the included
`Dockerfile` + `render.yaml` do this) and point the SPA at that URL instead of
a tunnel.

**5. Error responses.** `App\Support\Cors` is wired into the exception handler
via `$exceptions->respond(...)`, so 4xx/5xx responses also carry CORS headers.
If a CORS error persists, open the failing request in the Network tab and check
its real status/body — it is usually a 500 with the actual cause in the log.
