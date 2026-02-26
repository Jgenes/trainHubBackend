<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    public function getAdminReports(Request $request)
    {
        $filter = $request->query('period', 'monthly');
        $courseId = $request->query('course_id');
        $providerId = $request->query('provider_id'); // Hii inachukua ID ya Tenant

        // 1. Kutengeneza Base Query kwa ajili ya malipo yaliyokamilika
        $query = Payment::where('payments.status', 'COMPLETED');

        // 2. Kuchuja kwa TENANT (Provider)
        // Tunajiunga na table ya courses ili kujua hili malipo ni la kozi ya tenant gani
        if ($providerId) {
            $query->join('courses', 'payments.course_id', '=', 'courses.id')
                  ->where('courses.user_id', $providerId)
                  ->select('payments.*'); // Tunahakikisha tunabaki na columns za malipo
        }

        // 3. Kuchuja kwa KOZI maalum
        if ($courseId) {
            $query->where('payments.course_id', $courseId);
        }

        // 4. FILTER KWA MUDA (Time Filtering)
        if ($filter == 'daily') {
            $query->whereDate('payments.created_at', today());
            $groupBy = DB::raw("DATE_FORMAT(payments.created_at, '%H:00') as label");
        } elseif ($filter == 'weekly') {
            $query->where('payments.created_at', '>=', now()->startOfWeek());
            $groupBy = DB::raw("DATE_FORMAT(payments.created_at, '%a') as label");
        } elseif ($filter == 'annual') {
            $query->whereYear('payments.created_at', date('Y'));
            $groupBy = DB::raw("DATE_FORMAT(payments.created_at, '%M') as label");
        } else {
            // Default: Monthly
            $query->whereMonth('payments.created_at', date('m'))
                  ->whereYear('payments.created_at', date('Y'));
            $groupBy = DB::raw("DATE_FORMAT(payments.created_at, '%d %b') as label");
        }

        // --- A. REVENUE TREND (Data za AreaChart) ---
        $revenueData = (clone $query)
            ->select(
                $groupBy,
                DB::raw('SUM(payments.amount) as total_amount')
            )
            ->groupBy('label')
            ->orderBy(DB::raw("MIN(payments.created_at)"), 'asc')
            ->get();

        // --- B. ENROLLMENT BREAKDOWN (Data za Table & BarChart) ---
        $enrollmentData = (clone $query)
            ->join('courses as c_list', 'payments.course_id', '=', 'c_list.id')
            ->select('c_list.title as course_name', DB::raw('count(payments.id) as student_count'))
            ->groupBy('c_list.title')
            ->get();

        // --- C. SUMMARY STATS ---
        $totalRevenue = (clone $query)->sum('payments.amount');
        $totalEnrollments = (clone $query)->count('payments.id');

        // --- D. DROPDOWN OPTIONS (Hapa ndipo 'tenant' inatumika) ---
        $courses = Course::select('id', 'title')->get();
        $tenants = User::where('role', 'tenant')
        ->select('id', 'name')->get();

        return response()->json([
            'success' => true,
            'revenue' => $revenueData,
            'enrollments' => $enrollmentData,
            'options' => [
                'courses' => $courses,
                'providers' => $tenants // Tumeiita 'providers' ili iendane na Frontend yako
            ],
            'summary' => [
                'total_revenue' => (float)$totalRevenue,
                'total_enrollments' => (int)$totalEnrollments,
            ]
        ]);
    }
}