<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProviderOnboardingController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\CohortController;
use App\Http\Controllers\EnrollmentController;
use App\Http\Controllers\TrainingController;
use App\Http\Controllers\PesapalController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\LearningController;
// Public
Route::post('/register', [AuthController::class, 'userRegister']);
Route::post('/tenant-register', [AuthController::class, 'tenantRegister']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-otp', [AuthController::class, 'verifyLoginOtp']);
// Iweke hapa juu kabisa ya file au nje ya group la auth
Route::get('/activate-account', [AuthController::class, 'activateAccount'])->name('activate.account');
// Authenticated routes
Route::middleware('auth:sanctum')->group(function () {
Route::post('/payment/initiate', [PaymentController::class, 'initiate']);
Route::get('/pesapal/redirect/{payment}', [PaymentController::class, 'redirect'])->name('pesapal.redirect');
Route::get('/pesapal/callback', [PaymentController::class, 'callback'])->name('pesapal.callback');
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


// web.php

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
    
    // Route ya kuangalia kama user ana access na kozi
    // Route::get('/user/courses', [UserController::class, 'myCourses']);

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-learning', [LearningController::class, 'myCourses']);
Route::get('/provider/enrollments', [LearningController::class, 'providerEnrollments']
);
Route::get('/my-payments', [EnrollmentController::class, 'myPayments']);
Route::get('/admin/all-payments', [EnrollmentController::class, 'allPayments']);
    Route::delete('/admin/payments/{id}', [EnrollmentController::class, 'deletePayment']);
    Route::get('/provider/payments', [EnrollmentController::class, 'providerPayments']);
    
// api.php
Route::get('/provider/enrollments', [EnrollmentController::class, 'providerEnrollments']);
});

Route::post('/payment-callback', [EnrollmentController::class, 'handlePaymentCallback']);

Route::get('/download-document/{reference}', [EnrollmentController::class, 'downloadDoc'])->name('download.doc');
