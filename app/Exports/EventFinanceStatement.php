<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;

class EventFinanceStatement extends DefaultValueBinder implements FromArray, WithCustomValueBinder
{
    public function __construct(private array $rows) {}

    public function array(): array
    {
        return $this->rows;
    }

    public function bindValue(Cell $cell, mixed $value): bool
    {
        // Preserve identifiers and decimal precision; never interpret user text as formulas.
        if (is_string($value)) {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }
}
