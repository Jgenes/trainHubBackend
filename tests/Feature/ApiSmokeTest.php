<?php

namespace Tests\Feature;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\Provider;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class ApiSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Plain Artisan::call is used instead of RefreshDatabase so the suite
        // also runs on runtimes that cannot handle its connection swapping.
        Artisan::call('migrate:fresh', ['--force' => true]);
    }


    private function user(string $role = 'user', array $attributes = []): User
    {
        static $seq = 0;
        $seq++;

        return User::create(array_merge([
            'name'              => 'Test User '.$seq,
            'email'             => "user{$seq}@example.test",
            'phone'             => '0712345'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT),
            'password'          => 'password123',
            'role'              => $role,
            'is_verified'       => true,
            'email_verified_at' => now(),
        ], $attributes));
    }

    private function token(User $user): string
    {
        return $user->createToken('test')->plainTextToken;
    }

    private function auth(User $user): array
    {
        // Sanctum's request guard caches the user between in-process requests.
        $this->app->make('auth')->forgetGuards();

        return ['Authorization' => 'Bearer '.$this->token($user)];
    }

    private function course(User $provider, string $status = 'Published', array $contents = []): Course
    {
        return Course::create([
            'provider_id'       => $provider->id,
            'title'             => 'Test Course',
            'category'          => 'IT',
            'mode'              => 'Online',
            'short_description' => 'Short',
            'long_description'  => 'Long',
            'contents'          => $contents ?: [
                ['title' => 'Lesson 1', 'description' => 'Intro', 'video_links' => [], 'handouts' => []],
                ['title' => 'Lesson 2', 'description' => 'More', 'video_links' => [], 'handouts' => []],
            ],
            'status'            => $status,
        ]);
    }

    private function paidPayment(User $student, Course $course, ?Cohort $cohort = null): Payment
    {
        return Payment::create([
            'reference'    => 'TRH-'.Str::random(8),
            'user_id'      => $student->id,
            'course_id'    => $course->id,
            'cohort_id'    => $cohort?->id,
            'first_name'   => 'Test',
            'last_name'    => 'Student',
            'email'        => $student->email,
            'phone_number' => $student->phone,
            'amount'       => 100000,
            'currency'     => 'TZS',
            'status'       => 'COMPLETED',
        ]);
    }

    public function test_every_route_action_resolves(): void
    {
        foreach (app('router')->getRoutes() as $route) {
            $action = $route->getActionName();
            if (! str_contains($action, '@')) {
                continue;
            }
            [$class, $method] = explode('@', $action, 2);
            $this->assertTrue(class_exists($class), "Missing controller $class");
            $this->assertTrue(method_exists($class, $method), "Missing method $class::$method");
        }
    }

    public function test_health_and_public_endpoints(): void
    {
        $this->get('/up')->assertOk();
        $this->getJson('/api/training')->assertOk()->assertJsonIsArray();
        $this->getJson('/api/public-courses')->assertOk()->assertJsonIsArray();
        $this->getJson('/api/public-reviews')->assertOk()->assertJsonIsArray();
        $this->getJson('/api/top-providers')->assertOk()->assertJsonIsArray();
        $this->getJson('/api/qr-code/hello')->assertOk()->assertJsonStructure(['qr_code']);
    }

    public function test_registration_activation_login_and_me(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/register', [
            'name'                  => 'Jane Doe',
            'email'                 => 'jane@example.test',
            'phone'                 => '0712345678',
            'password'              => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response->assertCreated();

        $user = User::where('email', 'jane@example.test')->firstOrFail();
        $this->assertFalse((bool) $user->is_verified);
        $this->assertNotNull($user->activation_token);

        $this->get('/api/auth/activate-account?token='.$user->activation_token)
            ->assertRedirect();

        $this->postJson('/api/login', ['email' => 'jane@example.test', 'password' => 'password123'])
            ->assertOk();

        $otp = $user->fresh()->login_otp;
        $this->assertNotNull($otp);

        $login = $this->postJson('/api/verify-otp', ['email' => 'jane@example.test', 'otp' => $otp])
            ->assertOk()
            ->assertJsonStructure(['token', 'user', 'redirect']);

        $this->withHeader('Authorization', 'Bearer '.$login->json('token'))
            ->getJson('/api/me')
            ->assertOk()
            ->assertJsonPath('email', 'jane@example.test');
    }

    public function test_password_reset_flow(): void
    {
        Mail::fake();
        $user = $this->user();

        $this->postJson('/api/forgot-password', ['email' => $user->email])->assertOk();

        $token = null;
        Mail::assertSent(\App\Mail\ResetPasswordMail::class, function ($mail) use (&$token) {
            $token = $mail->token;
            return true;
        });
        $this->assertNotNull($token);

        $this->postJson('/api/reset-password', [
            'token'                 => $token,
            'email'                 => $user->email,
            'password'              => 'newpassword123',
            'password_confirmation' => 'newpassword123',
        ])->assertOk();

        $this->assertTrue(\Illuminate\Support\Facades\Hash::check('newpassword123', $user->fresh()->password));
    }

    public function test_admin_routes_are_protected(): void
    {
        $user  = $this->user('user');
        $admin = $this->user('admin');

        $this->withHeaders($this->auth($user))->getJson('/api/admin/stats')->assertForbidden();
        $this->withHeaders($this->auth($admin))->getJson('/api/admin/stats')
            ->assertOk()
            ->assertJsonStructure(['totalUsers', 'totalProviders', 'totalCourses', 'totalRevenue', 'revenueChart']);
    }

    public function test_provider_kyc_documents_upload_onboarding_and_profile_and_download_privately(): void
    {
        Storage::fake('local');
        Mail::fake();

        $providerUser = $this->user('tenant');
        $providerHeaders = $this->auth($providerUser);

        $this->withHeaders($providerHeaders)->post('/api/provider/onboarding', [
            'legal_name'    => 'KYC Training Ltd',
            'provider_type' => 'Private',
            'region'        => 'Dar es Salaam',
            'contact_name'  => 'Jane Doe',
            'contact_phone' => '0712345678',
            'brela_doc'     => UploadedFile::fake()->create('brela.pdf', 100, 'application/pdf'),
        ])->assertCreated();

        $provider = Provider::where('created_by', $providerUser->id)->firstOrFail();
        $brelaPath = $provider->brela_doc;
        $this->assertNotNull($brelaPath);
        Storage::disk('local')->assertExists($brelaPath);

        $this->withHeaders($providerHeaders)->post('/api/provider/kyc/documents', [
            'tin_doc' => UploadedFile::fake()->create('tin.pdf', 100, 'application/pdf'),
        ])->assertOk()->assertJsonPath('data.brela_doc', $brelaPath);

        $provider->refresh();
        $this->assertNotNull($provider->tin_doc);
        Storage::disk('local')->assertExists($provider->tin_doc);

        $this->withHeaders($providerHeaders)->get('/api/provider/kyc/documents/brela')
            ->assertOk()
            ->assertHeader('content-disposition');
        $this->withHeaders($providerHeaders)->get('/api/provider/kyc/documents/tin')
            ->assertOk()
            ->assertHeader('content-disposition');

        $otherProvider = $this->user('tenant');
        $this->withHeaders($this->auth($otherProvider))
            ->getJson('/api/provider/kyc/documents/brela')
            ->assertNotFound();

        $admin = $this->user('admin');
        $this->withHeaders($this->auth($admin))
            ->get('/api/admin/providers/'.$provider->id.'/kyc/tin')
            ->assertOk()
            ->assertHeader('content-disposition');
    }

    public function test_provider_profile_update_accepts_kyc_documents(): void
    {
        Storage::fake('local');
        Mail::fake();

        $user = $this->user('tenant');
        $headers = $this->auth($user);
        $this->withHeaders($headers)->postJson('/api/provider/onboarding', [
            'legal_name'    => 'Profile Training Ltd',
            'provider_type' => 'Private',
            'region'        => 'Dar es Salaam',
            'contact_name'  => 'Jane Doe',
            'contact_phone' => '0712345678',
        ])->assertCreated();

        $this->withHeaders($headers)->post('/api/provider/profile/update', [
            'legal_name'    => 'Profile Training Ltd',
            'region'        => 'Dar es Salaam',
            'contact_name'  => 'Jane Doe',
            'contact_phone' => '0712345678',
            'contact_email' => $user->email,
            'tin_doc'       => UploadedFile::fake()->create('tin.pdf', 100, 'application/pdf'),
        ])->assertOk();

        $provider = Provider::where('created_by', $user->id)->firstOrFail();
        $this->assertNotNull($provider->tin_doc);
        Storage::disk('local')->assertExists($provider->tin_doc);
    }

    public function test_provider_course_and_cohort_flow(): void
    {
        Mail::fake();
        $provider = $this->user('tenant');
        $headers  = $this->auth($provider);

        $this->withHeaders($headers)->postJson('/api/provider/onboarding', [
            'legal_name'    => 'Test Training Ltd',
            'provider_type' => 'Private',
            'region'        => 'Dar es Salaam',
            'contact_name'  => 'John Doe',
            'contact_phone' => '0712345678',
        ])->assertCreated();

        $this->withHeaders($headers)->getJson('/api/provider/profile')->assertOk();

        $courseResponse = $this->withHeaders($headers)->postJson('/api/courses', [
            'title'             => 'Test Course',
            'category'          => 'IT',
            'mode'              => 'Online',
            'short_description' => 'Short',
            'long_description'  => 'Long',
            'learning_outcomes' => ['Learn PHP'],
            'contents'          => [['title' => 'Lesson 1', 'description' => 'Intro']],
        ]);
        $courseResponse->assertCreated();
        $courseId = $courseResponse->json('course.id');
        $this->assertNotNull($courseId);

        $this->withHeaders($headers)->getJson('/api/courses')->assertOk();
        $this->withHeaders($headers)->getJson('/api/courses/'.$courseId)->assertOk();

        $cohort = $this->withHeaders($headers)->postJson("/api/courses/{$courseId}/cohorts", [
            'intake_name'           => 'January Intake',
            'start_date'            => now()->addDay()->toDateString(),
            'end_date'              => now()->addMonth()->toDateString(),
            'capacity'              => 30,
            'price'                 => 100000,
            'registration_deadline' => now()->addDays(5)->toDateString(),
            'status'                => 'OPEN',
        ])->assertCreated();
        $cohortId = $cohort->json('id');

        $this->withHeaders($headers)->getJson("/api/courses/{$courseId}/cohorts")->assertOk();
        $this->withHeaders($headers)->patchJson("/api/courses/{$courseId}/cohorts/{$cohortId}/status", ['status' => 'CLOSED'])->assertOk();
        $this->withHeaders($headers)->postJson("/api/courses/{$courseId}/cohorts/refresh-statuses")->assertOk();

        $this->withHeaders($headers)->getJson("/api/courses/{$courseId}/notes")->assertOk();
        $this->withHeaders($headers)->getJson("/api/courses/{$courseId}/announcements")->assertOk();
        $this->withHeaders($headers)->getJson("/api/courses/{$courseId}/tools")->assertOk();
        $this->withHeaders($headers)->postJson("/api/courses/{$courseId}/submit-all", [
            'announcement_title' => 'Welcome',
            'announcement_body'  => 'Hello students',
            'tool_name'          => 'Slides',
            'tool_link'          => 'https://example.test/slides',
        ])->assertCreated();

        $this->withHeaders($headers)->postJson("/api/courses/{$courseId}/announcement", [
            'title' => 'Update', 'content' => 'New materials',
        ])->assertCreated();
        $this->withHeaders($headers)->getJson("/api/courses/{$courseId}/announcements")->assertOk();
    }

    public function test_dashboard_reports_and_admin_screens(): void
    {
        Mail::fake();
        $provider = $this->user('tenant');
        $student  = $this->user('user');
        $admin    = $this->user('admin');
        $course   = $this->course($provider);
        $this->paidPayment($student, $course);

        foreach (['/api/dashboard/stats', '/api/provider/reviews',
                  '/api/provider/reports/courses', '/api/provider/reports/courses-list',
                  '/api/provider/reports/enrollments', '/api/provider/reports/revenue',
                  '/api/provider/all-cohorts', '/api/provider/payments',
                  '/api/provider/enrollments-view', '/api/my-enrollments',
                  '/api/my-learning', '/api/my-payments'] as $uri) {
            $this->withHeaders($this->auth($provider))->getJson($uri)->assertOk($uri);
        }

        foreach (['/api/admin/stats', '/api/admin/financials', '/api/admin/all-payments',
                  '/api/admin/reports/summary', '/api/admin/notifications/count',
                  '/api/admin/courses', '/api/admin/providers'] as $uri) {
            $this->withHeaders($this->auth($admin))->getJson($uri)->assertOk($uri);
        }

        $this->withHeaders($this->auth($student))->getJson('/api/public-reviews')->assertOk();
    }

    public function test_payment_initiate_callback_and_enrollment(): void
    {
        Http::fake([
            '*/api/Auth/RequestToken'               => Http::response(['token' => 'test-token']),
            '*/api/URLSetup/RegisterIPN'            => Http::response(['ipn_id' => 'ipn-1']),
            '*/api/Transactions/SubmitOrderRequest' => Http::response([
                'order_tracking_id' => 'tracking-1',
                'redirect_url'      => 'https://pay.pesapal.com/iframe/1',
            ]),
            '*/api/Transactions/GetTransactionStatus*' => Http::response([
                'payment_status_description' => 'Completed',
            ]),
        ]);

        config()->set('services.pesapal.key', 'test-key');
        config()->set('services.pesapal.secret', 'test-secret');

        $provider = $this->user('tenant');
        $student  = $this->user('user');
        $course   = $this->course($provider);
        $headers  = $this->auth($student);

        $this->withHeaders($headers)->postJson('/api/payment/initiate', [
            'course_id' => $course->id,
            'amount'    => 100000,
        ])->assertOk()->assertJsonPath('success', true);

        $payment = Payment::firstOrFail();
        $this->assertDatabaseHas('invoices', ['payment_id' => $payment->id]);

        $this->get('/api/pesapal/callback?OrderTrackingId=tracking-1&OrderMerchantReference='.$payment->reference)
            ->assertRedirect();

        $this->assertDatabaseHas('payments', ['id' => $payment->id, 'status' => 'COMPLETED']);
        $this->assertDatabaseHas('receipts', ['payment_id' => $payment->id]);
        $this->assertDatabaseHas('enrollments', ['user_id' => $student->id, 'course_id' => $course->id]);
    }

    public function test_exports_documents_reviews_and_learning_progress(): void
    {
        Mail::fake();
        $provider = $this->user('tenant');
        $student  = $this->user('user');
        $course   = $this->course($provider);
        $payment  = $this->paidPayment($student, $course);
        $headers  = $this->auth($student);

        $this->withHeaders($headers)->getJson("/api/courses/{$course->id}/learning")
            ->assertOk()
            ->assertJsonStructure(['modules', 'progress_percentage']);

        $this->withHeaders($headers)->postJson("/api/courses/{$course->id}/complete-lesson", ['lesson_id' => 1])
            ->assertOk();

        $this->withHeaders($headers)->getJson("/api/courses/{$course->id}/learning")
            ->assertOk()
            ->assertJsonPath('progress_percentage', 50);

        $this->withHeaders($headers)->postJson('/api/reviews', [
            'course_id' => $course->id, 'rating' => 5, 'comment' => 'Great course',
        ])->assertOk();

        $this->withHeaders($headers)->getJson('/api/download-document/'.$payment->reference)->assertOk();
        $this->withHeaders($headers)->getJson('/api/provider/download-document/'.$payment->reference)->assertOk();

        $providerHeaders = $this->auth($provider);
        $this->withHeaders($providerHeaders)->getJson('/api/provider/reports/revenue?format=pdf')->assertOk();
        $this->withHeaders($providerHeaders)->getJson('/api/provider/reports/enrollments?format=excel')->assertOk();
    }
    /**
     * Hit every GET API route with a valid token and make sure none of them
     * blow up with a 500. Missing records (404) and redirects are fine.
     */
    public function test_all_get_routes_are_error_free(): void
    {
        Mail::fake();
        Http::fake();

        $admin    = $this->user('admin');
        $provider = $this->user('tenant');
        $student  = $this->user('user');

        $replacements = [
            'reference' => 'NO-SUCH-REF',
            'data'      => 'test',
            'type'      => 'revenue',
        ];

        foreach (app('router')->getRoutes() as $route) {
            if (! in_array('GET', explode('|', $route->methods() ? implode('|', $route->methods()) : ''), true)) {
                continue;
            }

            $uri = $route->uri();
            if (! str_starts_with($uri, 'api/') || str_contains($uri, '{path')) {
                continue;
            }

            $path = preg_replace_callback('/\{([^}]+?)\??\}/', function ($m) use ($replacements) {
                return $replacements[$m[1]] ?? '1';
            }, $uri);

            $user = str_contains($uri, 'admin/') ? $admin
                : (str_contains($uri, 'provider/') || str_contains($uri, 'courses/') ? $provider : $student);

            $response = $this->withHeaders($this->auth($user))->getJson('/'.$path);

            $this->assertLessThan(
                500,
                $response->getStatusCode(),
                "GET /{$path} returned {$response->getStatusCode()}: ".substr($response->getContent(), 0, 250)
            );
        }
    }

}
