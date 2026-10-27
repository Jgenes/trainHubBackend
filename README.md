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
| `CORS_ALLOWED_ORIGINS` | Comma-separated origins allowed with credentials | `localhost:5173,127.0.0.1:5173,localhost:3000` |
| `SANCTUM_STATEFUL_DOMAINS` | First-party SPA domains | `localhost:5173,...` |
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
