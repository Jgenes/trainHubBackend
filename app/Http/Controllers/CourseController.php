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
    // Tumia 'makeHidden' ili kuzuia data nyingine nzito zisizo lazima
    return response()->json(\App\Models\Course::all()->makeHidden(['created_at', 'updated_at']));
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
}