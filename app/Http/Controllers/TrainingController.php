<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Enrollment; 
use App\Models\Cohort;
use App\Models\Payment; 
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TrainingController extends Controller
{
    public function index(Request $request)
    {
        try {
            // 1. Pata ID ya user kwa usalama
            $userId = Auth::guard('sanctum')->id();

            // 2. Query kozi ambazo ni Published pekee
            $query = Course::where('status', 'Published')
                ->with([
                    'provider:id,name', 
                    'cohorts'
                ]);

            if ($request->filled('name')) {
                $query->where('title', 'like', '%' . $request->name . '%');
            }

            $courses = $query->get();

            // 3. Transform data (Hapa ndipo palikuwa na error)
            $courses->transform(function ($course) use ($userId) {
                $course->is_enrolled = false;

                // Check kama user amelipia/amejisajili
                if ($userId) {
                    try {
                        $course->is_enrolled = Payment::where('user_id', $userId)
                            ->where('course_id', $course->id)
                            ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS']) 
                            ->exists();
                    } catch (\Exception $e) {
                        Log::error("Payment Check Error: " . $e->getMessage());
                        $course->is_enrolled = false; 
                    }
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
            $course = Course::with([
                'provider:id,name',
                'cohorts'
            ])->where('status', 'Published')->find($id);

            if (!$course) {
                return response()->json(['message' => 'Course not found'], 404);
            }

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
}