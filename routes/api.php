<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AdminCourseController,
    AdminNotificationController,
    AdminProviderController,
    AdminReportController,
    AdminStatsController,
    AuthController,
    CheckoutController,
    CohortController,
    CourseController,
    CourseExtrasController,
    DashboardController,
    EnrollmentController,
    LearningController,
    MailCheckController,
    PasswordResetController,
    PaymentController,
    ProviderOnboardingController,
    ProviderKycController,
    ProviderProfileController,
    ProviderReportController,
    ProviderSettingsController,
    PublicController,
    QrCodeController,
    ReviewController,
    TrainingController
};

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/
Route::get('/training', [TrainingController::class, 'index']);
Route::get('/training/{id}', [TrainingController::class, 'show'])->whereNumber('id');
Route::get('/public-courses', [CourseController::class, 'publicIndex']);
Route::get('/public-reviews', [ReviewController::class, 'publicReviews']);
Route::get('/top-providers', [PublicController::class, 'topProviders']);

// CORS canary: confirms the request actually reached Laravel. See
// PublicController::corsCheck() and "Troubleshooting CORS" in the README.
Route::get('/cors-check', [PublicController::class, 'corsCheck']);

// Mail canary: explains why activation/OTP mail never arrives. Answers 404
// until MAIL_CHECK_TOKEN is configured. See MailCheckController.
Route::match(['get', 'post'], '/mail-check', MailCheckController::class);

