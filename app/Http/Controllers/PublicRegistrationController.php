<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\AttendeeMerchandise;
use App\Models\AttendeeRegistrationAnswer;
use App\Models\BadgeType;
use App\Models\Event;
use App\Models\MerchandiseVariant;
use App\Services\BadgeGenerationService;
use App\Services\QrTokenService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PublicRegistrationController extends Controller
{
    public function show(Event $event): View
    {
        $event->load([
            'organization',
            'attendeeCategories',
            'badgeTypes',
            'registrationFields',
        ]);

        $fields = $event->registrationFields()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $eventDays = $this->registrationDays($event);

        return view('public.events.register', [
            'event' => $event,
            'branding' => $this->branding($event),
            'isOpen' => (bool) $event->registration_is_open,
            'isFull' => $event->isRegistrationFull(),
            'waitlistEnabled' => (bool) $event->registration_waitlist_enabled,
            'registrationStats' => $this->registrationStats($event),

            'categories' => $event->attendeeCategories()
                ->orderBy('name')
                ->get(),

            'badgeTypes' => $event->badgeTypes()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),

            'fields' => $fields,
            'eventDays' => $eventDays,
            'merchandiseItems' => $this->registrationMerchandise($event),
        ]);
    }

    public function store(Request $request, Event $event): RedirectResponse
    {
        $event->load([
            'organization',
            'registrationFields',
        ]);

        if (! $event->registration_is_open) {
            return back()
                ->withInput()
                ->with('error', 'Registration for this event is currently closed.');
        }

        $isFull = $event->isRegistrationFull();
        $waitlistEnabled = (bool) $event->registration_waitlist_enabled;

        if ($isFull && ! $waitlistEnabled) {
            return back()
                ->withInput()
                ->with('error', 'Registration is full. The event has reached its capacity.');
        }

        $fields = $event->registrationFields()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        $eventDays = $this->registrationDays($event);
        $merchandiseItems = $this->registrationMerchandise($event);

        $validated = $request->validate(
            $this->registrationRules(
                $event,
                $fields,
                $eventDays,
                $merchandiseItems
            ),
            $this->validationMessages(
                $eventDays,
                $merchandiseItems
            )
        );

        $fullName = trim($validated['full_name']);
        $phone = $event->registration_show_phone
            ? $this->normalizePhone($validated['phone'] ?? null)
            : null;

        $email = $event->registration_show_email
            ? strtolower(trim($validated['email'] ?? ''))
            : '';

        if ($this->alreadyRegistered($event, $fullName, $phone, $email)) {
            return back()
                ->withInput()
                ->with('error', 'This attendee already exists for this event.');
        }

        $status = match (true) {
            $isFull && $waitlistEnabled => 'waitlisted',
            $event->registration_requires_approval => 'pending_approval',
            default => 'registered',
        };

        $badgeTypeId = $event->registration_show_badge_type
            ? ($validated['badge_type_id'] ?? null)
            : null;

        if (! $badgeTypeId) {
            $badgeTypeId = BadgeType::query()
                ->where('event_id', $event->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->value('id');
        }

        try {
            $attendee = DB::transaction(
                function () use (
                    $event,
                    $validated,
                    $fields,
                    $eventDays,
                    $merchandiseItems,
                    $fullName,
                    $phone,
                    $email,
                    $status,
                    $badgeTypeId
                ): Attendee {
                    $attendee = Attendee::create([
                        'event_id' => $event->id,
                        'category_id' =>
                            $event->registration_show_category
                                ? ($validated['category_id'] ?? null)
                                : null,

                        'badge_type_id' => $badgeTypeId,
                        'full_name' => $fullName,
                        'phone' => $phone,
                        'email' => $email ?: null,

                        'organization_name' =>
                            $event->registration_show_organization
                                ? (
                                    trim(
                                        $validated['organization_name'] ?? ''
                                    ) ?: null
                                )
                                : null,

                        'position' =>
                            $event->registration_show_position
                                ? (
                                    trim(
                                        $validated['position'] ?? ''
                                    ) ?: null
                                )
                                : null,
                        'status' => $status,
                        'registration_source' => 'public',
                        'registered_at' => now(),
                    ]);

                    $this->saveRegistrationAnswers(
                        $event,
                        $attendee,
                        $fields,
                        $validated['answers'] ?? []
                    );

                    $this->saveEventDaySelections(
                        $attendee,
                        $eventDays,
                        $validated['event_days'] ?? []
                    );

                    $this->saveMerchandiseOrders(
                        $event,
                        $attendee,
                        $merchandiseItems,
                        $validated['merchandise'] ?? [],
                        $status
                    );

                    return $attendee;
                },
                attempts: 3
            );
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            report($exception);

            return back()
                ->withInput()
                ->with(
                    'error',
                    'Registration could not be completed. Please try again.'
                );
        }

        app(QrTokenService::class)->generateForAttendee($attendee);

        if (
            $event->registration_auto_generate_badge
            && $status === 'registered'
        ) {
            app(BadgeGenerationService::class)
                ->generateForAttendee($attendee);
        }

        return redirect()->route(
            'public.registration.success',
            [
                'event' => $event,
                'attendee' => $attendee,
            ]
        );
    }

    public function success(Event $event, Attendee $attendee): View
    {
        abort_unless(
            (int) $attendee->event_id === (int) $event->id,
            404
        );

        $event->load('organization');

        $attendee->load([
            'eventDays',
            'merchandiseSelections.merchandise',
            'merchandiseSelections.variant',
        ]);

        return view('public.events.success', [
            'event' => $event,
            'attendee' => $attendee,
            'branding' => $this->branding($event),
            'registrationStats' => $this->registrationStats($event),
        ]);
    }

    protected function registrationRules(
        Event $event,
        Collection $fields,
        Collection $eventDays,
        Collection $merchandiseItems
    ): array {
        $rules = [
            'full_name' => [
                'required',
                'string',
                'max:255',
            ],

            'phone' => [
                $this->standardFieldRule(
                    (bool) $event->registration_show_phone,
                    (bool) $event->registration_require_phone
                ),
                'string',
                'max:255',
            ],

            'email' => [
                $this->standardFieldRule(
                    (bool) $event->registration_show_email,
                    (bool) $event->registration_require_email
                ),
                'email',
                'max:255',
            ],

            'organization_name' => [
                $this->standardFieldRule(
                    (bool) $event->registration_show_organization,
                    (bool) $event->registration_require_organization
                ),
                'string',
                'max:255',
            ],

            'position' => [
                $this->standardFieldRule(
                    (bool) $event->registration_show_position,
                    (bool) $event->registration_require_position
                ),
                'string',
                'max:255',
            ],

            'category_id' => [
                $this->standardFieldRule(
                    (bool) $event->registration_show_category,
                    (bool) $event->registration_require_category
                ),
                'integer',

                Rule::exists('attendee_categories', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('event_id', $event->id)
                    ),
            ],

            'badge_type_id' => [
                $this->standardFieldRule(
                    (bool) $event->registration_show_badge_type,
                    (bool) $event->registration_require_badge_type
                ),
                'integer',

                Rule::exists('badge_types', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('event_id', $event->id)
                            ->where('is_active', true)
                    ),
            ],

            'answers' => [
                'nullable',
                'array',
            ],

            'event_days' => [
                $eventDays->isNotEmpty() ? 'required' : 'nullable',
                'array',
                $eventDays->isNotEmpty() ? 'min:1' : 'min:0',
            ],

            'event_days.*' => [
                'integer',

                Rule::exists('event_days', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where('event_id', $event->id)
                            ->where('status', 'active')
                            ->where('is_registration_open', true)
                    ),
            ],

            'merchandise' => [
                'nullable',
                'array',
            ],
        ];

        foreach ($fields as $field) {
            $fieldRules = [
                $field->is_required ? 'required' : 'nullable',
            ];

            $fieldType = $field->field_type
                ?? $field->type
                ?? 'text';

            if ($fieldType === 'checkbox') {
                $fieldRules[] = 'array';
                $fieldRules[] = 'max:100';

                $rules["answers.{$field->id}"] = $fieldRules;
                $rules["answers.{$field->id}.*"] = [
                    'string',
                    'max:1000',
                ];

                continue;
            }

            match ($fieldType) {
                'email' => $fieldRules[] = 'email',
                'number' => $fieldRules[] = 'numeric',
                'date' => $fieldRules[] = 'date',
                'phone' => $fieldRules[] = 'string',
                default => $fieldRules[] = 'string',
            };

            $fieldRules[] = 'max:1000';

            $rules["answers.{$field->id}"] = $fieldRules;
        }

        foreach ($merchandiseItems as $item) {
            $itemPath = "merchandise.{$item->id}";
            $selectedPath = "{$itemPath}.selected";
            $variantPath = "{$itemPath}.variant_id";
            $quantityPath = "{$itemPath}.quantity";

            $rules[$itemPath] = ['nullable', 'array'];
            $rules[$selectedPath] = ['nullable', 'boolean'];

            $isRequiredItem = $item->selection_type === 'required';
            $isSelected = $isRequiredItem
                || request()->boolean($selectedPath);

            $rules[$variantPath] = [
                $isSelected ? 'required' : 'nullable',
                'integer',
                Rule::exists('merchandise_variants', 'id')
                    ->where(
                        fn ($query) => $query
                            ->where(
                                'event_merchandise_id',
                                $item->id
                            )
                            ->where('is_active', true)
                    ),
            ];

            $rules[$quantityPath] = [
                $isSelected ? 'required' : 'nullable',
                'integer',
                'min:1',
                'max:' . max(
                    1,
                    (int) $item->maximum_per_attendee
                ),
            ];
        }

        return $rules;
    }

    protected function standardFieldRule(
        bool $isShown,
        bool $isRequired
    ): string {
        if (! $isShown) {
            return 'prohibited';
        }

        return $isRequired
            ? 'required'
            : 'nullable';
    }

    protected function validationMessages(
        Collection $eventDays,
        Collection $merchandiseItems
    ): array {
        $messages = [
            'phone.required' =>
                'Please enter your phone number.',

            'email.required' =>
                'Please enter your email address.',

            'email.email' =>
                'Please enter a valid email address.',

            'organization_name.required' =>
                'Please enter your organization or company.',

            'position.required' =>
                'Please enter your position or title.',

            'category_id.required' =>
                'Please select an attendee category.',

            'category_id.exists' =>
                'The selected attendee category is unavailable.',

            'badge_type_id.required' =>
                'Please select a badge type.',

            'badge_type_id.exists' =>
                'The selected badge type is unavailable.',
        ];

        if ($eventDays->isNotEmpty()) {
            $messages['event_days.required'] =
                'Please select at least one day or session.';

            $messages['event_days.min'] =
                'Please select at least one day or session.';

            $messages['event_days.*.exists'] =
                'One of the selected days or sessions is unavailable.';
        }

        foreach ($merchandiseItems as $item) {
            $messages[
                "merchandise.{$item->id}.variant_id.required"
            ] = "Please select a size or color for {$item->name}.";

            $messages[
                "merchandise.{$item->id}.variant_id.exists"
            ] = "The selected option for {$item->name} is unavailable.";

            $messages[
                "merchandise.{$item->id}.quantity.required"
            ] = "Please enter the quantity for {$item->name}.";

            $messages[
                "merchandise.{$item->id}.quantity.min"
            ] = 'Quantity must be at least one.';

            $messages[
                "merchandise.{$item->id}.quantity.max"
            ] = "The selected quantity for {$item->name} is above the allowed maximum.";
        }

        return $messages;
    }

    protected function saveRegistrationAnswers(
        Event $event,
        Attendee $attendee,
        Collection $fields,
        array $answers
    ): void {
        foreach ($fields as $field) {
            $answer = $answers[$field->id] ?? null;

            if (is_array($answer)) {
                $answer = json_encode(
                    $answer,
                    JSON_UNESCAPED_UNICODE
                );
            }

            if (blank($answer)) {
                continue;
            }

            AttendeeRegistrationAnswer::create([
                'event_id' => $event->id,
                'attendee_id' => $attendee->id,
                'event_registration_field_id' => $field->id,
                'value' => $answer,
            ]);
        }
    }

    protected function saveEventDaySelections(
        Attendee $attendee,
        Collection $eventDays,
        array $selectedDayIds
    ): void {
        if ($eventDays->isEmpty()) {
            return;
        }

        $allowedDayIds = $eventDays
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);

        $selectedDayIds = collect($selectedDayIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(
                fn (int $id): bool =>
                    $allowedDayIds->contains($id)
            )
            ->unique()
            ->values();

        if ($selectedDayIds->isEmpty()) {
            throw ValidationException::withMessages([
                'event_days' =>
                    'Please select at least one day or session.',
            ]);
        }

        $pivotData = [];

        foreach ($selectedDayIds as $dayId) {
            $pivotData[$dayId] = [
                'selection_source' =>
                    'public_registration',
                'selected_at' => now(),
            ];
        }

        $attendee->eventDays()->sync($pivotData);
    }

    protected function saveMerchandiseOrders(
        Event $event,
        Attendee $attendee,
        Collection $merchandiseItems,
        array $submittedMerchandise,
        string $attendeeStatus
    ): void {
        foreach ($merchandiseItems as $item) {
            $selection = $submittedMerchandise[$item->id] ?? [];

            $isRequired = $item->selection_type === 'required';

            $isSelected = $isRequired
                || filter_var(
                    $selection['selected'] ?? false,
                    FILTER_VALIDATE_BOOLEAN
                );

            if (! $isSelected) {
                continue;
            }

            $variantId = $selection['variant_id'] ?? null;

            if (! $variantId) {
                throw ValidationException::withMessages([
                    "merchandise.{$item->id}.variant_id" =>
                        "Please select a size or color for {$item->name}.",
                ]);
            }

            $variant = MerchandiseVariant::query()
                ->whereKey($variantId)
                ->where('event_merchandise_id', $item->id)
                ->where('is_active', true)
                ->lockForUpdate()
                ->first();

            if (! $variant) {
                throw ValidationException::withMessages([
                    "merchandise.{$item->id}.variant_id" =>
                        "The selected option for {$item->name} is unavailable.",
                ]);
            }

            $quantity = (int) ($selection['quantity'] ?? 1);
            $maximum = max(
                1,
                (int) $item->maximum_per_attendee
            );

            if ($quantity < 1) {
                throw ValidationException::withMessages([
                    "merchandise.{$item->id}.quantity" =>
                        'Quantity must be at least one.',
                ]);
            }

            if ($quantity > $maximum) {
                throw ValidationException::withMessages([
                    "merchandise.{$item->id}.quantity" =>
                        "You can order a maximum of {$maximum} for {$item->name}.",
                ]);
            }

            /*
             * Exact remaining stock is intentionally not disclosed publicly.
             * The stock check still runs while the row is locked.
             */
            if (! $variant->hasAvailableStock($quantity)) {
                throw ValidationException::withMessages([
                    "merchandise.{$item->id}.quantity" =>
                        'The requested quantity is currently unavailable. Please reduce the quantity or select another option.',
                ]);
            }

            $unitPrice = (float) ($variant->price ?? 0);
            $totalPrice = $unitPrice * $quantity;

            $selectionStatus = match ($attendeeStatus) {
                'waitlisted' => 'waitlisted',
                'pending_approval' => 'selected',
                default => 'reserved',
            };

            AttendeeMerchandise::create([
                'event_id' => $event->id,
                'attendee_id' => $attendee->id,
                'event_merchandise_id' => $item->id,
                'merchandise_variant_id' => $variant->id,

                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'total_price' => $totalPrice,
                'currency' => $variant->currency ?: 'TZS',
                'payment_status' => $unitPrice > 0
                    ? 'pending'
                    : 'not_required',

                'status' => $selectionStatus,
                'selection_source' => 'public_registration',
                'selected_at' => now(),
            ]);
        }
    }

    protected function registrationDays(
        Event $event
    ): Collection {
        return $event->days()
            ->where('status', 'active')
            ->where('is_registration_open', true)
            ->orderBy('display_order')
            ->orderBy('event_date')
            ->orderBy('id')
            ->get();
    }

    protected function registrationMerchandise(Event $event): Collection
    {
        return $event->merchandise()
            ->where('is_active', true)
            ->whereIn('selection_type', [
                'optional',
                'required',
            ])
            ->where(function ($query) {
                $query
                    ->whereNull('selection_opens_at')
                    ->orWhere(
                        'selection_opens_at',
                        '<=',
                        now()
                    );
            })
            ->where(function ($query) {
                $query
                    ->whereNull('selection_closes_at')
                    ->orWhere(
                        'selection_closes_at',
                        '>=',
                        now()
                    );
            })
            ->with([
                'activeVariants' => function ($query) {
                    $query
                        ->orderBy('display_order')
                        ->orderBy('id');
                },
            ])
            ->orderBy('display_order')
            ->orderBy('id')
            ->get();
    }

    protected function alreadyRegistered(
        Event $event,
        string $fullName,
        ?string $phone,
        ?string $email
    ): bool {
        return Attendee::query()
            ->where('event_id', $event->id)
            ->where(function ($query) use (
                $fullName,
                $phone,
                $email
            ) {
                $query->where(
                    function ($query) use (
                        $fullName,
                        $phone
                    ) {
                        $query->whereRaw(
                            'LOWER(full_name) = ?',
                            [strtolower($fullName)]
                        );

                        if (filled($phone)) {
                            $query->where('phone', $phone);
                        }
                    }
                );

                if (filled($email)) {
                    $query->orWhere(
                        'email',
                        strtolower($email)
                    );
                }
            })
            ->whereNotIn('status', [
                'rejected',
                'cancelled',
            ])
            ->exists();
    }

    protected function registrationStats(Event $event): array
    {
        $capacity = (int) ($event->capacity ?? 0);
        $accepted = $event->acceptedAttendeesCount();

        $pending = Attendee::query()
            ->where('event_id', $event->id)
            ->where('status', 'pending_approval')
            ->count();

        $registered = Attendee::query()
            ->where('event_id', $event->id)
            ->whereIn('status', [
                'registered',
                'confirmed',
                'checked_in',
            ])
            ->count();

        $waitlisted = $event->waitlistedAttendeesCount();

        return [
            'capacity' => $capacity > 0
                ? $capacity
                : null,
            'accepted' => $accepted,
            'pending' => $pending,
            'registered' => $registered,
            'waitlisted' => $waitlisted,
            'remaining' => $event->remainingCapacity(),
            'is_full' => $event->isRegistrationFull(),
        ];
    }

    protected function branding(Event $event): array
    {
        $organization = $event->organization;

        return [
            'logo' => $event->registration_logo_path
                ?: $organization?->logo_path,

            'banner' => $event->registration_banner_image_path,

            'primary_color' => $event->registration_primary_color
                ?: $organization?->primary_color
                ?: '#233F7E',

            'background_color' => $event->registration_background_color
                ?: $organization?->background_color
                ?: '#F8FAFC',

            'button_color' => $event->registration_button_color
                ?: $organization?->button_color
                ?: '#233F7E',

            'support_email' => $organization?->support_email
                ?: $organization?->email,

            'support_phone' => $organization?->support_phone
                ?: $organization?->phone,
        ];
    }

    protected function normalizePhone(?string $phone): ?string
    {
        $phone = trim((string) $phone);

        if ($phone === '') {
            return null;
        }

        $phone = preg_replace('/[^0-9+]/', '', $phone);

        if (str_starts_with($phone, '+')) {
            $phone = substr($phone, 1);
        }

        if (
            str_starts_with($phone, '0')
            && strlen($phone) === 10
        ) {
            return '255' . substr($phone, 1);
        }

        if (
            (
                str_starts_with($phone, '7')
                || str_starts_with($phone, '6')
            )
            && strlen($phone) === 9
        ) {
            return '255' . $phone;
        }

        return $phone;
    }
}
