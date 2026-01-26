<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cohort;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;

class CohortController extends Controller
{
    // List cohorts for a course (PUBLIC - anyone can view)
 public function index($courseId)
{
    $cohorts = Cohort::with('course')
        ->where('course_id', $courseId)
        ->get()
        ->map(function($cohort) {
            return [
                'id' => $cohort->id,
                'intake_name' => $cohort->intake_name,
                'start_date' => $cohort->start_date,
                'end_date' => $cohort->end_date,
                'schedule_text' => $cohort->schedule_text,
                'venue' => $cohort->venue,
                'online_link' => $cohort->online_link,
                'capacity' => $cohort->capacity,
                'seats_taken' => $cohort->seats_taken,
                'price' => $cohort->price, // <<< important
                'registration_deadline' => $cohort->registration_deadline,
                'status' => $cohort->status,
                'course' => $cohort->course, // includes course info
            ];
        });

    return response()->json([
        'status' => 'success',
        'data' => $cohorts
    ]);
}

    // Create new cohort
    public function store(Request $request, $courseId)
    {
        $userId = Auth::id();

        // Debug: Check authentication
        if (!$userId) {
            return response()->json(['error' => 'Not authenticated'], 401);
        }

        $course = Course::where('id', $courseId)
                        ->where('provider_id', $userId)
                        ->first();

        if (!$course) {
            return response()->json([
                'error' => 'Course not found or you do not own this course',
                'course_id' => $courseId,
                'user_id' => $userId
            ], 404);
        }

        $data = $request->validate([
            'intake_name'              => 'required|string|max:255',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after:start_date',
            'schedule_text'            => 'nullable|string',
            'venue'                    => 'nullable|string',
            'online_link'              => 'nullable|string',
            'capacity'                 => 'required|integer|min:1',
            'price'                    => 'required|integer|min:0',
            'registration_deadline'    => 'required|date',
            'status'                   => 'required|in:OPEN,FULL,CLOSED',
        ]);

        $cohort = Cohort::create([
            'course_id'              => $course->id,
            'provider_id'            => $userId,
            'intake_name'            => $data['intake_name'],
            'start_date'             => $data['start_date'],
            'end_date'               => $data['end_date'],
            'schedule_text'          => $data['schedule_text'] ?? null,
            'venue'                  => $data['venue'] ?? null,
            'online_link'            => $data['online_link'] ?? null,
            'capacity'               => $data['capacity'],
            'seats_taken'            => 0,
            'price'                  => $data['price'],
            'registration_deadline'  => $data['registration_deadline'],
            'status'                 => $data['status'],
        ]);

        return response()->json($cohort, 201);
    }

    // Show single cohort
    public function show($courseId, $cohortId)
    {
        $cohort = Cohort::where('id', $cohortId)
            ->where('course_id', $courseId)
            ->with(['course', 'enrollments'])
            ->firstOrFail();

        return response()->json($cohort);
    }

    // Update cohort
    public function update(Request $request, $courseId, $cohortId)
    {
        $userId = Auth::id();

        $cohort = Cohort::where('id', $cohortId)
            ->where('course_id', $courseId)
            ->where('provider_id', $userId)
            ->firstOrFail();

        $data = $request->validate([
            'intake_name'              => 'sometimes|string|max:255',
            'start_date'               => 'sometimes|date',
            'end_date'                 => 'sometimes|date',
            'schedule_text'            => 'nullable|string',
            'venue'                    => 'nullable|string',
            'online_link'              => 'nullable|string',
            'capacity'                 => 'sometimes|integer|min:1',
            'price'                    => 'sometimes|integer|min:0',
            'registration_deadline'    => 'sometimes|date',
            'status'                   => 'sometimes|in:OPEN,FULL,CLOSED'
        ]);

        $cohort->update($data);

        return response()->json($cohort);
    }

    // Delete cohort
    public function destroy($courseId, $cohortId)
    {
        $userId = Auth::id();

        $cohort = Cohort::where('id', $cohortId)
            ->where('course_id', $courseId)
            ->where('provider_id', $userId)
            ->firstOrFail();

        $cohort->delete();

        return response()->json(['message' => 'Cohort deleted'], 200);
    }
}
