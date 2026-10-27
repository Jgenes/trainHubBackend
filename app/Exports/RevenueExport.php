<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class RevenueExport implements FromCollection, WithHeadings, WithMapping
{
    protected Collection $payments;
    protected float $totalRevenue;
    protected string $courseTitle;

    public function __construct($payments, $totalRevenue = 0, $courseTitle = 'All Courses')
    {
        $this->payments = collect($payments);
        $this->totalRevenue = (float) $totalRevenue;
        $this->courseTitle = (string) $courseTitle;
    }

    public function collection(): Collection
    {
        return $this->payments;
    }

    public function headings(): array
    {
        return ['REFERENCE', 'STUDENT', 'COURSE', 'AMOUNT (TZS)', 'STATUS', 'DATE'];
    }

    /**
     * @param  \App\Models\Payment|null  $payment
     */
    public function map($payment): array
    {
        return [
            $payment?->reference ?? '',
            $payment?->user?->name ?? 'N/A',
            $payment?->course?->title ?? 'N/A',
            $payment?->amount ?? 0,
            $payment?->status ?? '',
            $payment?->created_at?->format('d M Y') ?? '',
        ];
    }
}
