<?php
namespace App\Http\Controllers; // Hakikisha hapa huna \Api
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Enrollment;
use Illuminate\Support\Facades\Auth;

class LearningController extends Controller
{
    public function myCourses()
    {
        $user = Auth::user();

        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('status', 'PAID')
            ->with(['course'])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $enrollments
        ]);
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
