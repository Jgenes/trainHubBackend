<?php

namespace App\Http\Controllers;

use App\Mail\CourseApproved;
use App\Mail\CourseDrafted;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AdminCourseController extends Controller
{
    public function index()
    {
        $courses = Course::with(['provider:id,name,email', 'cohorts'])->latest()->get();

        return response()->json($courses);
    }

    public function updateStatus(Request $request, $id)
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:2000'],
        ]);

        $status = Course::normalizeStatus($data['status']);

        // The admin UI sends PUBLISHED / DRAFT. Reject anything else explicitly.
        if (! in_array(strtoupper(trim($data['status'])), ['PUBLISHED', 'DRAFT', 'PUBLISH', 'CLOSED', 'SUSPENDED'], true)) {
            return response()->json(['error' => 'Invalid status'], 422);
        }

        $course = Course::with('provider')->findOrFail($id);
        $course->update(['status' => $status]);

        $this->notifyProvider($course, $status, $data['reason'] ?? null);

        return response()->json([
            'message'   => "Course status updated to {$status}",
            'course_id' => $course->id,
            'status'    => $status,
        ]);
    }

    public function approveCourse($id)
    {
        $course = Course::with('provider')->findOrFail($id);
        $course->update(['status' => 'Published']);

        $this->notifyProvider($course, 'Published', null);

        return response()->json([
            'message'   => 'Course approved successfully.',
            'course_id' => $course->id,
        ]);
    }

    private function notifyProvider(Course $course, string $status, ?string $reason): void
    {
        $email = $course->provider?->email;

        if (! $email) {
            return;
        }

        try {
            if ($status === 'Published') {
                Mail::to($email)->send(new CourseApproved($course));
            } else {
                Mail::to($email)->send(new CourseDrafted($course, $reason));
            }
        } catch (\Throwable $e) {
            Log::error("Course notification mail failed for course {$course->id}: ".$e->getMessage());
        }
    }
}
