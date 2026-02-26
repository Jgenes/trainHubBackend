<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\{
    AuthController,
    ProviderOnboardingController,
    CourseController,
    CohortController,
    EnrollmentController,
    TrainingController,
    PaymentController,
    LearningController,
    ProviderProfileController,
    ProviderSettingsController,
    ProviderReportController,
    DashboardController,
    AdminCourseController,
    AdminStatsController,
    AdminProviderController,
    AdminReportController,
    AdminNotificationController,
    ReviewController,
    QrCodeController
};

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'userRegister']);
Route::post('/tenant-register', [AuthController::class, 'tenantRegister']);
Route::post('/login', [AuthController::class, 'login'])->name('login');
Route::post('/verify-otp', [AuthController::class, 'verifyLoginOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::get('/auth/activate-account', [AuthController::class, 'activateAccount'])->name('activate.account');

Route::get('/training', [TrainingController::class, 'index']);
Route::get('/training/{id}', [TrainingController::class, 'show']);
Route::get('/public-courses', [CourseController::class, 'publicIndex']);

// HII NDIYO ROUTE MPYA YA PUBLIC REVIEWS
Route::get('/public-reviews', [ReviewController::class, 'publicReviews']);
Route::get('/download-documenreference}', [EnrollmentController::class, 'downloadDoc'])->name('download.doc');

// Webhooks
Route::get('/pesapal/callback', [PaymentController::class, 'callback'])->name('pesapal.callback');
Route::post('/payment-callback', [EnrollmentController::class, 'handlePaymentCallback']);


/*
|--------------------------------------------------------------------------
| Authenticated Routes (Sanctum)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {
// Upande wa Mwanafunzi
Route::post('/reviews', [ReviewController::class, 'store']);

// Upande wa Provider (Iweke ndani ya prefix ya provider uliyotengeneza mwanzo)
Route::get('/provider/reviews', [ReviewController::class, 'providerReviews']);
    // 1. Common Authenticated Routes
    Route::get('/me', function () { return auth()->user(); });
    Route::get('/dashboard/stats', [DashboardController::class, 'getStats']); // Default stats

    // 2. Provider Routes Group
    Route::prefix('provider')->group(function () {
        Route::get('/profile', [ProviderProfileController::class, 'index']);
        Route::put('/profile/update', [ProviderProfileController::class, 'update']);
        Route::post('/onboarding', [ProviderOnboardingController::class, 'store']);
        Route::get('/payments', [EnrollmentController::class, 'providerPayments']);
        Route::post('/payments/{id}/status', [EnrollmentController::class, 'providerUpdatePaymentStatus']);
        Route::get('/courses/{courseId}/enrollments', [EnrollmentController::class, 'providerCourseEnrollments']);
        // Provider Reports & Engagement
        Route::prefix('reports')->group(function () {
            Route::get('/courses', [ProviderReportController::class, 'getCourseStats']);
            Route::get('/courses-list', [ProviderReportController::class, 'coursesList']);
            Route::get('/enrollments', [ProviderReportController::class, 'enrollmentReport']);
        
        // 3. Route ya Ripoti ya Mapato (Revenue)
        Route::get('/revenue', [ProviderReportController::class, 'revenueReport']);
        });

        // Provider Settings
        Route::prefix('settings')->group(function () {
            Route::put('/password', [ProviderSettingsController::class, 'updatePassword']);
            Route::put('/email', [ProviderSettingsController::class, 'updateEmail']);
        });
    });

    // 3. Admin Routes Group
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminStatsController::class, 'getAdminDashboardData']);
        Route::get('/financials', [PaymentController::class, 'adminFinancials']);
        Route::get('/all-payments', [EnrollmentController::class, 'allPayments']);
        Route::get('/reports/summary', [AdminReportController::class, 'getAdminReports']);
        Route::get('/notifications/count', [AdminNotificationController::class, 'getCount']);

        // Admin Provider Management
        Route::get('/providers', [AdminProviderController::class, 'index']);
        Route::post('/providers/{id}/approve', [AdminProviderController::class, 'approveStatus']);
        Route::post('/providers/{id}/suspend', [AdminProviderController::class, 'suspendStatus']);
        Route::post('/providers/{id}/reject', [AdminProviderController::class, 'rejectStatus']);

        // Admin Course Management
        Route::get('/courses', [AdminCourseController::class, 'index']);
        Route::post('/courses/{id}/approve', [AdminCourseController::class, 'approveCourse']);
        Route::post('/courses/{id}/status', [AdminCourseController::class, 'updateStatus']);
    });

    // 4. Learning & Student Routes
    Route::get('/my-learning', [LearningController::class, 'myCourses']);
    Route::get('/my-enrollments', [EnrollmentController::class, 'myEnrollments']);
    Route::get('/my-payments', [EnrollmentController::class, 'myPayments']);
    Route::get('/courses/{id}/learning', [LearningController::class, 'learningContent']);
    Route::post('/lessons/{id}/complete', [LearningController::class, 'completeLesson']);

    // 5. Payment Actions (User side)
    Route::prefix('payment')->group(function () {
        Route::post('/initiate', [PaymentController::class, 'initiate']);
        Route::post('/send-otp', [PaymentController::class, 'sendOtp']);
        Route::post('/verify-otp', [PaymentController::class, 'verifyOtp']);
    });

    // 6. Course & Cohort Management (General)
    Route::apiResource('courses', CourseController::class);
    Route::post('/courses/{id}', [CourseController::class, 'update']); // Multipart fix
    
    Route::prefix('courses/{courseId}')->group(function () {
        Route::post('/announcement', [CourseController::class, 'addAnnouncement']);
        Route::post('/question', [CourseController::class, 'askQuestion']);
        
        // Cohorts Nested Routes
        Route::prefix('cohorts')->group(function () {
            Route::get('/', [CohortController::class, 'index']);
            Route::post('/', [CohortController::class, 'store']);
            Route::get('/{cohortId}', [CohortController::class, 'show']);
            Route::put('/{cohortId}', [CohortController::class, 'update']);
            Route::delete('/{cohortId}', [CohortController::class, 'destroy']);
        });
    });

    Route::post('/question/{id}/answer', [CourseController::class, 'answerQuestion']);
});

Route::middleware('auth:sanctum')->get('/provider/enrollments-view', [EnrollmentController::class, 'allProviderEnrollments']);
Route::middleware('auth:sanctum')->get('/provider/all-cohorts', [EnrollmentController::class, 'allProviderCohorts']);
// Route::get('provider/cohort-students/{cohortId}', [App\Http\Controllers\EnrollmentController::class, 'getCohortStudents']);
Route::middleware('auth:sanctum')->get('/provider/download-document/{reference}', [EnrollmentController::class, 'downloadDoc'])->name('provider.download.doc');
Route::get('/qr-code/{data}', [QrCodeController::class, 'generateQrCode']);
Route::middleware('auth:sanctum')->get('/provider/reports/{type}', [ProviderReportController::class, 'export']);