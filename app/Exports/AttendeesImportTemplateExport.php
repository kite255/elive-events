<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AttendeesImportTemplateExport implements FromArray, ShouldAutoSize, WithColumnWidths, WithStyles, WithTitle
{
    public function title(): string
    {
        return 'eLive Import Template';
    }

    public function array(): array
    {
        return [
            [
                'eLive Events',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
            [
                'Attendees Import Template',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
            [
                'Use this file to import attendees into eLive Events. Do not change the column names in row 5.',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
            [
                '',
                '',
                '',
                '',
                '',
                '',
                '',
            ],
            [
                'full_name',
                'phone',
                'email',
                'organization_name',
                'position',
                'category',
                'badge_type',
            ],
            [
                'John Michael',
                '712345678',
                'kitenken@elive.co.tz',
                'ABC Company',
                'Manager',
                'VIP',
                'VIP Badge',
            ],
            [
                'Sarah Joseph',
                '713456789',
                'sarah@elive.co.tz',
                'XYZ Organization',
                'Director',
                'Delegate',
                'Delegate Badge',
            ],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 28,
            'B' => 18,
            'C' => 28,
            'D' => 28,
            'E' => 22,
            'F' => 18,
            'G' => 22,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:G1');
        $sheet->mergeCells('A2:G2');
        $sheet->mergeCells('A3:G3');

        $sheet->getStyle('A1')->getFont()
            ->setBold(true)
            ->setSize(22)
            ->getColor()
            ->setARGB('233F7E');

        $sheet->getStyle('A2')->getFont()
            ->setBold(true)
            ->setSize(16)
            ->getColor()
            ->setARGB('0F172A');

        $sheet->getStyle('A3')->getFont()
            ->setSize(11)
            ->getColor()
            ->setARGB('64748B');

        $sheet->getStyle('A5:G5')->getFont()
            ->setBold(true)
            ->getColor()
            ->setARGB('FFFFFF');

        $sheet->getStyle('A5:G5')->getFill()
            ->setFillType('solid')
            ->getStartColor()
            ->setARGB('233F7E');

        $sheet->getStyle('A5:G7')->getBorders()
            ->getAllBorders()
            ->setBorderStyle('thin')
            ->getColor()
            ->setARGB('CBD5E1');

        $sheet->getStyle('A1:G7')->getAlignment()
            ->setVertical('center');

        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->getRowDimension(2)->setRowHeight(24);
        $sheet->getRowDimension(5)->setRowHeight(24);

        $sheet->freezePane('A6');

        return [];
    }
}