<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Payment;
use App\Models\Course;
use App\Exports\RevenueExport;
use App\Exports\EnrollmentExport;
use Illuminate\Support\Facades\DB;

class ProviderReportController extends Controller
{
    /**
     * 1. Inaleta list ya kozi za provider kwa ajili ya Dropdown ya Frontend
     */
    public function coursesList()
    {
        $courses = Course::where('provider_id', Auth::id())
            ->select('id', 'title')
            ->get();
        return response()->json($courses);
    }

    /**
     * 2. Enrollment Report (Inajumuisha 'breakdown' kwa ajili ya table ya Frontend)
     */
    public function enrollmentReport(Request $request)
    {
        $userId   = Auth::id();
        $courseId = $request->get('course_id', 'all');
        $period   = $request->get('period', 'monthly');
        $format   = $request->get('format');

        $query = $this->baseQuery($userId, $courseId);
        $this->applyPeriodFilter($query, $period);
        $enrollments = $query->latest()->get();

        // Mchanganuo wa kozi (Breakdown) kwa ajili ya table ya Frontend
        $breakdown = Payment::whereHas('course', fn($q) => $q->where('provider_id', $userId))
            ->whereIn('status', ['COMPLETED', 'SUCCESS', 'PAID'])
            ->select('course_id', DB::raw('count(*) as count'))
            ->with('course:id,title')
            ->groupBy('course_id');
            
        $this->applyPeriodFilter($breakdown, $period);
        $breakdownData = $breakdown->get()->map(function($item) {
            return [
                'title' => $item->course->title ?? 'Unknown',
                'count' => $item->count
            ];
        });

        if ($format === 'pdf') {
            return Pdf::loadView('reports.enrollment_pdf', [
                'enrollments' => $enrollments,
                'total'       => $enrollments->count(),
                'user'        => Auth::user(),
                'period'      => $period,
                'reportTitle' => "Enrollment Report - " . $this->getCourseTitle($userId, $courseId)
            ])->download('Enrollment_Report.pdf');
        }

        if ($format === 'excel') {
            return Excel::download(new EnrollmentExport($enrollments), 'Enrollment_Report.xlsx');
        }

        return response()->json([
            'total_students' => $enrollments->count(),
            'enrollments'    => $enrollments,
            'breakdown'      => $breakdownData, // Hii ndio Frontend inataka
            'generated_at'   => now()->format('d M Y H:i')
        ]);
    }

    /**
     * 3. Revenue Report
     */
    public function revenueReport(Request $request)
    {
        $userId   = Auth::id();
        $courseId = $request->get('course_id', 'all');
        $period   = $request->get('period', 'monthly');
        $format   = $request->get('format');

        $query = $this->baseQuery($userId, $courseId);
        $this->applyPeriodFilter($query, $period);

        $payments = $query->latest()->get();
        $totalRevenue = $payments->sum('amount');

        if ($format === 'pdf') {
            return Pdf::loadView('reports.revenue_pdf', [
                'list'        => $payments,
                'total'       => $totalRevenue,
                'user'        => Auth::user(),
                'period'      => $period,
                'reportTitle' => $this->getCourseTitle($userId, $courseId)
            ])->download('Revenue_Report.pdf');
        }

        if ($format === 'excel') {
            return Excel::download(new RevenueExport($payments, $totalRevenue, $this->getCourseTitle($userId, $courseId)), 'Revenue_Report.xlsx');
        }

        return response()->json([
            'total_revenue' => $totalRevenue,
            'transactions'  => $payments, // Hii inatumiwa na table yako upande wa kulia
            'generated_at'  => now()->format('d M Y H:i')
        ]);
    }

    /* --- HELPERS --- */

    private function baseQuery($userId, $courseId = null)
    {
        $query = Payment::whereHas('course', fn($q) => $q->where('provider_id', $userId))
            ->whereIn('status', ['COMPLETED', 'SUCCESS', 'PAID'])
            ->with(['course', 'user']);

        if ($courseId && $courseId !== 'all') {
            $query->where('course_id', $courseId);
        }
        return $query;
    }

    private function applyPeriodFilter($query, $period)
    {
        $now = Carbon::now();
        switch ($period) {
            case 'daily':   $query->whereDate('created_at', $now->toDateString()); break;
            case 'weekly':  $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]); break;
            case 'yearly':  $query->whereYear('created_at', $now->year); break;
            default:        $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year); break;
        }
    }

    private function getCourseTitle($userId, $courseId)
    {
        if (!$courseId || $courseId === 'all') return 'All Courses';
        $course = Course::where('provider_id', $userId)->find($courseId);
        return $course ? $course->title : 'Selected Course';
    }
}