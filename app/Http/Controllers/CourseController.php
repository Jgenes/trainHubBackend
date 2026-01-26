<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Course;
use App\Models\Note;
use App\Models\Announcement;
use App\Models\LearningTool;
use Illuminate\Support\Facades\Auth;

class CourseController extends Controller
{
    // List all courses for provider
    public function index()
    {
        $courses = Course::where('provider_id', Auth::id())->get();
        return response()->json($courses);
    }

    // Create a course
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'mode' => 'required|string',
            'short_description' => 'required|string',
            'long_description' => 'required|string',
            'learning_outcomes' => 'nullable|array',
            'skills' => 'nullable|array',
            'requirements' => 'nullable|array',
            'contents' => 'required|array',
            'contents.*.title' => 'required|string',
            'contents.*.description' => 'required|string',
            'contents.*.link' => 'nullable|string',
            'contents.*.video' => 'nullable|file|mimes:mp4,mov,avi,webm|max:204800',
            'contents.*.handouts' => 'nullable|file|mimes:pdf|max:20480',
            'banner' => 'nullable|image|mimes:jpg,jpeg,png',
        ]);

        $contents = $request->contents;

        // Handle video & handouts
        foreach ($contents as $i => $section) {
            if (isset($section['video']) && $section['video'] instanceof \Illuminate\Http\UploadedFile) {
                $file = $section['video'];
                $fileName = time() . "_video_{$i}." . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/course_videos'), $fileName);
                $contents[$i]['video'] = 'uploads/course_videos/' . $fileName;
            }

            if (isset($section['handouts']) && $section['handouts'] instanceof \Illuminate\Http\UploadedFile) {
                $file = $section['handouts'];
                $fileName = time() . "_handouts_{$i}." . $file->getClientOriginalExtension();
                $file->move(public_path('uploads/course_handouts'), $fileName);
                $contents[$i]['handouts'] = 'uploads/course_handouts/' . $fileName;
            }
        }

        $courseData = [
            'provider_id' => Auth::id(),
            'title' => $request->title,
            'category' => $request->category,
            'mode' => $request->mode,
            'short_description' => $request->short_description,
            'long_description' => $request->long_description,
            'learning_outcomes' => $request->learning_outcomes ?? [],
            'skills' => $request->skills ?? [],
            'requirements' => $request->requirements ?? [],
            'contents' => $contents,
            'status' => 'Draft',
        ];

        if ($request->hasFile('banner')) {
            $file = $request->file('banner');
            $fileName = time() . "_banner." . $file->getClientOriginalExtension();
            $file->move(public_path('uploads/banners'), $fileName);
            $courseData['banner'] = 'uploads/banners/' . $fileName;
        }

        $course = Course::create($courseData);
        return response()->json(['message' => 'Course created successfully', 'course' => $course]);
    }

    // Show a single course
    public function show($id)
    {
        $course = Course::where('id', $id)->where('provider_id', Auth::id())->firstOrFail();
        return response()->json($course);
    }

    // Delete course
    public function destroy($id)
    {
        $course = Course::where('id', $id)->where('provider_id', Auth::id())->firstOrFail();
        $course->delete();
        return response()->json(['success' => true, 'message' => 'Course deleted successfully!']);
    }

    
    public function submitAll(Request $request, $courseId)
{
    // NOTES
    if($request->has('note_title') && $request->hasFile('note_files')) {
        foreach ($request->file('note_files') as $file) {
            $path = $file->store('course_notes', 'public');
            \App\Models\Note::create([
                'course_id' => $courseId,
                'title' => $request->note_title,
                'file_path' => asset('storage/' . $path)
            ]);
        }
    }

    // ANNOUNCEMENT
    if($request->has('announcement_title') && $request->has('announcement_body')) {
        $announcement = \App\Models\Announcement::create([
            'course_id' => $courseId,
            'title' => $request->announcement_title,
            'content' => $request->announcement_body
        ]);

        if($request->hasFile('announcement_files')) {
            foreach ($request->file('announcement_files') as $file) {
                $filePath = $file->store('announcement_files', 'public');
                // optional: create a model if you track files separately
                // \App\Models\AnnouncementFile::create([...]);
            }
        }
    }

    // TOOL
    if($request->has('tool_name') && $request->has('tool_link')) {
        \App\Models\LearningTool::create([
            'course_id' => $courseId,
            'title' => $request->tool_name,
            'link' => $request->tool_link,
            'description' => $request->tool_description
        ]);
    }

    return response()->json(['message' => 'All items submitted successfully!'], 201);
}

}
