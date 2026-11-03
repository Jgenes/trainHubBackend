<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Payment; 
use App\Support\CatalogueCourses;
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

            // 3. Transform data (Hapa ndipo palikuwa na error)
            $courses->transform(function ($course) use ($userId) {
                try {
                    $course->is_enrolled = $this->hasPaidEnrollment($userId, $course->id);
                } catch (\Exception $e) {
                    Log::error("Payment Check Error: " . $e->getMessage());
                    $course->is_enrolled = false;
                }

                // Hesabu za viti vya cohort
                if ($course->cohorts) {
                    foreach ($course->cohorts as $cohort) {
                        $capacity = (int)($cohort->capacity ?? 0);
                        $taken = (int)($cohort->seats_taken ?? 0);
                        $cohort->remaining_seats = max(0, $capacity - $taken);
                    }
                }
                
                return $course;
            }); // Mabano yalifungwa hapa!

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

            $course->is_enrolled = $this->hasPaidEnrollment(
                Auth::guard('sanctum')->id(),
                $course->id
            );

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

    private function hasPaidEnrollment($userId, $courseId): bool
    {
        return $userId !== null && Payment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])
            ->exists();
    }
}