<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\Question;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    /** Courses belonging to the signed-in provider. */
    public function index()
    {
        return response()->json(
            Course::where('provider_id', Auth::id())->with('cohorts')->latest()->get()
        );
    }

    /** Published courses for the public catalogue. */
    public function publicIndex()
    {
        $courses = Course::published()
            ->with([
                'provider:id,name',
                'cohorts' => fn ($q) => $q->where('status', 'OPEN'),
            ])
            ->withCount('enrollments')
            ->latest()
            ->get();

        return response()->json($courses);
    }

    public function store(Request $request)
    {
        $this->validateCourse($request);

        try {
            return DB::transaction(function () use ($request) {
                $course = Course::create([
                    'provider_id'       => Auth::id(),
                    'title'             => $request->input('title'),
                    'category'          => $request->input('category'),
                    'mode'              => $this->normalizeMode($request->input('mode')),
                    'short_description' => $request->input('short_description', $request->input('shortDescription')),
                    'long_description'  => $request->input('long_description', $request->input('longDescription')),
                    'learning_outcomes' => $this->normalizeList($request->input('learning_outcomes')),
                    'skills'            => $this->normalizeList($request->input('skills')),
                    'requirements'      => $this->normalizeList($request->input('requirements')),
                    'contents'          => $this->buildContents($request),
                    'banner'            => $this->storeBanner($request),
                    'status'            => Course::normalizeStatus($request->input('status', 'Draft')),
                ]);

                return response()->json([
                    'message' => 'Course saved successfully!',
                    'course'  => $course,
                ], 201);
            });
        } catch (\Throwable $e) {
            Log::error('Course creation error: '.$e->getMessage());

            return response()->json([
                'message' => 'Failed to save course',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function show($id)
    {
        return response()->json($this->ownedCourse($id)->load('cohorts'));
    }

    public function update(Request $request, $id)
    {
        $this->validateCourse($request);

        try {
            return DB::transaction(function () use ($request, $id) {
                $course = $this->ownedCourse($id);

                $course->fill([
                    'title'             => $request->input('title', $course->title),
                    'category'          => $request->input('category', $course->category),
                    'mode'              => $this->normalizeMode($request->input('mode', $course->mode)),
                    'short_description' => $request->input('short_description', $request->input('shortDescription', $course->short_description)),
                    'long_description'  => $request->input('long_description', $request->input('longDescription', $course->long_description)),
                    'status'            => $request->has('status')
                        ? Course::normalizeStatus($request->input('status'))
                        : $course->status,
                ]);

                foreach (['learning_outcomes', 'skills', 'requirements'] as $field) {
                    if ($request->has($field)) {
                        $course->{$field} = $this->normalizeList($request->input($field));
                    }
                }

                if ($request->has('contents')) {
                    $course->contents = $this->buildContents($request, $course);
                }

                if ($banner = $this->storeBanner($request)) {
                    if ($course->banner && File::exists(public_path($course->banner))) {
                        File::delete(public_path($course->banner));
                    }
                    $course->banner = $banner;
                }

                $course->save();

                return response()->json([
                    'success' => true,
                    'message' => 'Course updated successfully',
                    'course'  => $course,
                ]);
            });
        } catch (\Throwable $e) {
            Log::error('Course update error: '.$e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to update course',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        $course = $this->ownedCourse($id);
        $course->delete();

        return response()->json(['success' => true, 'message' => 'Course deleted successfully!']);
    }

    // ------------------------------------------------------------------
    // Course community features
    // ------------------------------------------------------------------

    public function addAnnouncement(Request $request, $courseId)
    {
        $course = $this->ownedCourse($courseId);

        $data = $request->validate([
            'title'   => ['required', 'string', 'max:255'],
            'content' => ['required', 'string'],
        ]);

        $announcement = Announcement::create([
            'course_id' => $course->id,
            'title'     => $data['title'],
            'content'   => $data['content'],
        ]);

        return response()->json($announcement, 201);
    }

    public function askQuestion(Request $request, $courseId)
    {
        $course = Course::findOrFail($courseId);

        $data = $request->validate([
            'question_text' => ['required_without:question', 'string'],
            'question'      => ['required_without:question_text', 'string'],
        ]);

        $question = Question::create([
            'course_id'     => $course->id,
            'user_id'       => Auth::id(),
            'question_text' => $data['question_text'] ?? $data['question'],
        ]);

        return response()->json($question, 201);
    }

    public function answerQuestion(Request $request, $id)
    {
        $question = Question::with('course')->findOrFail($id);

        if ((int) $question->course?->provider_id !== (int) Auth::id()) {
            return response()->json(['message' => 'You do not own this course.'], 403);
        }

        $data = $request->validate([
            'answer_text' => ['required_without:answer', 'string'],
            'answer'      => ['required_without:answer_text', 'string'],
        ]);

        $question->update(['answer_text' => $data['answer_text'] ?? $data['answer']]);

        return response()->json($question);
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function validateCourse(Request $request): void
    {
        $request->validate([
            'title'    => ['required', 'string', 'max:255'],
            'category' => ['required', 'string', 'max:255'],
            'mode'     => ['nullable', 'in:Online,Physical,Hybrid'],
            'banner'   => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:5120'],
        ]);
    }

    private function normalizeMode($mode): string
    {
        return in_array($mode, ['Online', 'Physical', 'Hybrid'], true) ? $mode : 'Online';
    }

    private function ownedCourse($id): Course
    {
        return Course::where('id', $id)
            ->where('provider_id', Auth::id())
            ->firstOrFail();
    }

    /** Accept an array, a JSON string or newline-separated text. */
    private function normalizeList($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return array_values(array_filter($decoded, 'is_scalar'));
            }

            return array_values(array_filter(array_map('trim', preg_split('/\r\n|\r|\n/', $value)), 'strlen'));
        }

        if (is_array($value)) {
            return array_values(array_filter($value, fn ($v) => is_scalar($v) && $v !== ''));
        }

        return [];
    }

    /** Build the JSON curriculum, persisting any uploaded videos/handouts. */
    private function buildContents(Request $request, ?Course $course = null): array
    {
        $raw = $request->input('contents');

        if (is_string($raw)) {
            $decoded = json_decode($raw, true);
            $raw = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($raw)) {
            return $course?->contents ?? [];
        }

        $previous = $course?->contents ?? [];
        $result   = [];

        foreach ($raw as $i => $section) {
            if (! is_array($section)) {
                continue;
            }

            $video = $section['video'] ?? ($previous[$i]['video'] ?? null);
            if (! is_string($video)) {
                $video = null;
            }

            $handouts = array_values(array_filter(
                (array) ($section['handouts'] ?? ($previous[$i]['handouts'] ?? [])),
                'is_string'
            ));

            if ($request->hasFile("contents.$i.video")) {
                $video = $this->storeUpload($request->file("contents.$i.video"), 'courses/videos');
            }

            if ($request->hasFile("contents.$i.handouts")) {
                $handouts = [];
                foreach ((array) $request->file("contents.$i.handouts") as $file) {
                    if ($file instanceof UploadedFile) {
                        $handouts[] = $this->storeUpload($file, 'courses/docs');
                    }
                }
            }

            $result[] = [
                'title'       => (string) ($section['title'] ?? ''),
                'description' => (string) ($section['description'] ?? ''),
                'notes_text'  => (string) ($section['notes_text'] ?? ''),
                'video'       => $video,
                'video_links' => array_values(array_filter((array) ($section['video_links'] ?? []), 'is_string')),
                'handouts'    => $handouts,
            ];
        }

        return $result;
    }

    private function storeBanner(Request $request): ?string
    {
        if (! $request->hasFile('banner')) {
            return null;
        }

        return $this->storeUpload($request->file('banner'), 'banners');
    }

    private function storeUpload(UploadedFile $file, string $directory): string
    {
        $target = public_path('uploads/'.$directory);
        File::ensureDirectoryExists($target);

        $extension = $file->getClientOriginalExtension() ?: $file->guessExtension() ?: 'bin';
        $name = Str::random(24).'.'.$extension;
        $file->move($target, $name);

        return 'uploads/'.$directory.'/'.$name;
    }
}
