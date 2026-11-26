<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="elive-admission-cards" role="radiogroup" aria-label="Admission method">
        @foreach ([
            'free_registration' => ['Free registration', 'Attendees register online without payment.', 'heroicon-o-clipboard-document-list'],
            'paid_registration' => ['Paid registration', 'Attendees register and pay a fee.', 'heroicon-o-credit-card'],
            'tickets' => ['Ticket sales', 'Attendees purchase digital admission tickets.', 'heroicon-o-ticket'],
            'combined' => ['Registration and tickets', 'Offer both registration and ticket purchases.', 'heroicon-o-rectangle-stack'],
            'manual' => ['Manual attendee management', 'Manage attendees without public registration.', 'heroicon-o-users'],
        ] as $value => [$title, $description, $icon])
            <label class="elive-admission-choice">
                <input type="radio" name="{{ $getStatePath() }}" value="{{ $value }}" wire:model.live="{{ $getStatePath() }}" @disabled($isDisabled())>
                <x-filament::icon :icon="$icon" class="elive-admission-icon" />
                <span class="elive-admission-copy">
                    <span class="elive-admission-title">{{ $title }}</span>
                    <span class="elive-admission-description">{{ $description }}</span>
                </span>
                <span class="elive-admission-dot" aria-hidden="true"></span>
            </label>
        @endforeach
    </div>
</x-dynamic-component>
