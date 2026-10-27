<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Payment;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    /**
     * Revenue / enrollment summary grouped in PHP so the report works on
     * MySQL, PostgreSQL and SQLite (no driver-specific DATE_FORMAT).
     */
    public function getAdminReports(Request $request)
    {
        $filter     = $request->query('period', 'monthly');
        $courseId   = $request->query('course_id');
        $providerId = $request->query('provider_id');

        $query = Payment::query()->where('payments.status', 'COMPLETED');

        if ($providerId) {
            $query->whereIn('payments.course_id', function ($sub) use ($providerId) {
                $sub->select('id')->from('courses')->where('courses.provider_id', $providerId);
            });
        }

        if ($courseId) {
            $query->where('payments.course_id', $courseId);
        }

        $this->applyPeriodFilter($query, $filter);

        $rows = $query->orderBy('payments.created_at')->get(['payments.id', 'payments.course_id', 'payments.amount', 'payments.created_at']);

        $revenue = $rows->groupBy(fn ($row) => $this->label($row->created_at, $filter))
            ->map(fn ($group, $label) => [
                'label'        => $label,
                'total_amount' => (float) $group->sum('amount'),
            ])
            ->values();

        $courseNames = Course::whereIn('id', $rows->pluck('course_id')->filter()->unique())
            ->pluck('title', 'id');

        $enrollments = $rows->groupBy('course_id')
            ->map(fn ($group, $id) => [
                'course_name'   => $courseNames[$id] ?? 'Unknown course',
                'student_count' => $group->count(),
            ])
            ->values();

        return response()->json([
            'success'   => true,
            'revenue'   => $revenue,
            'enrollments' => $enrollments,
            'options'   => [
                'courses'   => Course::select('id', 'title')->get(),
                'providers' => User::where('role', 'tenant')->select('id', 'name')->get(),
            ],
            'summary'   => [
                'total_revenue'     => (float) $rows->sum('amount'),
                'total_enrollments' => $rows->count(),
            ],
        ]);
    }

    private function applyPeriodFilter($query, string $filter): void
    {
        $now = Carbon::now();

        match ($filter) {
            'daily'   => $query->whereDate('payments.created_at', $now->toDateString()),
            'weekly'  => $query->whereBetween('payments.created_at', [$now->copy()->startOfWeek(), $now->copy()->endOfWeek()]),
            'annual'  => $query->whereYear('payments.created_at', $now->year),
            default   => $query->whereMonth('payments.created_at', $now->month)->whereYear('payments.created_at', $now->year),
        };
    }

    private function label($date, string $filter): string
    {
        $date = Carbon::parse($date);

        return match ($filter) {
            'daily'   => $date->format('H:00'),
            'weekly'  => $date->format('D'),
            'annual'  => $date->format('F'),
            default   => $date->format('d M'),
        };
    }
}
