<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EnrollmentExport implements FromCollection, WithHeadings, WithMapping
{
    protected $enrollments;

    public function __construct($enrollments)
    {
        $this->enrollments = $enrollments;
    }

    public function collection()
    {
        return $this->enrollments;
    }

    public function headings(): array
    {
        return ["STUDENT NAME", "COURSE TITLE", "DATE", "STATUS"];
    }

    public function map($e): array
    {
        return [
            $e->user->name ?? 'N/A',
            $e->course->title ?? 'N/A',
            $e->created_at->format('d M Y'),
            $e->status
        ];
    }
}