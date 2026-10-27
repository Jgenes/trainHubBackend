<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Review;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'rating'    => ['required', 'integer', 'min:1', 'max:5'],
            'comment'   => ['nullable', 'string'],
        ]);

        $review = Review::updateOrCreate(
            ['user_id' => Auth::id(), 'course_id' => $data['course_id']],
            ['rating'  => $data['rating'], 'review' => $data['comment'] ?? null]
        );

        return response()->json(['status' => 'success', 'message' => 'Saved!', 'review' => $review]);
    }

    public function providerReviews()
    {
        $providerId = Auth::id();

        $courseStats = Course::where('provider_id', $providerId)
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->get()
            ->map(fn ($course) => [
                'course_id'      => $course->id,
                'course_title'   => $course->title,
                'total_reviews'  => $course->reviews_count,
                'average_rating' => round($course->reviews_avg_rating ?? 0, 1),
            ]);

        $detailedReviews = Review::with(['user:id,name', 'course:id,title'])
            ->whereHas('course', fn ($q) => $q->where('provider_id', $providerId))
            ->latest()
            ->get();

        return response()->json([
            'summary' => [
                'total_feedback_count' => $detailedReviews->count(),
                'overall_average'      => round($detailedReviews->avg('rating') ?? 0, 1),
            ],
            'stats'   => $courseStats,
            'reviews' => $detailedReviews,
        ]);
    }

    public function publicReviews()
    {
        return response()->json(
            Review::with(['user:id,name', 'course:id,title'])->latest()->get()
        );
    }
}