Route::post('/register', [AuthController::class, 'userRegister']);
Route::post('/tenant-register', [AuthController::class, 'tenantRegister']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/verify-otp', [AuthController::class, 'verifyLoginOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::get('/auth/activate-account', [AuthController::class, 'activateAccount'])->name('activate.account');

Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink']);
Route::post('/reset-password', [PasswordResetController::class, 'reset']);

// Public document download (the reference acts as the capability token).
Route::get('/download-document/{reference}', [EnrollmentController::class, 'downloadDoc'])
    ->name('download.doc');

// Payment gateway / webhook endpoints.
Route::match(['get', 'post'], '/pesapal/callback', [PaymentController::class, 'callback'])
    ->name('pesapal.callback');
Route::post('/payment-callback', [EnrollmentController::class, 'handlePaymentCallback']);
Route::get('/qr-code/{data}', [QrCodeController::class, 'generateQrCode'])->where('data', '.*');

/*
|--------------------------------------------------------------------------
| Authenticated routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', fn () => auth()->user());
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']);

    // ----- Admin ---------------------------------------------------------
    Route::prefix('admin')->middleware('admin')->group(function () {
        Route::get('/stats', [AdminStatsController::class, 'getAdminDashboardData']);
        Route::get('/financials', [PaymentController::class, 'adminFinancials']);
        Route::get('/all-payments', [EnrollmentController::class, 'allPayments']);
        Route::get('/reports/summary', [AdminReportController::class, 'getAdminReports']);
        Route::get('/notifications/count', [AdminNotificationController::class, 'getCount']);

        Route::get('/providers', [AdminProviderController::class, 'index']);
        Route::get('/providers/{id}/kyc/{document}', [ProviderKycController::class, 'downloadForAdmin'])
            ->whereIn('document', ['brela', 'tin'])
            ->name('admin.provider.kyc.documents.download');
        Route::post('/providers/{id}/approve', [AdminProviderController::class, 'approveStatus']);
        Route::post('/providers/{id}/suspend', [AdminProviderController::class, 'suspendStatus']);
        Route::post('/providers/{id}/reject', [AdminProviderController::class, 'rejectStatus']);

        Route::get('/courses', [AdminCourseController::class, 'index']);
        Route::post('/courses/{id}/approve', [AdminCourseController::class, 'approveCourse']);
        Route::post('/courses/{id}/status', [AdminCourseController::class, 'updateStatus']);
    });

    // The admin UI uses this short path; keep it behind the admin guard.
    Route::post('/courses/{id}/status', [AdminCourseController::class, 'updateStatus'])
        ->middleware('admin')
        ->whereNumber('id');

    // ----- Provider ------------------------------------------------------
    Route::prefix('provider')->group(function () {
        Route::get('/profile', [ProviderProfileController::class, 'index']);
        Route::put('/profile/update', [ProviderProfileController::class, 'update']);
        Route::post('/profile/update', [ProviderProfileController::class, 'update']);
        Route::post('/onboarding', [ProviderOnboardingController::class, 'store']);
        Route::post('/kyc/documents', [ProviderKycController::class, 'update']);
        Route::get('/kyc/documents/{document}', [ProviderKycController::class, 'downloadForProvider'])
            ->whereIn('document', ['brela', 'tin'])
            ->name('provider.kyc.documents.download');
        Route::get('/payments', [EnrollmentController::class, 'providerPayments']);
        Route::post('/payments/{id}/status', [EnrollmentController::class, 'providerUpdatePaymentStatus'])
            ->whereNumber('id');
        Route::get('/courses/{courseId}/enrollments', [EnrollmentController::class, 'providerCourseEnrollments'])
            ->whereNumber('courseId');
        Route::get('/enrollments-view', [EnrollmentController::class, 'allProviderEnrollments']);
        Route::get('/all-cohorts', [EnrollmentController::class, 'allProviderCohorts']);
        Route::get('/cohort-students/{cohortId}', [EnrollmentController::class, 'getCohortStudents'])
            ->whereNumber('cohortId');
        Route::get('/download-document/{reference}', [EnrollmentController::class, 'downloadDoc'])
            ->name('provider.download.doc');
        Route::get('/reviews', [ReviewController::class, 'providerReviews']);

        Route::prefix('settings')->group(function () {
            Route::put('/password', [ProviderSettingsController::class, 'updatePassword']);
            Route::put('/email', [ProviderSettingsController::class, 'updateEmail']);
        });

        Route::prefix('reports')->group(function () {
            Route::get('/courses', [ProviderReportController::class, 'getCourseStats']);
            Route::get('/courses-list', [ProviderReportController::class, 'coursesList']);
            Route::get('/enrollments', [ProviderReportController::class, 'enrollmentReport']);
            Route::get('/revenue', [ProviderReportController::class, 'revenueReport']);
            // Export must come after the specific report routes.
            Route::get('/{type}', [ProviderReportController::class, 'export'])
                ->whereIn('type', ['enrollments', 'revenue']);
        });
    });

    // ----- Learning ------------------------------------------------------
    Route::get('/my-learning', [LearningController::class, 'myCourses']);
    Route::get('/my-enrollments', [EnrollmentController::class, 'myEnrollments']);
    Route::get('/my-payments', [EnrollmentController::class, 'myPayments']);
    Route::get('/courses/{id}/learning', [LearningController::class, 'learningContent'])->whereNumber('id');
    Route::post('/courses/{courseId}/complete-lesson', [LearningController::class, 'completeCourseLesson'])
        ->whereNumber('courseId');
    Route::post('/lessons/{id}/complete', [LearningController::class, 'completeLesson'])->whereNumber('id');

    Route::post('/reviews', [ReviewController::class, 'store']);

    // ----- Payments ------------------------------------------------------
    Route::prefix('payment')->group(function () {
        Route::post('/initiate', [PaymentController::class, 'initiate']);
        Route::post('/send-otp', [PaymentController::class, 'sendOtp']);
        Route::post('/verify-otp', [PaymentController::class, 'verifyOtp']);
    });

    Route::post('/checkout', [CheckoutController::class, 'store']);
    Route::post('/checkout/{enrollmentId}/confirm', [CheckoutController::class, 'confirm']);

    // ----- Courses, cohorts and course extras ----------------------------
    Route::apiResource('courses', CourseController::class);
    Route::post('/courses/{id}', [CourseController::class, 'update'])->whereNumber('id'); // multipart fix

    Route::prefix('courses/{courseId}')->whereNumber('courseId')->group(function () {
        Route::post('/announcement', [CourseController::class, 'addAnnouncement']);
        Route::post('/question', [CourseController::class, 'askQuestion']);
        Route::get('/notes', [CourseExtrasController::class, 'notes']);
        Route::get('/announcements', [CourseExtrasController::class, 'announcements']);
        Route::get('/tools', [CourseExtrasController::class, 'tools']);
        Route::post('/submit-all', [CourseExtrasController::class, 'submitAll']);

        Route::prefix('cohorts')->group(function () {
            Route::get('/', [CohortController::class, 'index']);
            Route::post('/', [CohortController::class, 'store']);
            Route::post('/refresh-statuses', [CohortController::class, 'refreshStatuses']);
            Route::get('/{cohortId}', [CohortController::class, 'show'])->whereNumber('cohortId');
            Route::put('/{cohortId}', [CohortController::class, 'update'])->whereNumber('cohortId');
            Route::patch('/{cohortId}/status', [CohortController::class, 'updateStatus'])->whereNumber('cohortId');
            Route::delete('/{cohortId}', [CohortController::class, 'destroy'])->whereNumber('cohortId');
        });
    });

    Route::post('/question/{id}/answer', [CourseController::class, 'answerQuestion'])->whereNumber('id');
});
