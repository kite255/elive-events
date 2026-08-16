<?php

namespace App\Http\Controllers;

use App\Models\Attendee;
use App\Models\AttendeeCategory;
use App\Models\AttendeeMerchandise;
use App\Models\AttendeeRegistrationAnswer;
use App\Models\BadgeType;
use App\Models\Event;
use App\Models\EventSession;
use App\Models\MerchandiseVariant;
use App\Services\AutomaticCommunicationService;
use App\Services\BadgeGenerationService;
use App\Services\PhoneNumberService;
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
        $eventSessions = $this->registrationSessions(
            $event,
            $eventDays
        );

        return view('public.events.register', [
            'event' => $event,
            'branding' => $this->branding($event),
            'isOpen' => (bool) $event->registration_is_open,
            'isFull' => $event->isRegistrationFull(),
            'waitlistEnabled' => (bool) $event->registration_waitlist_enabled,
            'registrationStats' => $this->registrationStats($event),

            'categories' => $this->registrationCategories($event),

            'badgeTypes' => $event->badgeTypes()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(),

            'fields' => $fields,
            'eventDays' => $eventDays,
            'eventSessions' => $eventSessions,

            'allowDaySelection' =>
                $event->allowsDaySelection(),

            'allowAllDaysSelection' =>
                $event->allowsAllDaysSelection(),

            'allowSessionRegistration' =>
                $event->allowsSessionRegistration(),

            'registrationSectionLabels' =>
                $event->registrationSectionLabels(),

            'merchandiseItems' =>
                $this->registrationMerchandise($event),
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
        $eventSessions = $this->registrationSessions(
            $event,
            $eventDays
        );
        $merchandiseItems = $this->registrationMerchandise($event);

        $validated = $request->validate(
            $this->registrationRules(
                $event,
                $fields,
                $eventDays,
                $eventSessions,
                $merchandiseItems
            ),
            $this->validationMessages(
                $event,
                $eventDays,
                $eventSessions,
                $merchandiseItems
            )
        );

        $fullName = trim($validated['full_name']);
        $phone = $event->registration_show_phone
            ? app(PhoneNumberService::class)
                ->normalize($validated['phone'] ?? null)
            : null;

        $email = $event->registration_show_email
            ? strtolower(trim($validated['email'] ?? ''))
            : '';

        $duplicateReason = $this->duplicateRegistrationReason(
            $event,
            $fullName,
            $email
        );

        if ($duplicateReason) {
            return back()
                ->withInput()
                ->with('error', $duplicateReason);
        }

        $status = match (true) {
            $isFull && $waitlistEnabled => 'waitlisted',
            $event->registration_requires_approval => 'pending_approval',
            default => 'registered',
        };

        /*
        |--------------------------------------------------------------------------
        | Participant type and badge resolution
        |--------------------------------------------------------------------------
        */

        $categoryId = $event->registration_show_category
            ? ($validated['category_id'] ?? null)
            : null;

        $selectedCategory = null;

        if ($categoryId) {
            $selectedCategory = AttendeeCategory::query()
                ->whereKey($categoryId)
                ->where('event_id', $event->id)
                ->where('is_active', true)
                ->where('is_public', true)
                ->first();
        }

        $badgeTypeId = $selectedCategory?->badge_type_id;

        if (
            ! $badgeTypeId
            && $event->registration_show_badge_type
        ) {
            $badgeTypeId = $validated['badge_type_id'] ?? null;
        }

        if (! $badgeTypeId) {
            $badgeTypeId = BadgeType::query()
                ->where('event_id', $event->id)
                ->where('is_active', true)
                ->orderByDesc('is_default')
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
                    $eventSessions,
                    $merchandiseItems,
                    $fullName,
                    $phone,
                    $email,
                    $status,
                    $categoryId,
                    $badgeTypeId
                ): Attendee {
                    $attendee = Attendee::create([
                        'event_id' => $event->id,
                        'category_id' => $categoryId,
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
                        $event,
                        $attendee,
                        $eventDays,
                        $validated['event_days'] ?? []
                    );

                    $this->saveEventSessionSelections(
                        $event,
                        $attendee,
                        $eventSessions,
                        $validated['event_sessions'] ?? []
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

        /*
        |--------------------------------------------------------------------------
        | Refresh generated attendee values before badge generation
        |--------------------------------------------------------------------------
        */

        $attendee->refresh();

        if (
            $event->registration_auto_generate_badge
            && $status === 'registered'
        ) {
            app(BadgeGenerationService::class)
                ->generateForAttendee($attendee);

            $attendee->refresh();
        }

        /*
        |--------------------------------------------------------------------------
        | Automatic registration communication
        |--------------------------------------------------------------------------
        |
        | Communication runs only after attendee creation, badge number/QR
        | generation, and optional badge generation. Communication failures
        | must never roll back or block a successful registration.
        |
        */

        try {
            app(AutomaticCommunicationService::class)
                ->handleRegistration($attendee->fresh());
        } catch (Throwable $exception) {
            report($exception);
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
            'eventSessions.eventDay',
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
        Collection $eventSessions,
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
                'max:20',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ) use ($event): void {
                    if (! $event->registration_show_phone) {
                        return;
                    }

                    if (blank($value)) {
                        return;
                    }

                    if (
                        ! app(PhoneNumberService::class)
                            ->isValid((string) $value)
                    ) {
                        $fail(
                            'Please enter a valid Tanzanian mobile number, for example 0650537539.'
                        );
                    }
                },
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
                            ->where('is_active', true)
                            ->where('is_public', true)
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
                $event->allowsDaySelection()
                    && $eventDays->isNotEmpty()
                        ? 'required'
                        : 'nullable',
                'array',
                $event->allowsDaySelection()
                    && $eventDays->isNotEmpty()
                        ? 'min:1'
                        : 'min:0',
            ],

            'event_days.*' => [
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ) use (
                    $event,
                    $eventDays
                ): void {
                    if (! $event->allowsDaySelection()) {
                        $fail(
                            'Event-day selection is not enabled for this event.'
                        );

                        return;
                    }

                    if ($value === 'all') {
                        if (! $event->allowsAllDaysSelection()) {
                            $fail(
                                'The All Event Days option is not available for this event.'
                            );
                        }

                        return;
                    }

                    if (! is_numeric($value)) {
                        $fail(
                            'One of the selected event days is invalid.'
                        );

                        return;
                    }

                    $allowed = $eventDays->contains(
                        fn ($day): bool =>
                            (int) $day->id === (int) $value
                    );

                    if (! $allowed) {
                        $fail(
                            'One of the selected event days is unavailable.'
                        );
                    }
                },
            ],

            'event_sessions' => [
                $event->allowsSessionRegistration()
                    ? 'nullable'
                    : 'prohibited',
                'array',
                'max:100',
            ],

            'event_sessions.*' => [
                'integer',
                function (
                    string $attribute,
                    mixed $value,
                    \Closure $fail
                ) use (
                    $event,
                    $eventDays,
                    $eventSessions
                ): void {
                    if (! $event->allowsSessionRegistration()) {
                        $fail(
                            'Session registration is not enabled for this event.'
                        );

                        return;
                    }

                    if (! is_numeric($value)) {
                        $fail(
                            'One of the selected sessions is invalid.'
                        );

                        return;
                    }

                    $session = $eventSessions->firstWhere(
                        'id',
                        (int) $value
                    );

                    if (! $session) {
                        $fail(
                            'One of the selected sessions is unavailable.'
                        );

                        return;
                    }

                    if ($event->allowsDaySelection()) {
                        $submittedDays = collect(
                            request()->input(
                                'event_days',
                                []
                            )
                        );

                        $selectAllDays = $submittedDays
                            ->contains(
                                fn ($dayId): bool =>
                                    (string) $dayId === 'all'
                            );

                        if ($selectAllDays) {
                            if (! $event->allowsAllDaysSelection()) {
                                $fail(
                                    'The All Event Days option is not available for this event.'
                                );

                                return;
                            }

                            $selectedDayIds = $eventDays
                                ->pluck('id')
                                ->map(
                                    fn ($id): int => (int) $id
                                );
                        } else {
                            $selectedDayIds = $submittedDays
                                ->filter(
                                    fn ($dayId): bool =>
                                        is_numeric($dayId)
                                )
                                ->map(
                                    fn ($dayId): int =>
                                        (int) $dayId
                                );
                        }
                    } else {
                        /*
                         * When public day selection is disabled,
                         * all active/open event days are assigned
                         * automatically to the attendee.
                         */
                        $selectedDayIds = $eventDays
                            ->pluck('id')
                            ->map(
                                fn ($id): int => (int) $id
                            );
                    }

                    if (
                        ! $selectedDayIds->contains(
                            (int) $session->event_day_id
                        )
                    ) {
                        $fail(
                            $session->name
                            . ' belongs to an event day you are not registered to attend.'
                        );
                    }
                },
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
        Event $event,
        Collection $eventDays,
        Collection $eventSessions,
        Collection $merchandiseItems
    ): array {
        $messages = [
            'phone.required' =>
                'Please enter your phone number.',

            'phone.string' =>
                'Please enter a valid phone number.',

            'phone.max' =>
                'The phone number is too long.',

            'email.required' =>
                'Please enter your email address.',

            'email.email' =>
                'Please enter a valid email address.',

            'organization_name.required' =>
                'Please enter your organization or company.',

            'position.required' =>
                'Please enter your position or title.',

            'category_id.required' =>
                'Please select a participant type.',

            'category_id.exists' =>
                'The selected participant type is unavailable.',

            'badge_type_id.required' =>
                'Please select a badge type.',

            'badge_type_id.exists' =>
                'The selected badge type is unavailable.',
        ];

        if (
            $event->allowsDaySelection()
            && $eventDays->isNotEmpty()
        ) {
            $messages['event_days.required'] =
                'Please select at least one event day.';

            $messages['event_days.min'] =
                'Please select at least one event day.';
        }

        if ($eventSessions->isNotEmpty()) {
            $messages['event_sessions.prohibited'] =
                'Session registration is not enabled for this event.';

            $messages['event_sessions.array'] =
                'The selected sessions are invalid.';

            $messages['event_sessions.*.integer'] =
                'One of the selected sessions is invalid.';
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
        Event $event,
        Attendee $attendee,
        Collection $eventDays,
        array $selectedDayIds
    ): void {
        if ($eventDays->isEmpty()) {
            return;
        }

        $allowedDayIds = $eventDays
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        /*
         * If day selection is disabled, automatically assign
         * every active/open event day.
         */
        if (! $event->allowsDaySelection()) {
            $pivotData = [];

            foreach ($allowedDayIds as $dayId) {
                $pivotData[$dayId] = [
                    'selection_source' =>
                        'public_registration_auto_days',
                    'selected_at' => now(),
                ];
            }

            $attendee->eventDays()->sync($pivotData);

            return;
        }

        $selectAllDays = collect($selectedDayIds)
            ->contains(
                fn ($value): bool =>
                    (string) $value === 'all'
            );

        if (
            $selectAllDays
            && ! $event->allowsAllDaysSelection()
        ) {
            throw ValidationException::withMessages([
                'event_days' =>
                    'The All Event Days option is not available for this event.',
            ]);
        }

        if ($selectAllDays) {
            $selectedDayIds = $allowedDayIds;
        } else {
            $selectedDayIds = collect($selectedDayIds)
                ->filter(
                    fn ($id): bool =>
                        is_numeric($id)
                )
                ->map(fn ($id): int => (int) $id)
                ->filter(
                    fn (int $id): bool =>
                        $allowedDayIds->contains($id)
                )
                ->unique()
                ->values();
        }

        if ($selectedDayIds->isEmpty()) {
            throw ValidationException::withMessages([
                'event_days' =>
                    'Please select at least one event day.',
            ]);
        }

        $pivotData = [];

        foreach ($selectedDayIds as $dayId) {
            $pivotData[$dayId] = [
                'selection_source' =>
                    $selectAllDays
                        ? 'public_registration_all_days'
                        : 'public_registration',
                'selected_at' => now(),
            ];
        }

        $attendee->eventDays()->sync($pivotData);
    }

    protected function saveEventSessionSelections(
        Event $event,
        Attendee $attendee,
        Collection $eventSessions,
        array $selectedSessionIds
    ): void {
        if (! $event->allowsSessionRegistration()) {
            return;
        }

        if (
            $eventSessions->isEmpty()
            || $selectedSessionIds === []
        ) {
            return;
        }

        $selectedSessionIds = collect($selectedSessionIds)
            ->filter(
                fn ($id): bool =>
                    is_numeric($id)
            )
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        if ($selectedSessionIds->isEmpty()) {
            return;
        }

        $selectedDayIds = $attendee->eventDays()
            ->pluck('event_days.id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $allowedSessionIds = $eventSessions
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        $pivotData = [];

        foreach ($selectedSessionIds as $sessionId) {
            if (! $allowedSessionIds->contains($sessionId)) {
                throw ValidationException::withMessages([
                    'event_sessions' =>
                        'One of the selected sessions is unavailable.',
                ]);
            }

            $session = EventSession::query()
                ->whereKey($sessionId)
                ->where('event_id', $attendee->event_id)
                ->where('status', EventSession::STATUS_ACTIVE)
                ->where('requires_registration', true)
                ->where('registration_is_open', true)
                ->lockForUpdate()
                ->first();

            if (! $session) {
                throw ValidationException::withMessages([
                    'event_sessions' =>
                        'One of the selected sessions is no longer available.',
                ]);
            }

            if (
                ! $selectedDayIds->contains(
                    (int) $session->event_day_id
                )
            ) {
                throw ValidationException::withMessages([
                    'event_sessions' =>
                        $session->name
                        . ' belongs to an event day you did not select.',
                ]);
            }

            if (
                $session->capacity !== null
                && (int) $session->capacity > 0
            ) {
                $registeredCount = DB::table(
                    'attendee_event_session'
                )
                    ->where(
                        'event_session_id',
                        $session->id
                    )
                    ->where(
                        'status',
                        'registered'
                    )
                    ->count();

                if (
                    $registeredCount
                    >= (int) $session->capacity
                ) {
                    throw ValidationException::withMessages([
                        'event_sessions' =>
                            $session->name
                            . ' has reached its capacity. Please select another session.',
                    ]);
                }
            }

            $pivotData[$session->id] = [
                'status' => 'registered',
                'selection_source' =>
                    'public_registration',
                'selected_at' => now(),
            ];
        }

        if ($pivotData !== []) {
            $attendee->eventSessions()->sync(
                $pivotData
            );
        }
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

    protected function registrationCategories(
        Event $event
    ): Collection {
        return $event->attendeeCategories()
            ->where('is_active', true)
            ->where('is_public', true)
            ->orderBy('group_name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();
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

    protected function registrationSessions(
        Event $event,
        Collection $eventDays
    ): Collection {
        if (
            ! $event->allowsSessionRegistration()
            || $eventDays->isEmpty()
        ) {
            return new Collection();
        }

        $eventDayIds = $eventDays
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        return EventSession::query()
            ->where('event_id', $event->id)
            ->whereIn('event_day_id', $eventDayIds)
            ->where('status', EventSession::STATUS_ACTIVE)
            ->where('requires_registration', true)
            ->where('registration_is_open', true)
            ->with('eventDay')
            ->withCount([
                'registeredAttendees as registered_attendees_count',
            ])
            ->orderBy('event_day_id')
            ->orderBy('display_order')
            ->orderBy('starts_at')
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

    protected function duplicateRegistrationReason(
        Event $event,
        string $fullName,
        ?string $email
    ): ?string {
        $normalizedName = strtolower(
            trim($fullName)
        );

        $nameExists = Attendee::query()
            ->where('event_id', $event->id)
            ->whereNotIn('status', [
                'rejected',
                'cancelled',
            ])
            ->whereRaw(
                'LOWER(TRIM(full_name)) = ?',
                [$normalizedName]
            )
            ->exists();

        if ($nameExists) {
            return 'This attendee name is already registered for this event. Please verify the name or contact the event organizer.';
        }

        if (filled($email)) {
            $normalizedEmail = strtolower(
                trim($email)
            );

            $emailExists = Attendee::query()
                ->where('event_id', $event->id)
                ->whereNotIn('status', [
                    'rejected',
                    'cancelled',
                ])
                ->whereRaw(
                    'LOWER(TRIM(email)) = ?',
                    [$normalizedEmail]
                )
                ->exists();

            if ($emailExists) {
                return 'This email address is already registered for this event. Please use a different email address or contact the event organizer.';
            }
        }

        return null;
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
                ?: '#161943',

            'background_color' => $event->registration_background_color
                ?: $organization?->background_color
                ?: '#F8FAFC',

            'button_color' => $event->registration_button_color
                ?: $organization?->button_color
                ?: '#161943',

            'support_email' => $organization?->support_email
                ?: $organization?->email,

            'support_phone' => $organization?->support_phone
                ?: $organization?->phone,
        ];
    }
}
