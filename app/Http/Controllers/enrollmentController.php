<?php
namespace App\Http\Controllers;

use App\Models\Enrollment;
use App\Models\Cohort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;

class EnrollmentController extends Controller
{
    // Start checkout - create PENDING enrollment
    public function startCheckout(Request $request)
    {
        $studentId = Auth::id(); // logged-in student
        $data = $request->validate([
            'course_id' => 'required|exists:courses,id',
            'cohort_id' => 'required|exists:cohorts,id'
        ]);

        $cohort = Cohort::where('id', $data['cohort_id'])
            ->where('course_id', $data['course_id'])
            ->firstOrFail();

        // Check seats availability
        $enrolledCount = Enrollment::where('cohort_id', $cohort->id)
            ->where('status', 'CONFIRMED')
            ->count();

        if ($enrolledCount >= $cohort->capacity) {
            return response()->json(['message' => 'Cohort is full'], 400);
        }

        // Create PENDING enrollment
        $enrollment = Enrollment::create([
            'student_id' => $studentId,
            'provider_id' => $cohort->provider_id,
            'course_id' => $cohort->course_id,
            'cohort_id' => $cohort->id,
            'status' => 'PENDING'
        ]);

        return response()->json([
            'message' => 'Enrollment started',
            'enrollment' => $enrollment,
            'checkout_summary' => [
                'provider_name' => $cohort->provider->legal_name,
                'course_title' => $cohort->course->title,
                'cohort_name' => $cohort->intake_name,
                'dates' => $cohort->start_date . ' to ' . $cohort->end_date,
                'venue' => $cohort->venue,
                'mode' => $cohort->course->mode,
                'seats_remaining' => $cohort->capacity - $enrolledCount,
                'price' => $cohort->price
            ]
        ]);
    }

    // Confirm payment
    public function confirmPayment(Request $request, $enrollmentId)
    {
        $enrollment = Enrollment::where('id', $enrollmentId)
            ->where('student_id', Auth::id())
            ->firstOrFail();

        if ($enrollment->status !== 'PENDING') {
            return response()->json(['message' => 'Enrollment not in PENDING status'], 400);
        }

        // TODO: integrate with payment gateway
        $paymentSuccess = $request->input('payment_success', true);

        if ($paymentSuccess) {
            $enrollment->status = 'CONFIRMED';
            $enrollment->save();
            return response()->json(['message' => 'Enrollment confirmed', 'enrollment' => $enrollment]);
        } else {
            $enrollment->status = 'CANCELLED';
            $enrollment->save();
            return response()->json(['message' => 'Payment failed, enrollment cancelled', 'enrollment' => $enrollment]);
        }
    }

    // View student enrollments
    public function myEnrollments()
    {
        $studentId = Auth::id();

        $enrollments = Enrollment::where('student_id', $studentId)
            ->with('course', 'cohort', 'provider')
            ->get();

        return response()->json($enrollments);
    }
}
