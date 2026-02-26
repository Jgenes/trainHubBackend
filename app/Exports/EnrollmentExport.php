<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;

class EnrollmentExport implements FromCollection
{
    protected $enrollments;

    public function __construct($enrollments)
    {
        $this->enrollments = $enrollments;
    }

    public function collection()
    {
        return $this->enrollments->map(function ($enrollment) {
            return [
                'Student' => $enrollment->user->name ?? '',
                'Course'  => $enrollment->course->title ?? '',
                'Date'    => $enrollment->created_at->format('d M Y'),
            ];
        });
    }
}