<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ImportErrorsExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles
{
    public function __construct(
        protected array $errors
    ) {}

    public function headings(): array
    {
        return [
            'Row',
            'Error',
            'Full Name',
            'Phone',
            'Email',
            'Organization Name',
            'Position',
            'Category',
            'Badge Type',
        ];
    }

    public function array(): array
    {
        return collect($this->errors)
            ->map(function ($error) {
                return [
                    $error['row'] ?? null,
                    $error['error'] ?? $error['message'] ?? (string) $error,
                    $error['full_name'] ?? null,
                    $error['phone'] ?? null,
                    $error['email'] ?? null,
                    $error['organization_name'] ?? null,
                    $error['position'] ?? null,
                    $error['category'] ?? null,
                    $error['badge_type'] ?? null,
                ];
            })
            ->values()
            ->toArray();
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getStyle('A1:I1')->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB('FFFFFF');

        $sheet->getStyle('A1:I1')->getFill()
            ->setFillType('solid')
            ->getStartColor()
            ->setARGB('DC2626');

        $sheet->getStyle('A1:I1000')->getBorders()
            ->getAllBorders()
            ->setBorderStyle('thin')
            ->getColor()
            ->setARGB('CBD5E1');

        $sheet->freezePane('A2');

        return [];
    }
}