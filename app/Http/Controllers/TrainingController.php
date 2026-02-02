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
    $query = Course::where('status', 'Published')
        ->with([
            'provider:id,name,logo', 
            'cohorts' => function ($query) {
                $query->where('status', 'OPEN')
                      // If you are testing and don't see courses, 
                      // check if your registration_deadline is in the past!
                      ->whereDate('registration_deadline', '>=', now()) 
                      ->select(
                          'id', 'course_id', 'intake_name', 'start_date', 
                          'capacity', 'seats_taken', 'price', 'status'
                      );
            }
        ]);

    // Apply basic search if provided via query params
    if ($request->filled('name')) {
        $query->where('title', 'like', '%' . $request->name . '%');
    }

    $courses = $query->get();

    // Map remaining seats and ensure categories exist for frontend filtering
    $courses->each(function ($course) {
        $course->cohorts->each(function ($cohort) {
            $cohort->remaining_seats = max(0, $cohort->capacity - $cohort->seats_taken);
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
