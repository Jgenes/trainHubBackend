<?php

namespace App\Http\Controllers;

use App\Models\Review;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    // Student store/update review
    public function store(Request $request)
    {
        try {
            $request->validate([
                'course_id' => 'required',
                'rating'    => 'required|integer|min:1|max:5',
                'comment'   => 'nullable|string'
            ]);

            $review = Review::updateOrCreate(
                [
                    'user_id'   => auth()->id(),
                    'course_id' => $request->course_id
                ],
                [
                    'rating' => $request->rating,
                    'review' => $request->comment
                ]
            );

            return response()->json([
                'status' => 'success',
                'message' => 'Saved!'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    // Provider reviews dashboard
    public function providerReviews()
    {
        $providerId = Auth::id();

        $courseStats = Course::where('user_id', $providerId)
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->get()
            ->map(function ($course) {
                return [
                    'course_id'     => $course->id,
                    'course_title'  => $course->title,
                    'total_reviews' => $course->reviews_count,
                    'average_rating'=> round($course->reviews_avg_rating, 1) ?? 0,
                ];
            });

        $detailedReviews = Review::with(['user:id,name', 'course:id,title'])
            ->whereHas('course', function ($query) use ($providerId) {
                $query->where('user_id', $providerId);
            })
            ->latest()
            ->get();

        $summary = [
            'total_feedback_count' => $detailedReviews->count(),
            'overall_average'      => round($detailedReviews->avg('rating'), 1) ?? 0,
        ];

        return response()->json([
            'summary' => $summary,
            'stats'   => $courseStats,
            'reviews' => $detailedReviews
        ]);
    }

    // PUBLIC REVIEWS (HII NDIYO INATUMIWA NA HOME PAGE YAKO)
    public function publicReviews()
    {
        return Review::with(['user:id,name', 'course:id,title'])
            ->latest()
            ->get();
    }
}