<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\LessonCompletion;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LearningController extends Controller
{
    /** Courses the signed-in learner has paid for. */
    public function myCourses()
    {
        $payments = Payment::where('user_id', Auth::id())
            ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])
            ->with(['course', 'cohort'])
            ->latest()
            ->get();

        return response()->json($payments);
    }

    /** Curriculum for a paid course, including per-lesson progress. */
    public function learningContent($id)
    {
        $userId = Auth::id();

        $paid = Payment::where('user_id', $userId)
            ->where('course_id', $id)
            ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])
            ->exists();

        if (! $paid) {
            return response()->json(['message' => 'Access denied. Please pay first.'], 403);
        }

        $course = Course::findOrFail($id);

        $completed = LessonCompletion::where('user_id', $userId)
            ->where('course_id', $course->id)
            ->pluck('lesson_id')
            ->map(fn ($v) => (string) $v)
            ->all();

        $modules = collect($course->contents ?? [])->map(function ($item, $index) use ($completed) {
            $id = (string) ($item['id'] ?? ($index + 1));
            $video = $item['video'] ?? null;

            return [
                'id'          => $id,
                'title'       => $item['title'] ?? 'Untitled Lesson',
                'description' => $item['description'] ?? '',
                'notes'       => $item['notes_text'] ?? '',
                'video_url'   => $video ? $this->assetUrl($video) : null,
                'links'       => $item['video_links'] ?? [],
                'handouts'    => collect($item['handouts'] ?? [])
                    ->filter(fn ($h) => is_string($h))
                    ->map(fn ($h) => $this->assetUrl($h))
                    ->values()
                    ->all(),
                'completed'   => in_array($id, $completed, true),
            ];
        })->values();

        $total = $modules->count();
        $done  = $modules->where('completed', true)->count();

        return response()->json([
            'id'                  => $course->id,
            'title'               => $course->title,
            'short_description'   => $course->short_description,
            'modules'             => $modules,
            'progress_percentage' => $total > 0 ? (int) round($done / $total * 100) : 0,
        ]);
    }

    /** POST /api/lessons/{id}/complete */
    public function completeLesson(Request $request, $id)
    {
        $courseId = $request->input('course_id');

        if (! $courseId) {
            $courseId = Payment::where('user_id', Auth::id())
                ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])
                ->latest()
                ->value('course_id');
        }

        return $this->markComplete($courseId, $request->input('lesson_id', $id));
    }

    /** POST /api/courses/{courseId}/complete-lesson */
    public function completeCourseLesson(Request $request, $courseId)
    {
        return $this->markComplete($courseId, $request->input('lesson_id', $request->input('lesson')));
    }

    private function markComplete($courseId, $lessonId)
    {
        if (! $courseId || $lessonId === null || $lessonId === '') {
            return response()->json(['message' => 'course_id and lesson_id are required.'], 422);
        }

        $paid = Payment::where('user_id', Auth::id())
            ->where('course_id', $courseId)
            ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])
            ->exists();

        if (! $paid) {
            return response()->json(['message' => 'Access denied. Please pay first.'], 403);
        }

        LessonCompletion::firstOrCreate([
            'user_id'   => Auth::id(),
            'course_id' => $courseId,
            'lesson_id' => (string) $lessonId,
        ]);

        $course  = Course::findOrFail($courseId);
        $total   = count($course->contents ?? []);
        $done    = LessonCompletion::where('user_id', Auth::id())
            ->where('course_id', $courseId)
            ->count();

        return response()->json([
            'success'             => true,
            'progress_percentage' => $total > 0 ? (int) round(min($done, $total) / $total * 100) : 100,
        ]);
    }

    private function assetUrl(string $path): string
    {
        return str_starts_with($path, 'uploads/')
            ? asset($path)
            : asset('storage/'.ltrim($path, '/'));
    }
}
