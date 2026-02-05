<?php
namespace App\Http\Controllers; // Hakikisha hapa huna \Api
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Auth;
use App\Models\Payment; // Au Enrollment kama unatumia table hiyo
class LearningController extends Controller
{
    public function myCourses()
    {
        try {
            $user = auth()->user();

            // Tunatafuta kozi ambazo huyu user amelipia (Status PAID/COMPLETED)
            // Tunatumia 'with' ili kuchukua data za kozi husika toka table ya courses
            $enrollments = Payment::where('user_id', $user->id)
                ->whereIn('status', ['PAID', 'COMPLETED'])
                ->with('course') // Hii inachukua data za course husika
                ->get();

            // Tunarudisha data kwa format ambayo React yako inatarajia
            return response()->json($enrollments);
            
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

  public function learningContent($id)
{
    try {
        $userId = auth('sanctum')->id();

        // 1. Check kama amelipia
        $isPaid = \App\Models\Payment::where('user_id', $userId)
            ->where('course_id', $id)
            ->whereIn('status', ['COMPLETED', 'PAID'])
            ->exists();

        if (!$isPaid) {
            return response()->json(['message' => 'Access denied. Please pay first.'], 403);
        }

        // 2. Tafuta kozi
        $course = \App\Models\Course::findOrFail($id);

        // 3. Chakata 'contents' (Ambayo ni JSON array kutokana na $casts kwenye Model)
        // Kila 'item' hapa ina: title, description, video, handouts[], video_links[], notes_text
        $modules = collect($course->contents)->map(function ($item, $index) {
            return [
                'id'          => $index + 1,
                'title'       => $item['title'] ?? 'Untitled Lesson',
                'description' => $item['description'] ?? '',
                'notes'       => $item['notes_text'] ?? '',
                // Hapa tunatengeneza full URL ya video
                'video_url'   => !empty($item['video']) ? asset('storage/' . $item['video']) : null,
                'links'       => $item['video_links'] ?? [],
                'handouts'    => isset($item['handouts']) ? collect($item['handouts'])->map(fn($h) => asset('storage/' . $h)) : []
            ];
        });

        return response()->json([
            'id'                => $course->id,
            'title'             => $course->title,
            'short_description' => $course->short_description,
            'modules'           => $modules
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error'   => 'Server Error',
            'message' => $e->getMessage()
        ], 500);
    }
}


    // public function myEnrollments()
    // {
    //     $user = Auth::user();

    //     $enrollments = Enrollment::where('user_id', $user->id)
    //         ->with([
    //             'course:id,title',
    //             'cohort:id,name'
    //         ])
    //         ->get()
    //         ->map(function ($e) use ($user) {
    //             return [
    //                 'id' => $e->id,
    //                 'student' => $user->name,
    //                 'course' => $e->course?->title ?? 'N/A',
    //                 'cohort' => $e->cohort?->name ?? 'N/A',
    //                 'date' => $e->created_at->format('Y-m-d'),
    //                 'status' => ucfirst(strtolower($e->status)),
    //             ];
    //         });

    //     return response()->json([
    //         'success' => true,
    //         'data' => $enrollments
    //     ]);
    // }
    

//    public function providerEnrollments()
// {
//     $user = Auth::user();

//     try {
//         $enrollments = Enrollment::with([
//                 'student:id,name', // Iwe student, siyo user
//                 'cohort:id,name',
//                 'course:id,title,provider_id'
//             ])
//             ->whereHas('course', function ($q) use ($user) {
//                 // Hakikisha hapa unalinganisha provider_id wa kozi na ID ya aliyelogin
//                 $q->where('provider_id', $user->id);
//             })
//             ->latest()
//             ->get()
//             ->map(function ($e) {
//                 return [
//                     'id' => $e->id,
//                     'student' => $e->student?->name ?? 'N/A', // Iwe student
//                     'course' => $e->course?->title ?? 'N/A',
//                     'cohort' => $e->cohort?->name ?? 'N/A',
//                     'date' => $e->created_at->format('Y-m-d'),
//                     'status' => ucfirst(strtolower($e->status)),
//                 ];
//             });

//         return response()->json($enrollments); // Rudi kwenye array rahisi ili React isichanganyikiwe
//     } catch (\Exception $e) {
//         return response()->json(['error' => $e->getMessage()], 500);
//     }
// }

}
