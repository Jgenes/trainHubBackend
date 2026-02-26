<?php
namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\Request;
use Barryvdh\DomPDF\Facade\Pdf;
use App\Mail\PaymentReceiptMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;
use App\Models\Enrollment as EnrollmentModel;
use Illuminate\Support\Facades\Log;

class EnrollmentController extends Controller
{
    /**
     * Handle the Payment Callback/Webhook
     */
    public function handlePaymentCallback(Request $request)
    {
        // 1. Validate the incoming request
        $reference = $request->input('reference');
        $status = strtoupper($request->input('status', 'PENDING'));

        // 2. Find the payment or fail
$payment = Payment::with(['course', 'cohort', 'user'])->where('reference', $reference)->first();        
        if (!$payment) {
            return response()->json(['status' => 'error', 'message' => 'Payment reference not found'], 404);
        }

        try {
            // 3. Prepare data for the update
            $updateData = [
                'status' => $status,
                'user_name' => $request->input('user_name', $payment->user_name),
                'payment_method' => $request->input('payment_method', 'Mobile Money / Card'),
            ];

            // If it's a new invoice (Pending), set an expiry date (e.g., 7 days)
            if ($status === 'PENDING' && !$payment->expires_at) {
                $updateData['expires_at'] = Carbon::now()->addDays(7);
            }

            $payment->update($updateData);

            // 4. Generate the correct PDF type
            // If status is PENDING, we use invoice. If COMPLETED/PAID, we use receipt.
            $isPaid = in_array($status, ['COMPLETED', 'PAID', 'SUCCESS']);
            $view = $isPaid ? 'pdf.receipt' : 'pdf.invoice';
            $mailType = $isPaid ? 'receipt' : 'invoice';

            $pdf = Pdf::loadView($view, compact('payment'));

            // 5. Send Email with Attachment
            Mail::to($payment->email)->send(new PaymentReceiptMail($payment, $pdf, $mailType));

            return response()->json([
                'status' => 'success',
                'message' => "Payment updated to $status and $mailType sent.",
                'data' => [
                    'reference' => $payment->reference,
                    'expiry' => $payment->expires_at
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'System error during processing',
                'error_detail' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manual Download Link for Users
     */
    /**
 * Manual Download Link for Users
 * Tumeshajiridhisha kuwa Route inaita downloadDoc
 */
public function downloadDoc($reference)
{
    // 1. Vuta data pamoja na mahusiano yake yote
    $payment = Payment::with(['course', 'cohort', 'user'])->where('reference', $reference)->firstOrFail();
    
    // 2. Angalia kama ameshalipa au bado
    $isPaid = in_array($payment->status, ['COMPLETED', 'PAID', 'SUCCESS']);
    
    // 3. Chagua View na Jina la faili
    $view = $isPaid ? 'pdf.receipt' : 'pdf.invoice';
    $filename = $isPaid ? "Receipt-{$reference}.pdf" : "Invoice-{$reference}.pdf";

    // 4. Tengeneza PDF
    $pdf = Pdf::loadView($view, compact('payment'));
    
    // 5. Download
    return $pdf->download($filename);
}
    /**
 * Get the authenticated user's payment history
 */
/**
 * Get the authenticated user's payment history
 */
public function myPayments()
{
    $userId = auth()->id();

    // ONGEZA .with(['course', 'cohort']) HAPA CHINI:
    $payments = Payment::with(['course', 'cohort'])
                ->where('user_id', $userId)
                ->orderBy('created_at', 'desc')
                ->get();

    return response()->json($payments);
}
/**
 * Admin: View all payments from all users
 */
public function allPayments()
{
    // Tunavuta malipo yote na taarifa za User, Course, na Cohort
    $payments = Payment::with(['user', 'course', 'cohort'])
                ->orderBy('created_at', 'desc')
                ->get();

    return response()->json($payments);
}

/**
 * Admin: Delete a payment record
 */
public function deletePayment($id)
{
    try {
        $payment = Payment::findOrFail($id);
        $payment->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Payment record deleted successfully'
        ], 200);
    } catch (\Exception $e) {
        return response()->json([
            'status' => 'error',
            'message' => 'Failed to delete record'
        ], 500);
    }
}

/**
 * Provider: View payments for their own courses only
 */
public function providerPayments()
{
    $providerId = auth()->id();

    // Tunapata malipo yote ambapo kozi husika inamilikiwa na huyu Provider
   $payments = Payment::with(['user', 'course', 'cohort'])
        ->whereHas('course', function ($q) use ($providerId) {
            $q->where('provider_id', $providerId);
        })
        ->orderBy('created_at', 'desc')
        ->get();

    return response()->json($payments);
}

/**
 * Provider: Update payment status manually (button click)
 */
public function providerUpdatePaymentStatus(Request $request, $id)
{
    // 1. Validation ya kile kinachotoka React
    $request->validate([
        'status' => 'required|string|in:PENDING,COMPLETED,PAID,FAILED,REFUNDED,CANCELLED,SUCCESS'
    ]);

    try {
        // 2. Tafuta Payment pekee (Table: payments)
        // Tunatumia 'with:course' ili tuweze kufanya security check ya provider_id
        $payment = \App\Models\Payment::with('course')->findOrFail($id);

        // 3. Security: Je, hii payment inamilikiwa na kozi ya huyu provider?
        if (!$payment->course || $payment->course->provider_id != auth()->id()) {
            return response()->json(['message' => 'Unauthorized access'], 403);
        }

        // 4. Update Status ya Payment pekee
        $payment->status = strtoupper($request->input('status'));
        $payment->save();

        // 5. Rudisha majibu (Hapa hatusomi wala kuandika kwenye enrollment table)
        return response()->json([
            'status' => 'success',
            'payment' => $payment
        ]);

    } catch (\Exception $e) {
        // Hapa ndipo utaona error kama bado ipo
        \Illuminate\Support\Facades\Log::error('Payment Update Error: ' . $e->getMessage());
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage() 
        ], 500);
    }
}
/**
 * Provider: View all student enrollments for their courses
 */
/**
 * Provider: View all student enrollments for their courses
 */

/**
 * Provider: View enrollments for a specific course
 */
public function providerCourseEnrollments($courseId)
{
    try {
        $providerId = auth()->id();

        // Tunavuta malipo moja kwa moja kutoka table ya Payments
        $enrollments = \App\Models\Payment::with(['course', 'cohort','user'])
            ->where('course_id', $courseId)
            ->whereHas('course', function ($q) use ($providerId) {
                $q->where('provider_id', $providerId);
            })
            ->latest()
            ->get();

        return response()->json([
            'course' => \App\Models\Course::find($courseId),
            'enrollments' => $enrollments
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

/**
 * Provider: View ALL student enrollments for ALL their courses
 */
public function allProviderEnrollments()
{
    try {
        $providerId = auth()->id();

        // JARIBIO A: Je, kuna malipo yoyote yaliyo "COMPLETED" kwenye table?
        $countAllPaid = \App\Models\Payment::whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])->count();

        // JARIBIO B: Je, huyu Provider ana kozi zozote?
        $providerCourses = \App\Models\Course::where('provider_id', $providerId)->pluck('id');

        // JARIBIO C: Query ya mwisho ikiwa imeboreshwa
        $enrollments = \App\Models\Payment::with(['course', 'cohort', 'user'])
            ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])
            ->whereIn('course_id', $providerCourses) // Tunatumia ID moja kwa moja badala ya whereHas
            ->latest()
            ->get();

        return response()->json([
            'status' => 'success',
            'debug' => [
                'current_provider_id' => $providerId,
                'total_paid_in_system' => $countAllPaid,
                'courses_found_for_provider' => $providerCourses,
            ],
            'enrollments' => $enrollments
        ]);
    } catch (\Exception $e) {
        return response()->json(['error' => $e.getMessage()], 500);
    }
}

public function allProviderCohorts()
{
    try {
        $providerId = auth()->id();

        if (!$providerId) {
            return response()->json(['error' => 'User not authenticated'], 401);
        }

        // Tunatumia Query Builder moja kwa moja (DB) ili kuepuka Eloquent issues
        $cohorts = \DB::table('cohorts')
            ->join('courses', 'cohorts.course_id', '=', 'courses.id')
            ->where('courses.provider_id', $providerId)
            ->select(
                'cohorts.*', 
                'courses.title as course_title'
            )
            ->orderBy('cohorts.created_at', 'desc')
            ->get();

        // Ongeza idadi ya wanafunzi kwa kila cohort
        foreach ($cohorts as $cohort) {
            $cohort->students_count = \DB::table('payments')
                ->where('cohort_id', $cohort->id)
                ->whereIn('status', ['PAID', 'COMPLETED', 'SUCCESS'])
                ->count();
        }

        return response()->json([
            'status' => 'success',
            'cohorts' => $cohorts
        ]);

    } catch (\Exception $e) {
        // Hapa sasa tutaona kosa halisi kama likitokea
        return response()->json([
            'status' => 'error',
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ], 500);
    }
}

 public function getAllCohorts()
{
    $cohorts = \DB::table('cohorts')
        ->join('courses', 'cohorts.course_id', '=', 'courses.id') // Join the courses table
        ->select(
            'cohorts.*', 
            'courses.title as course_title' // Alias the title so it's easy to find
        )
        ->get();

    return response()->json(['cohorts' => $cohorts]);
}

    public function getCohortStudents($cohortId)
{
    try {
        $students = \DB::table('payments')
            // BADILISHA HAPA: Tumia leftJoin badala ya join
            ->leftJoin('users', 'payments.user_id', '=', 'users.id') 
            ->where('payments.cohort_id', $cohortId)
            ->select(
                'payments.id',
                'payments.amount',
                'users.name',         // Kama jina halipo kwenye users table, litaonekana null
                'users.email',
                'users.phone_number',
                'users.organization',
                'users.position',
                'users.street',
                'users.region',
                'users.city'
            )
            ->get();

        return response()->json([
            'success' => true,
            'students' => $students
        ], 200);

    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'message' => $e->getMessage()
        ], 500);
    }
}
}










