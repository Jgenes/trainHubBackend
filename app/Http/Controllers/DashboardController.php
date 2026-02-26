<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Cohort;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
public function getStats()
{
    try {
        $totalCourses = Course::count();

        $totalCohorts = Cohort::withTrashed()->count();

        $totalEnrollments = Payment::whereIn('status', [
            'COMPLETED', 'PAID', 'SUCCESS'
        ])->count();

        $ongoingTrainings = Cohort::whereNotNull('start_date')
            ->whereNotNull('end_date')
            ->whereDate('start_date', '<=', now())
            ->whereDate('end_date', '>=', now())
            ->count();

        $courseData = Payment::whereIn('payments.status', [
                'COMPLETED', 'PAID', 'SUCCESS'
            ])
            ->join('courses', 'payments.course_id', '=', 'courses.id')
            ->select(
                'courses.title as course_name',
                DB::raw('COUNT(payments.id) as total')
            )
            ->groupBy('courses.title')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        return response()->json([
            'totalCourses'     => (int) $totalCourses,
            'totalCohorts'     => (int) $totalCohorts,
            'totalEnrollments' => (int) $totalEnrollments,
            'ongoingTrainings' => (int) $ongoingTrainings,
            'chart' => [
                'labels' => $courseData->pluck('course_name'),
                'values' => $courseData->pluck('total'),
            ]
        ]);

    } catch (\Exception $e) {
        return response()->json([
            'error' => $e->getMessage()
        ], 500);
    }
}

}