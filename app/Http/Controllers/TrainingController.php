<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Support\CatalogueCourses;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        try {
            // 1. Pata ID ya user kwa usalama
            $userId = Auth::guard('sanctum')->id();

            // 2. Kozi zinazoruhusiwa kwenye catalogue ya umma. Ikiwa hakuna
            //    hata moja inayolingana, CatalogueCourses hurejesha kozi
            //    zilizopo badala ya orodha tupu.
            $courses = CatalogueCourses::get(
                $request->filled('name') ? (string) $request->name : null
            );

            // 3. Malipo ya user huyu, query moja kwa kozi zote.
            $enrolments = $this->enrolmentMap($userId);

            // 4. Transform data
            $courses->transform(function ($course) use ($enrolments) {
                $entry = $enrolments->get($course->id);

                // is_enrolled ni true TU ikiwa PesaPal imethibitisha malipo.
                $course->is_enrolled = (bool) ($entry['is_enrolled'] ?? false);
                $course->enrolment_status = $entry['status'] ?? null;

                // Hesabu za viti vya cohort
                if ($course->cohorts) {
                    foreach ($course->cohorts as $cohort) {
                        $capacity = (int)($cohort->capacity ?? 0);
                        $taken = (int)($cohort->seats_taken ?? 0);
                        $cohort->remaining_seats = max(0, $capacity - $taken);
                    }
                }

                return $course;
            });

            return response()->json($courses);

        } catch (\Exception $e) {
            Log::error("Training Controller Index Error: " . $e->getMessage());
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ], 500);
        }
    }

    public function show($id)
    {
        try {
            $course = CatalogueCourses::find($id);

            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }

            $entry = $this->enrolmentMap(Auth::guard('sanctum')->id())->get($course->id);

            $course->is_enrolled = (bool) ($entry['is_enrolled'] ?? false);
            $course->enrolment_status = $entry['status'] ?? null;

            // Handle JSON contents
            if (is_string($course->contents)) {
                $course->contents = json_decode($course->contents, true) ?: [];
            }

            if ($course->cohorts) {
                $course->cohorts->each(function ($cohort) {
                    $capacity = (int)($cohort->capacity ?? 0);
                    $taken = (int)($cohort->seats_taken ?? 0);
                    $cohort->remaining_seats = max(0, $capacity - $taken);
                    $cohort->enrolled = $taken;
                });
            }

            return response()->json($course);
        } catch (\Exception $e) {
            Log::error("Training Controller Show Error: " . $e->getMessage());
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Payment status per course for one learner, keyed by course id.
     *
     * is_enrolled comes from Payment::isVerifiedPaid(): a paid status AND the
     * gateway's confirmation timestamp. A PENDING order — or any row that was
     * written as COMPLETED without PesaPal confirming it — stays false, so the
     * catalogue keeps showing "Enrol now → checkout".
     *
     * @return Collection<int|string, array{is_enrolled: bool, status: string|null}>
     */
    private function enrolmentMap(int|string|null $userId): Collection
    {
        if ($userId === null || $userId === '') {
            return collect();
        }

        return Payment::where('user_id', $userId)
            ->orderBy('id')
            ->get(['id', 'course_id', 'status', 'verified_at'])
            ->groupBy('course_id')
            ->map(fn (Collection $payments) => [
                'is_enrolled' => $payments->contains(fn (Payment $payment) => $payment->isVerifiedPaid()),
                'status'      => strtoupper((string) $payments->last()->status),
            ]);
    }
}
