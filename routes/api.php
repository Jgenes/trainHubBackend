<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProviderOnboardingController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\TrainingController;

// Public
Route::post('/register', [AuthController::class, 'userRegister']);
Route::post('/tenant-register', [AuthController::class, 'tenantRegister']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-otp', [AuthController::class, 'verifyLoginOtp']);
Route::get('/activate-account', [AuthController::class, 'activateAccount']);

// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {

    // Me
Route::get('/me', function () {
return auth()->user();
});

    // Provider Onboarding
Route::post('/provider/onboarding', [ProviderOnboardingController::class,'store']);
    // Courses & Cohorts
Route::apiResource('courses', CourseController::class);
    Route::prefix('courses/{courseId}/cohorts')->group(function () {
        Route::get('/', [CohortController::class, 'index']); // List all cohorts for course
        Route::post('/', [CohortController::class, 'store']); // Create new cohort
        Route::get('{cohortId}', [CohortController::class, 'show']); // Show single cohort
        Route::put('{cohortId}', [CohortController::class, 'update']); // Update cohort
        Route::delete('{cohortId}', [CohortController::class, 'destroy']); // Delete cohort
    });

    //traiining courses listing


    // Enrollments & Checkout
Route::post('/checkout', [EnrollmentController::class, 'startCheckout']);
Route::post('/checkout/{enrollment}/confirm', [EnrollmentController::class, 'confirmPayment']);
Route::get('/my-enrollments', [EnrollmentController::class, 'myEnrollments']);
});
    Route::get('/training', [TrainingController::class, 'index']);
    Route::get('/training/{id}', [TrainingController::class, 'show']);

use App\Http\Controllers\PaymentController;

// web.php
Route::get('/pesapal/redirect/{payment}', [PaymentController::class, 'redirect'])->name('pesapal.redirect');
Route::get('/pesapal/callback', [PaymentController::class, 'callback'])->name('pesapal.callback');
Route::post('/courses/{course}/announcement', [CourseController::class, 'addAnnouncement']);
Route::post('/courses/{course}/tool', [CourseController::class, 'addLearningTool']);
Route::post('/courses/{course}/question', [CourseController::class, 'askQuestion']);
Route::post('/question/{id}/answer', [CourseController::class, 'answerQuestion']);
Route::post('/courses/{course}/review', [CourseController::class, 'addReview']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);

Route::post('/courses/{course}/submit-all', [CourseController::class, 'submitAll']);
Route::post('/pay', [PesapalController::class, 'pay'])->name('pesapal.pay');

Route::get('/pesapal/redirect', [PesapalController::class, 'redirect'])
    ->name('pesapal.redirect');

Route::post('/pesapal/callback', [PesapalController::class, 'callback'])
    ->name('pesapal.callback');
