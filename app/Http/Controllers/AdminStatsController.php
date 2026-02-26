<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Payment;
use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AdminStatsController extends Controller
{
    public function getAdminDashboardData()
    {
        try {
            // 1. Total Users (Role ya 'user' pekee)
            $totalUsers = User::where('role', 'user')->count();

            // 2. Service Providers (Role ya 'tenant')
            $totalProviders = User::where('role', 'tenant')->count();

            // 3. Total Courses
            $totalCourses = Course::count();

            // 4. Total Revenue (Malipo yaliyo 'COMPLETED' pekee)
            $totalRevenue = Payment::where('status', 'COMPLETED')->sum('amount') ?? 0;

            // 5. Top Enrolled Course (Kutoka table ya payments)
            $topCourseQuery = Payment::where('status', 'COMPLETED')
                ->select('course_id', DB::raw('count(*) as total'))
                ->groupBy('course_id')
                ->orderBy('total', 'desc')
                ->first();
            
            $topCourseName = "N/A";
            if ($topCourseQuery) {
                $course = Course::find($topCourseQuery->course_id);
                $topCourseName = $course ? $course->title : "Unknown Course";
            }

            // 6. Revenue Chart Data (Miezi 6 iliyopita)
            $labels = [];
            $values = [];

            for ($i = 5; $i >= 0; $i--) {
                $date = Carbon::now()->subMonths($i);
                $labels[] = $date->format('M'); // Mfano: Sep, Oct, Nov...

                $monthlyRevenue = Payment::where('status', 'COMPLETED')
                    ->whereMonth('created_at', $date->month)
                    ->whereYear('created_at', $date->year)
                    ->sum('amount');
                
                $values[] = (float) $monthlyRevenue;
            }

            // Inarudisha format ile ile inayotakiwa na Frontend yako
            return response()->json([
                'totalUsers' => $totalUsers,
                'totalProviders' => $totalProviders,
                'totalCourses' => $totalCourses,
                'totalRevenue' => $totalRevenue,
                'topEnrolledCourse' => $topCourseName,
                'revenueChart' => [
                    'labels' => $labels,
                    'values' => $values
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}