<?php

namespace App\Http\Controllers;

use App\Models\Course;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class ProviderReportController extends Controller
{
    private function getStats($userId)
    {
        $query = Course::where('user_id', $userId);

        return [
            'summary' => [
                'total_courses' => (clone $query)->count(),
                'total_students' => 0, // Update this later with Enrollments
            ],
            'reports' => [
                'daily'   => (clone $query)->whereDate('created_at', Carbon::today())->count(),
                'weekly'  => (clone $query)->where('created_at', '>=', Carbon::now()->subDays(7))->count(),
                'monthly' => (clone $query)->whereMonth('created_at', Carbon::now()->month)->count(),
            ],
            'generated_at' => Carbon::now()->format('d M Y, H:i')
        ];
    }

    // Inarudisha data za kawaida (JSON)
    public function getCourseStats()
    {
        try {
            $data = $this->getStats(Auth::id());
            return response()->json($data);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    // Inatengeneza na kudownload PDF
    public function downloadPdf()
    {
        $data = $this->getStats(Auth::id());
        $data['user'] = Auth::user();

        $pdf = Pdf::loadView('reports.provider_courses', $data);
        return $pdf->download('Course_Report_'.now()->format('Ymd').'.pdf');
    }
}