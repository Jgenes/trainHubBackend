<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Course;
use App\Models\Payment;
use Illuminate\Http\Request;

class AdminNotificationController extends Controller
{
    public function getCount()
    {
        try {
            // 1. Providers wapya (mfano: waliojisajili leo)
            $newProviders = User::where('role', 'tenant')
                ->whereDate('created_at', today())
                ->count();

            // 2. Courses mpya zinazosubiri review (kama unayo 'status' ya pending)
            $newCourses = Course::whereDate('created_at', today())
                ->count();

            // 3. Transactions zilizofanyika leo (Zilizokamilika)
            $newTransactions = Payment::where('status', 'COMPLETED')
                ->whereDate('created_at', today())
                ->count();

            // 4. Mfano wa Logs au Ripoti mpya
            $newLogs = 0; // Unaweza kuongeza logic hapa kulingana na table yako ya Logs

            return response()->json([
                'providers' => $newProviders,
                'courses' => $newCourses,
                'transactions' => $newTransactions,
                'reports' => 0, // Idadi ya ripoti mpya kama zipo
                'logs' => $newLogs
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }
}