<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Enrollment; 
use App\Models\Cohort;
use Illuminate\Support\Facades\Auth; // HII ILIKUWA IMEKOSEKANA!

class TrainingController extends Controller
{
    public function index(Request $request)
{
    try {
        $userId = Auth::guard('sanctum')->id();

        $query = Course::where('status', 'Published')
            ->with([
                'provider:id,name,logo', 
                'cohorts'
            ]);

        if ($request->filled('name')) {
            $query->where('title', 'like', '%' . $request->name . '%');
        }

        $courses = $query->get();

        $courses->transform(function ($course) use ($userId) {
            $course->is_enrolled = false;

            if ($userId) {
                // REKEBISHO HAPA: Angalia table ya Payment badala ya Enrollment
                // Na hakikisha status inalingana na inavyookolewa (PAID au COMPLETED)
                $course->is_enrolled = \App\Models\Payment::where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->whereIn('status', ['COMPLETED', 'PAID']) 
                    ->exists();
            }

            if($course->cohorts) {
                $course->cohorts->each(function ($cohort) {
                    $cohort->remaining_seats = max(0, $cohort->capacity - $cohort->seats_taken);
                });
            }
            return $course;
        });

        return response()->json($courses);

    } catch (\Exception $e) {
        return response()->json(['error' => $e->getMessage()], 500);
    }
}

    public function show($id)
    {
        $course = Course::with([
            'provider:id,name',
            'cohorts' // Hapa pia tumeondoa filter ili kadi isipotee
        ])->where('status', 'Published')->find($id);

        if (!$course) {
            return response()->json(['message' => 'Course not found'], 404);
        }

        // Handle JSON contents
        if (is_string($course->contents)) {
            $course->contents = json_decode($course->contents, true) ?: [];
        }

        $course->cohorts->each(function ($cohort) {
            $cohort->remaining_seats = max(0, $cohort->capacity - $cohort->seats_taken);
            $cohort->enrolled = $cohort->seats_taken;
        });

        return response()->json($course);
    }
}