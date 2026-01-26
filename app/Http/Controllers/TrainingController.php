<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Cohort;
class TrainingController extends Controller
{
    /**
     * Display a listing of courses with their cohorts.
     */
    public function index(Request $request)
    {
        // Fetch only Published courses with their OPEN cohorts
        $courses = Course::where('status', 'Published')
            ->with([
                'provider:id,name', // Only id and name from provider
                'cohorts' => function ($query) {
                    $query->where('status', 'OPEN')
                        ->whereDate('registration_deadline', '>=', now())
                        ->select(
                            'id',
                            'course_id',
                            'intake_name',
                            'start_date',
                            'capacity',
                            'seats_taken',
                            'price',
                            'status'
                        );
                }
            ])
            ->get();

        // Dynamically calculate remaining seats for each cohort
        $courses->each(function ($course) {
            $course->cohorts->each(function ($cohort) {
                $cohort->remaining_seats = $cohort->capacity - $cohort->seats_taken;
            });
        });

        return response()->json($courses);
    }

   

public function show($id)
{
    // Fetch course with provider and cohorts
    $course = Course::with([
        'provider:id,name',
        'cohorts' => function ($query) {
            $query->where('status', 'OPEN')
                  ->whereDate('registration_deadline', '>=', now())
                  ->select(
                      'id',
                      'course_id',
                      'intake_name',
                      'start_date',
                      'capacity',
                      'seats_taken',
                      'registration_deadline',
                      'schedule_text'
                  );
        }
    ])->where('status', 'Published')->find($id);

    if (!$course) {
        return response()->json(['message' => 'Course not found'], 404);
    }

    // Decode JSON contents from the course table
    if ($course->contents) {
        if (is_string($course->contents)) {
            $decoded = json_decode($course->contents, true);
            $course->contents = $decoded ?: [];
        }
    } else {
        $course->contents = [];
    }

    // Calculate remaining seats for cohorts
    $course->cohorts->each(function ($cohort) {
        $cohort->remaining_seats = $cohort->capacity - $cohort->seats_taken;
        $cohort->enrolled = $cohort->seats_taken;
    });

    return response()->json($course);
}



}
