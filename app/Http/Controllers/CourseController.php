<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Course;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::where('provider_id', Auth::id())->get();
        return response()->json($courses);
    }

    // App/Http/Controllers/CourseController.php

public function publicIndex() {
    // Tunapakia kozi pamoja na cohorts zake kwa mpigo mmoja (Eager Loading)
    $courses = \App\Models\Course::with(['cohorts' => function($query) {
        $query->where('status', 'OPEN'); // Optional: kama unataka cohorts za wazi tu
    }])->get();

    return response()->json($courses->makeHidden(['created_at', 'updated_at']));
}
    public function store(Request $request)
    {
        // 1. Validation
        $request->validate([
            'title' => 'required|string|max:255',
            'category' => 'required|string',
            'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120', // Max 5MB
        ]);

        try {
            return DB::transaction(function () use ($request) {
                // PHP inashindwa kusoma nested FormData arrays wakati mwingine, 
                // tunahakikisha tunapata contents vizuri
                $contents = $request->contents;
                
                // Ikiwa React imetuma kama JSON string (mara nyingi hutokea kwenye FormData complex)
                if (is_string($contents)) {
                    $contents = json_decode($contents, true);
                }

                if (!is_array($contents)) {
                    throw new \Exception("Course contents are missing or invalid.");
                }

                foreach ($contents as $i => $section) {
                    // A. Shughulikia Main Video
                    if ($request->hasFile("contents.$i.video")) {
                        $videoFile = $request->file("contents.$i.video");
                        $vName = time() . "_main_v_{$i}." . $videoFile->getClientOriginalExtension();
                        $videoFile->move(public_path('uploads/courses/videos'), $vName);
                        $contents[$i]['video'] = 'uploads/courses/videos/' . $vName;
                    }

                    // B. Shughulikia Multiple PDF Handouts
                    $uploadedHandouts = [];
                    if ($request->hasFile("contents.$i.handouts")) {
                        $files = $request->file("contents.$i.handouts");
                        foreach ($files as $fileKey => $file) {
                            $fileName = time() . "_h_{$i}_{$fileKey}." . $file->getClientOriginalExtension();
                            $file->move(public_path('uploads/courses/docs'), $fileName);
                            $uploadedHandouts[] = 'uploads/courses/docs/' . $fileName;
                        }
                        $contents[$i]['handouts'] = $uploadedHandouts;
                    } else {
                        // Kama hakuna file jipya, baki na zilizopo au empty array
                        $contents[$i]['handouts'] = $contents[$i]['handouts'] ?? [];
                    }

                    // C. Video Links (Zinakuja kama array tayari)
                    $contents[$i]['video_links'] = $section['video_links'] ?? [];
                }

                // D. Handle Banner
                $bannerPath = null;
                if ($request->hasFile('banner')) {
                    $bannerFile = $request->file('banner');
                    $bannerName = time() . '_b.' . $bannerFile->getClientOriginalExtension();
                    $bannerFile->move(public_path('uploads/banners'), $bannerName);
                    $bannerPath = 'uploads/banners/' . $bannerName;
                }

                // 2. Create Course
                $course = Course::create([
                    'provider_id'       => Auth::id(),
                    'title'             => $request->title,
                    'category'          => $request->category,
                    'mode'              => $request->mode ?? 'Online',
                    'short_description' => $request->short_description,
                    'long_description'  => $request->long_description,
                    'learning_outcomes' => $request->learning_outcomes, // Hakikisha model ina casts to array
                    'skills'            => $request->skills,
                    'requirements'      => $request->requirements,
                    'contents'          => $contents, 
                    'banner'            => $bannerPath,
                    'status'            => 'Draft'
                ]);

                // 3. Return Response (Ufunguo wa 'course' ni muhimu kwa React)
                return response()->json([
                    'message' => 'Course saved successfully!',
                    'course'  => $course
                ], 201);
            });

        } catch (\Exception $e) {
            Log::error("Course Creation Error: " . $e->getMessage());
            return response()->json([
                'error' => 'Failed to save course',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    public function show($id)
    {
        $course = Course::where('id', $id)
            ->where('provider_id', Auth::id())
            ->firstOrFail();
        return response()->json($course);
    }

    public function destroy($id)
    {
        $course = Course::where('id', $id)
            ->where('provider_id', Auth::id())
            ->firstOrFail();
        
        // Futa faili za banner/video hapa ikiwa ni lazima
        $course->delete();
        return response()->json(['success' => true, 'message' => 'Course deleted successfully!']);
    }

   public function update(Request $request, $id)
{
    $request->validate([
        'title' => 'required|string|max:255',
        'category' => 'required|string',
        'banner' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:5120',
    ]);

    try {
        return DB::transaction(function () use ($request, $id) {
            $course = Course::where('id', $id)
                ->where('provider_id', Auth::id())
                ->firstOrFail();

            // --- 1. HANDLE ARRAYS (Taja column majina ya DB) ---
            $processArray = function($fieldName) use ($request, $course) {
                $data = $request->input($fieldName);
                if (!$data) return $course->$fieldName; // Kama hakuna data mpya, tumia ya zamani

                if (is_string($data)) {
                    $decoded = json_decode($data, true);
                    return (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) 
                        ? $decoded 
                        : array_filter(array_map('trim', explode("\n", $data)));
                }
                return $data;
            };

            // Hakikisha hapa unatumia majina ya column za DB (snake_case)
            $course->learning_outcomes = $processArray('learning_outcomes');
            $course->requirements      = $processArray('requirements');
            $course->skills            = $processArray('skills');

            // --- 2. HANDLE BANNER ---
            if ($request->hasFile('banner')) {
                $bannerFile = $request->file('banner');
                $bannerName = time() . '_b.' . $bannerFile->getClientOriginalExtension();
                $bannerFile->move(public_path('uploads/banners'), $bannerName);
                $bannerPath = 'uploads/banners/' . $bannerName;

                if ($course->banner && file_exists(public_path($course->banner))) {
                    @unlink(public_path($course->banner));
                }
                $course->banner = $bannerPath;
            }

            // --- 3. UPDATE FIELDS (Taja moja baada ya nyingine kuzuia Unknown Column error) ---
            $course->title = $request->input('title');
            $course->category = $request->input('category');
            $course->mode = $request->input('mode', $course->mode);
            $course->short_description = $request->input('short_description', $course->short_description);
            $course->long_description = $request->input('long_description', $course->long_description);
            // $course->price = $request->input('price', $course->price);
            $course->status = $request->input('status', $course->status);

            $course->save();

            return response()->json([
                'success' => true,
                'message' => 'Course updated successfully',
                'course'  => $course
            ]);
        });
    } catch (\Exception $e) {
        Log::error('Course Update Error: ' . $e->getMessage());
        return response()->json([
            'success' => false,
            'error'   => 'Failed to update course',
            'details' => $e->getMessage() // Hapa ndipo ilipokuambia Column not found
        ], 500);
    }
}
}