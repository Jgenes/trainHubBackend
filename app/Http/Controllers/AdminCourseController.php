<?php

namespace App\Http\Controllers;

use App\Mail\CourseApproved;
use App\Mail\CourseDrafted;
use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Cohort;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Exception;

class AdminCourseController extends Controller
{
    public function index()
    {
        try {
            $courses = Course::with(['provider.user', 'cohorts'])->get();
            return response()->json($courses, 200);
        } catch (Exception $e) {
            Log::error("Retrieve Courses Error: " . $e->getMessage());
            return response()->json(['error' => 'Failed to retrieve courses'], 500);
        }
    }

    public function updateStatus(Request $request, $id)
    {
        try {
            $status = $request->input('status');
            $reason = $request->input('reason'); // POKEA SABABU KUTOKA FRONTEND

            if (!in_array($status, ['PUBLISHED', 'DRAFT'])) {
                return response()->json(['error' => 'Invalid status'], 422);
            }

            $course = Course::with(['provider.user'])->findOrFail($id);
            $course->status = $status;
            
            // Ukipenda kusave sababu kwenye database pia, unaweza kuongeza:
            // $course->rejection_reason = $reason; 
            
            $course->save();

            // Pata email (User email kwanza, kisha Provider email kama backup)
            $email = $course->provider?->user?->email ?? $course->provider?->email;

            if ($email) {
                try {
                    if ($status === 'PUBLISHED') {
                        Mail::to($email)->send(new CourseApproved($course));
                        Log::info("Approval email sent to {$email}");
                    } 
                    elseif ($status === 'DRAFT') {
                        // TUNATUMA EMAIL NA SABABU (REASON)
                        Mail::to($email)->send(new CourseDrafted($course, $reason));
                        Log::info("Draft notification with reason sent to {$email}");
                    }
                } catch (Exception $mailEx) {
                    Log::error("Mail failed for Course ID {$id}: " . $mailEx->getMessage());
                }
            }

            return response()->json([
                'message' => "Course status updated to {$status}",
                'course_id' => $course->id
            ]);

        } catch (Exception $e) {
            Log::error("Status Update Error: " . $e->getMessage());
            return response()->json(['error' => 'Server Error'], 500);
        }
    }
}