<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProviderOnboardingController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\LearningController;
use App\Http\Controllers\ProviderProfileController;
use App\Http\Controllers\ProviderSettingsController;
use App\Http\Controllers\ProviderReportController;

/*
|--------------------------------------------------------------------------
| Public Routes (Hazihitaji Login)
|--------------------------------------------------------------------------
*/
Route::post('/register', [AuthController::class, 'userRegister']);
Route::post('/tenant-register', [AuthController::class, 'tenantRegister']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-otp', [AuthController::class, 'verifyLoginOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::get('/activate-account', [AuthController::class, 'activateAccount'])->name('activate.account');

// Public Training listing
Route::get('/training', [TrainingController::class, 'index']);
Route::get('/training/{id}', [TrainingController::class, 'show']);

/*
|--------------------------------------------------------------------------
| PesaPal Webhooks (LAZIMA ziwe nje ya Auth Middleware)
|--------------------------------------------------------------------------
*/
// PesaPal inahitaji kugusa hapa bila Token yoyote
Route::get('/pesapal/callback', [PaymentController::class, 'callback'])->name('pesapal.callback');
Route::post('/payment-callback', [EnrollmentController::class, 'handlePaymentCallback']);
Route::get('/pesapal-callback', [PaymentController::class, 'callback'])->name('pesapal.callback');

/*
|--------------------------------------------------------------------------
| Authenticated Routes (Lazima uwe umelogin)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // 1. Payment Initiation
    Route::post('/payment/initiate', [PaymentController::class, 'initiate']);

    // 2. User Info
    Route::get('/me', function () {
        return auth()->user();
    });

    // 3. Learning & Enrollments
    Route::get('/my-learning', [LearningController::class, 'myCourses']);
    Route::get('/my-enrollments', [EnrollmentController::class, 'myEnrollments']);
    Route::get('/my-payments', [EnrollmentController::class, 'myPayments']);

    // 4. Provider Specific Routes
    Route::prefix('provider')->group(function () {
        Route::post('/onboarding', [ProviderOnboardingController::class, 'store']);
        Route::get('/profile', [ProviderProfileController::class, 'index']);
        Route::put('/profile/update', [ProviderProfileController::class, 'update']);
        Route::get('/enrollments', [EnrollmentController::class, 'providerEnrollments']);
        Route::get('/payments', [EnrollmentController::class, 'providerPayments']);
        Route::get('/reports/courses', [ProviderReportController::class, 'getCourseStats']);
        
        // Settings
        Route::put('/settings/password', [ProviderSettingsController::class, 'updatePassword']);
        Route::put('/settings/email', [ProviderSettingsController::class, 'updateEmail']);
    });

    // 5. Course Management
    Route::apiResource('courses', CourseController::class);
    Route::prefix('courses/{courseId}/cohorts')->group(function () {
        Route::get('/', [CohortController::class, 'index']);
        Route::post('/', [CohortController::class, 'store']);
        Route::get('{cohortId}', [CohortController::class, 'show']);
        Route::put('{cohortId}', [CohortController::class, 'update']);
        Route::delete('{cohortId}', [CohortController::class, 'destroy']);
    });

    // 6. Admin Routes
    Route::get('/admin/all-payments', [EnrollmentController::class, 'allPayments']);
    Route::delete('/admin/payments/{id}', [EnrollmentController::class, 'deletePayment']);

    // 7. Course Interactions
    Route::post('/courses/{course}/announcement', [CourseController::class, 'addAnnouncement']);
    Route::post('/courses/{course}/tool', [CourseController::class, 'addLearningTool']);
    Route::post('/courses/{course}/question', [CourseController::class, 'askQuestion']);
    Route::post('/question/{id}/answer', [CourseController::class, 'answerQuestion']);
    Route::post('/courses/{course}/review', [CourseController::class, 'addReview']);
    Route::post('/courses/{course}/submit-all', [CourseController::class, 'submitAll']);
});

// Downloads
Route::get('/public-courses', [CourseController::class, 'publicIndex']);
Route::get('/download-document/{reference}', [EnrollmentController::class, 'downloadDoc'])->name('download.doc');
Route::middleware('auth:sanctum')->group(function () {
Route::post('/lessons/{id}/complete', [LearningController::class, 'completeLesson']);
    // --- NEW: Course Learning Content Route ---
    // Hii itaitwa na React: api.get(`/courses/${courseId}/learning`)
Route::get('/courses/{id}/learning', [LearningController::class, 'learningContent']);
    // 1. Payment Initiation
    
    // ... route zingine zote zilizopo ...
});

