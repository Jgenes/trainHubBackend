<?php

namespace App\Http\Controllers;

use App\Models\Cohort;
use App\Models\Course;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function getStats()
    {
        $paidStatuses = ['COMPLETED', 'PAID', 'SUCCESS'];

        $courseData = Payment::whereIn('payments.status', $paidStatuses)
            ->join('courses', 'payments.course_id', '=', 'courses.id')
            ->select('courses.title as course_name', DB::raw('COUNT(payments.id) as total'))
            ->groupBy('courses.title')
            ->orderByDesc('total')
            ->take(10)
            ->get();

        return response()->json([
            'totalCourses'     => Course::count(),
            'totalCohorts'     => Cohort::count(),
            'totalEnrollments' => Payment::whereIn('status', $paidStatuses)->count(),
            'ongoingTrainings' => Cohort::whereDate('start_date', '<=', now())
                ->whereDate('end_date', '>=', now())
                ->count(),
            'chart' => [
                'labels' => $courseData->pluck('course_name'),
                'values' => $courseData->pluck('total'),
            ],
        ]);
    }
}
