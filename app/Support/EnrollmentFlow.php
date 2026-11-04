<?php

namespace App\Support;

use App\Models\Payment;

class EnrollmentFlow
{
    /** Keep the same payment-based entitlement rule as My Learning. */
    public static function decision(?int $userId, int $courseId): array
    {
        $enrolled = $userId !== null && Payment::where('user_id', $userId)
            ->where('course_id', $courseId)
            ->whereIn('status', ['COMPLETED', 'PAID', 'SUCCESS'])
            ->exists();

        return [
            'is_enrolled' => $enrolled,
            'enrollment_action' => $enrolled ? 'login' : 'checkout',
            // Frontend-relative paths, not backend routes or gateway URLs.
            'enrollment_redirect' => $enrolled ? '/login' : '/checkout?course_id='.$courseId,
        ];
    }
}
