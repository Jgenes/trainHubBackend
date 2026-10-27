<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use App\Models\Course;
use App\Models\LearningTool;
use App\Models\Note;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/** Notes, announcements and learning tools attached to a course. */
class CourseExtrasController extends Controller
{
    public function notes($courseId)
    {
        $this->visibleCourse($courseId);

        return response()->json(
            Note::where('course_id', $courseId)->latest()->get()
        );
    }

    public function announcements($courseId)
    {
        $this->visibleCourse($courseId);

        return response()->json(
            Announcement::where('course_id', $courseId)->latest()->get()
        );
    }

    public function tools($courseId)
    {
        $this->visibleCourse($courseId);

        return response()->json(
            LearningTool::where('course_id', $courseId)->latest()->get()
        );
    }

    /** One multipart request that may carry a note, an announcement and a tool. */
    public function submitAll(Request $request, $courseId)
    {
        $course = $this->ownedCourse($courseId);

        $data = $request->validate([
            'note_title'         => ['nullable', 'string', 'max:255'],
            'note_files'         => ['nullable', 'array'],
            'note_files.*'       => ['file', 'max:10240'],
            'announcement_title' => ['nullable', 'string', 'max:255'],
            'announcement_body'  => ['nullable', 'string'],
            'announcement_files' => ['nullable', 'array'],
            'announcement_files.*' => ['file', 'max:10240'],
            'tool_name'          => ['nullable', 'string', 'max:255'],
            'tool_link'          => ['nullable', 'string', 'max:2048'],
            'tool_description'   => ['nullable', 'string'],
        ]);

        foreach ((array) $request->file('note_files', []) as $file) {
            if ($file instanceof UploadedFile) {
                Note::create([
                    'course_id' => $course->id,
                    'title'     => $data['note_title'] ?? $file->getClientOriginalName(),
                    'file_path' => $this->storeUpload($file, 'courses/docs'),
                ]);
            }
        }

        if (! empty($data['announcement_title'])) {
            $files = $request->file('announcement_files', []);
            $first = is_array($files) ? reset($files) : null;

            Announcement::create([
                'course_id' => $course->id,
                'title'     => $data['announcement_title'],
                'content'   => $data['announcement_body'] ?? '',
                'file_path' => $first instanceof UploadedFile ? $this->storeUpload($first, 'courses/docs') : null,
            ]);
        }

        if (! empty($data['tool_name'])) {
            LearningTool::create([
                'course_id'   => $course->id,
                'title'       => $data['tool_name'],
                'link'        => $data['tool_link'] ?? null,
                'description' => $data['tool_description'] ?? null,
            ]);
        }

        return response()->json(['message' => 'Course extras saved successfully.'], 201);
    }

    private function visibleCourse($courseId): Course
    {
        return Course::findOrFail($courseId);
    }

    private function ownedCourse($courseId): Course
    {
        return Course::where('id', $courseId)
            ->where('provider_id', Auth::id())
            ->firstOrFail();
    }

    private function storeUpload(UploadedFile $file, string $directory): string
    {
        $target = public_path('uploads/'.$directory);
        File::ensureDirectoryExists($target);

        $extension = $file->getClientOriginalExtension() ?: 'bin';
        $name = Str::random(24).'.'.$extension;
        $file->move($target, $name);

        return 'uploads/'.$directory.'/'.$name;
    }
}
