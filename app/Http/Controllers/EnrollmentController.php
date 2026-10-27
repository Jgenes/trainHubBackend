<?php

namespace App\Http\Controllers;

use App\Mail\PaymentReceiptMail;
use App\Models\Course;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class EnrollmentController extends Controller
{
    /** Internal payment webhook: update a payment and email its invoice/receipt. */
    public function handlePaymentCallback(Request $request)
    {
        $secret = config('services.pesapal.callback_token');
        if ($secret && ! hash_equals((string) $secret, (string) $request->header('X-Callback-Token'))) {
            return response()->json(['message' => 'Invalid callback token.'], 403);
        }

        $data = $request->validate([
            'reference'     => ['required', 'string'],
            'status'        => ['required', 'in:PENDING,COMPLETED,PAID,SUCCESS,FAILED,INVALID,REFUNDED,CANCELLED'],
            'user_name'     => ['nullable', 'string'],
            'payment_method'=> ['nullable', 'string'],
        ]);

        $payment = Payment::with(['course', 'cohort', 'user'])
            ->where('reference', $data['reference'])
            ->first();

        if (! $payment) {
            return response()->json(['message' => 'Payment reference not found.'], 404);
        }

        try {
            $payment->update([
                'status'         => $data['status'],
                'user_name'      => $data['user_name'] ?? $payment->user_name,
                'payment_method' => $data['payment_method'] ?? $payment->payment_method,
                'expires_at'     => $data['status'] === 'PENDING' && ! $payment->expires_at
                    ? Carbon::now()->addDays(7)
                    : $payment->expires_at,
            ]);

            $isPaid    = $payment->isPaid();
            $view      = $isPaid ? 'pdf.receipt' : 'pdf.invoice';
            $mailType  = $isPaid ? 'receipt' : 'invoice';
            $pdf       = Pdf::loadView($view, compact('payment'));

            Mail::to($payment->email)->send(new PaymentReceiptMail($payment, $pdf, $mailType));

            return response()->json([
                'status'  => 'success',
                'message' => "Payment updated to {$data['status']} and {$mailType} sent.",
                'data'    => [
                    'reference' => $payment->reference,
                    'expiry'    => $payment->expires_at,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('Payment callback error: '.$e->getMessage());

            return response()->json([
                'status'  => 'error',
                'message' => 'The payment was updated but the document could not be emailed.',
            ], 500);
        }
    }

    /** Download an invoice or receipt as a PDF. */
    public function downloadDoc($reference)
    {
        $payment = Payment::with(['course', 'cohort', 'user'])
            ->where('reference', $reference)
            ->firstOrFail();

        $isPaid   = $payment->isPaid();
        $view     = $isPaid ? 'pdf.receipt' : 'pdf.invoice';
        $filename = ($isPaid ? 'Receipt-' : 'Invoice-').$reference.'.pdf';

        return Pdf::loadView($view, compact('payment'))->download($filename);
    }

    /** The learner's own enrollments (derived from completed payments). */
    public function myEnrollments()
    {
        $payments = Payment::with(['course', 'cohort'])
            ->where('user_id', Auth::id())
            ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])
            ->latest()
            ->get();

        return response()->json($payments);
    }

    public function myPayments()
    {
        return response()->json(
            Payment::with(['course', 'cohort'])
                ->where('user_id', Auth::id())
                ->latest()
                ->get()
        );
    }

    public function allPayments()
    {
        return response()->json(
            Payment::with(['user', 'course', 'cohort'])->latest()->get()
        );
    }

    public function deletePayment($id)
    {
        $payment = Payment::findOrFail($id);
        $payment->delete();

        return response()->json(['status' => 'success', 'message' => 'Payment record deleted successfully']);
    }

    public function providerPayments()
    {
        $payments = Payment::with(['user', 'course', 'cohort'])
            ->whereHas('course', fn ($q) => $q->where('provider_id', Auth::id()))
            ->latest()
            ->get();

        return response()->json($payments);
    }

    public function providerUpdatePaymentStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => ['required', 'string', 'in:PENDING,COMPLETED,PAID,FAILED,REFUNDED,CANCELLED,SUCCESS'],
        ]);

        try {
            $payment = Payment::with('course')->findOrFail($id);

            if (! $payment->course || (int) $payment->course->provider_id !== (int) Auth::id()) {
                return response()->json(['message' => 'Unauthorized access'], 403);
            }

            $payment->update(['status' => $data['status']]);

            return response()->json(['status' => 'success', 'payment' => $payment]);
        } catch (\Throwable $e) {
            Log::error('Payment update error: '.$e->getMessage());

            return response()->json(['status' => 'error', 'message' => $e->getMessage()], 500);
        }
    }

    public function providerCourseEnrollments($courseId)
    {
        $providerId = Auth::id();

        $enrollments = Payment::with(['course', 'cohort', 'user'])
            ->where('course_id', $courseId)
            ->whereHas('course', fn ($q) => $q->where('provider_id', $providerId))
            ->latest()
            ->get();

        return response()->json([
            'course'      => Course::find($courseId),
            'enrollments' => $enrollments,
        ]);
    }

    public function allProviderEnrollments()
    {
        $courseIds = Course::where('provider_id', Auth::id())->pluck('id');

        $enrollments = Payment::with(['course', 'cohort', 'user'])
            ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])
            ->whereIn('course_id', $courseIds)
            ->latest()
            ->get();

        return response()->json([
            'status'      => 'success',
            'enrollments' => $enrollments,
        ]);
    }

    public function allProviderCohorts()
    {
        $cohorts = DB::table('cohorts')
            ->join('courses', 'cohorts.course_id', '=', 'courses.id')
            ->where('courses.provider_id', Auth::id())
            ->select('cohorts.*', 'courses.title as course_title')
            ->orderByDesc('cohorts.created_at')
            ->get();

        foreach ($cohorts as $cohort) {
            $cohort->students_count = DB::table('payments')
                ->where('cohort_id', $cohort->id)
                ->whereIn('status', ['PAID', 'COMPLETED', 'SUCCESS'])
                ->count();
        }

        return response()->json(['status' => 'success', 'cohorts' => $cohorts]);
    }

    public function getAllCohorts()
    {
        $cohorts = DB::table('cohorts')
            ->join('courses', 'cohorts.course_id', '=', 'courses.id')
            ->select('cohorts.*', 'courses.title as course_title')
            ->get();

        return response()->json(['cohorts' => $cohorts]);
    }

    public function getCohortStudents($cohortId)
    {
        $students = DB::table('payments')
            ->leftJoin('users', 'payments.user_id', '=', 'users.id')
            ->where('payments.cohort_id', $cohortId)
            ->whereIn('payments.status', ['PAID', 'COMPLETED', 'SUCCESS'])
            ->select(
                'payments.id',
                'payments.reference',
                'payments.amount',
                'payments.status',
                'payments.first_name',
                'payments.last_name',
                'payments.email',
                'payments.phone_number',
                'payments.organization',
                'payments.position',
                'payments.street',
                'payments.region',
                'payments.city',
                'payments.postal',
                'users.name as user_name',
                'users.phone as user_phone'
            )
            ->get()
            ->map(function ($student) {
                $student->name = $student->user_name
                    ?: trim(($student->first_name ?? '').' '.($student->last_name ?? ''));

                return $student;
            });

        return response()->json(['success' => true, 'students' => $students]);
    }
}
