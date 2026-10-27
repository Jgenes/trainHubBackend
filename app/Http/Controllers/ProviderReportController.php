<?php

namespace App\Http\Controllers;

use App\Exports\EnrollmentExport;
use App\Exports\RevenueExport;
use App\Models\Course;
use App\Models\Payment;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ProviderReportController extends Controller
{
    public function coursesList()
    {
        return response()->json(
            Course::where('provider_id', Auth::id())->select('id', 'title')->get()
        );
    }

    /** Per-course performance used by the social engagement screen. */
    public function getCourseStats()
    {
        $userId = Auth::id();

        $courses = Course::where('provider_id', $userId)
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->get();

        $stats = $courses->map(function ($course) {
            $payments = Payment::where('course_id', $course->id)
                ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS']);

            return [
                'course_id'      => $course->id,
                'title'          => $course->title,
                'students'       => (clone $payments)->count(),
                'revenue'        => (float) (clone $payments)->sum('amount'),
                'reviews'        => $course->reviews_count,
                'average_rating' => round($course->reviews_avg_rating ?? 0, 1),
            ];
        });

        return response()->json(['success' => true, 'courses' => $stats]);
    }

    public function enrollmentReport(Request $request)
    {
        $userId   = Auth::id();
        $courseId = $request->get('course_id', 'all');
        $period   = $request->get('period', 'monthly');
        $format   = $request->get('format');

        $query = $this->baseQuery($userId, $courseId);
        $this->applyPeriodFilter($query, $period);
        $enrollments = $query->latest()->get();

        $breakdown = Payment::whereHas('course', fn ($q) => $q->where('provider_id', $userId))
            ->whereIn('status', ['COMPLETED', 'SUCCESS', 'PAID'])
            ->select('course_id', DB::raw('count(*) as count'))
            ->with('course:id,title')
            ->groupBy('course_id');

        $this->applyPeriodFilter($breakdown, $period);

        $breakdownData = $breakdown->get()->map(fn ($item) => [
            'title' => $item->course?->title ?? 'Unknown',
            'count' => $item->count,
        ]);

        if ($format === 'pdf') {
            return Pdf::loadView('pdf.enrollment_pdf', [
                'enrollments' => $enrollments,
                'total'       => $enrollments->count(),
                'user'        => Auth::user(),
                'period'      => $period,
                'reportTitle' => 'Enrollment Report - '.$this->getCourseTitle($userId, $courseId),
            ])->download('Enrollment_Report.pdf');
        }

        if ($format === 'excel' || $format === 'xlsx') {
            return Excel::download(new EnrollmentExport($enrollments), 'Enrollment_Report.xlsx');
        }

        return response()->json([
            'total_students' => $enrollments->count(),
            'enrollments'    => $enrollments,
            'breakdown'      => $breakdownData,
            'generated_at'   => now()->format('d M Y H:i'),
        ]);
    }

    public function revenueReport(Request $request)
    {
        $userId   = Auth::id();
        $courseId = $request->get('course_id', 'all');
        $period   = $request->get('period', 'monthly');
        $format   = $request->get('format');

        $query = $this->baseQuery($userId, $courseId);
        $this->applyPeriodFilter($query, $period);

        $payments     = $query->latest()->get();
        $totalRevenue = $payments->sum('amount');

        if ($format === 'pdf') {
            return Pdf::loadView('pdf.revenue_pdf', [
                'list'        => $payments,
                'total'       => $totalRevenue,
                'user'        => Auth::user(),
                'period'      => $period,
                'reportTitle' => $this->getCourseTitle($userId, $courseId),
            ])->download('Revenue_Report.pdf');
        }

        if ($format === 'excel' || $format === 'xlsx') {
            return Excel::download(
                new RevenueExport($payments, $totalRevenue, $this->getCourseTitle($userId, $courseId)),
                'Revenue_Report.xlsx'
            );
        }

        return response()->json([
            'total_revenue' => $totalRevenue,
            'transactions'  => $payments,
            'generated_at'  => now()->format('d M Y H:i'),
        ]);
    }

    /** GET /api/provider/reports/{type}?format=pdf|excel */
    public function export(Request $request, string $type)
    {
        $format = strtolower((string) $request->query('format', 'pdf'));
        $request->merge(['format' => in_array($format, ['pdf', 'excel', 'xlsx'], true) ? $format : 'pdf']);

        return match ($type) {
            'enrollments' => $this->enrollmentReport($request),
            'revenue'     => $this->revenueReport($request),
            default       => response()->json(['message' => 'Unknown report type.'], 404),
        };
    }

    private function baseQuery($userId, $courseId = null)
    {
        $query = Payment::whereHas('course', fn ($q) => $q->where('provider_id', $userId))
            ->whereIn('status', ['COMPLETED', 'SUCCESS', 'PAID'])
            ->with(['course', 'user']);

        if ($courseId && $courseId !== 'all') {
            $query->where('course_id', $courseId);
        }

        return $query;
    }

    private function applyPeriodFilter($query, $period): void
    {
        $now = Carbon::now();

        match ($period) {
            'daily'  => $query->whereDate('created_at', $now->toDateString()),
            'weekly' => $query->whereBetween('created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]),
            'yearly', 'annual' => $query->whereYear('created_at', $now->year),
            default  => $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year),
        };
    }

    private function getCourseTitle($userId, $courseId): string
    {
        if (! $courseId || $courseId === 'all') {
            return 'All Courses';
        }

        return Course::where('provider_id', $userId)->find($courseId)?->title ?? 'Selected Course';
    }
}
