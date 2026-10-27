<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class EnrollmentExport implements FromCollection, WithHeadings, WithMapping
{
    protected Collection $payments;

    public function __construct($payments)
    {
        $this->payments = collect($payments);
    }

    public function collection(): Collection
    {
        return $this->payments;
    }

    public function headings(): array
    {
        return ['STUDENT NAME', 'COURSE TITLE', 'DATE', 'STATUS'];
    }

    /**
     * @param  \App\Models\Payment|null  $payment
     */
    public function map($payment): array
    {
        return [
            $payment?->user?->name ?? 'N/A',
            $payment?->course?->title ?? 'N/A',
            $payment?->created_at?->format('d M Y') ?? '',
            $payment?->status ?? '',
        ];
    }
}
