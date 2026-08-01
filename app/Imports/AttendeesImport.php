<?php

namespace App\Imports;

use App\Models\Attendee;
use App\Models\AttendeeCategory;
use App\Models\BadgeType;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class AttendeesImport implements ToCollection, WithHeadingRow
{
    public int $imported = 0;

    public int $skipped = 0;

    public array $errors = [];

    public function __construct(
        protected int $eventId
    ) {}

    public function headingRow(): int
    {
        return 5;
    }

    public function collection(Collection $rows): void
    {
        foreach ($rows as $index => $row) {
            $rowNumber = $index + 6;

            $data = [
                'full_name' => trim((string) ($row['full_name'] ?? $row['name'] ?? '')),
                'phone' => $this->normalizePhone((string) ($row['phone'] ?? $row['phone_number'] ?? '')),
                'email' => strtolower(trim((string) ($row['email'] ?? ''))),
                'organization_name' => trim((string) ($row['organization_name'] ?? $row['organization'] ?? $row['company'] ?? '')),
                'position' => trim((string) ($row['position'] ?? $row['title'] ?? '')),
                'category' => trim((string) ($row['category'] ?? '')),
                'badge_type' => trim((string) ($row['badge_type'] ?? $row['badge'] ?? '')),
            ];

            if ($this->isEmptyRow($data)) {
                continue;
            }

            $validator = Validator::make($data, [
                'full_name' => ['required', 'string', 'max:255'],
                'phone' => ['nullable', 'string', 'max:255'],
                'email' => ['nullable', 'email', 'max:255'],
                'organization_name' => ['nullable', 'string', 'max:255'],
                'position' => ['nullable', 'string', 'max:255'],
                'category' => ['nullable', 'string', 'max:255'],
                'badge_type' => ['nullable', 'string', 'max:255'],
            ]);

            if ($validator->fails()) {
                $this->addError($rowNumber, $validator->errors()->first(), $data);
                continue;
            }

            $categoryId = $this->resolveCategoryId($data['category']);

            $badgeTypeId = $this->resolveBadgeTypeId($data['badge_type']);

            if ($this->isDuplicate($data)) {
                $this->addError($rowNumber, 'Duplicate attendee skipped.', $data);
                continue;
            }

            Attendee::create([
                'event_id' => $this->eventId,
                'category_id' => $categoryId,
                'badge_type_id' => $badgeTypeId,
                'full_name' => $data['full_name'],
                'phone' => $data['phone'] ?: null,
                'email' => $data['email'] ?: null,
                'organization_name' => $data['organization_name'] ?: null,
                'position' => $data['position'] ?: null,
                'status' => 'registered',
                'registration_source' => 'import',
                'registered_at' => now(),
            ]);

            $this->imported++;
        }
    }

    protected function normalizePhone(string $phone): string
    {
        $phone = trim($phone);

        if ($phone === '') {
            return '';
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (str_starts_with($phone, '0') && strlen($phone) === 10) {
            return '255' . substr($phone, 1);
        }

        if (str_starts_with($phone, '7') && strlen($phone) === 9) {
            return '255' . $phone;
        }

        if (str_starts_with($phone, '6') && strlen($phone) === 9) {
            return '255' . $phone;
        }

        return $phone;
    }

    protected function isEmptyRow(array $data): bool
    {
        return $data['full_name'] === ''
            && $data['phone'] === ''
            && $data['email'] === ''
            && $data['organization_name'] === ''
            && $data['position'] === ''
            && $data['category'] === ''
            && $data['badge_type'] === '';
    }

    protected function resolveCategoryId(string $categoryName): ?int
    {
        if ($categoryName === '') {
            return null;
        }

        $category = AttendeeCategory::query()
            ->firstOrCreate(
                [
                    'event_id' => $this->eventId,
                    'name' => $categoryName,
                ],
                [
                    'is_active' => true,
                ]
            );

        return $category->id;
    }

    protected function resolveBadgeTypeId(string $badgeTypeName): ?int
    {
        if ($badgeTypeName === '') {
            return null;
        }

        $badgeType = BadgeType::query()
            ->firstOrCreate(
                [
                    'event_id' => $this->eventId,
                    'name' => $badgeTypeName,
                ],
                [
                    'is_active' => true,
                ]
            );

        return $badgeType->id;
    }

    protected function isDuplicate(array $data): bool
    {
        return Attendee::query()
            ->where('event_id', $this->eventId)
            ->where('full_name', $data['full_name'])
            ->when($data['phone'] !== '', fn ($query) => $query->where('phone', $data['phone']))
            ->exists();
    }

    protected function addError(int $row, string $message, array $data = []): void
    {
        $this->skipped++;

        $this->errors[] = [
            'row' => $row,
            'error' => $message,
            'full_name' => $data['full_name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'email' => $data['email'] ?? null,
            'organization_name' => $data['organization_name'] ?? null,
            'position' => $data['position'] ?? null,
            'category' => $data['category'] ?? null,
            'badge_type' => $data['badge_type'] ?? null,
        ];
    }
}