<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div class="elive-admission-cards" role="radiogroup" aria-label="Admission method">
        <style>
            .elive-admission-cards{display:grid;gap:12px}
            .elive-admission-choice{position:relative;display:flex;align-items:center;gap:12px;padding:16px 14px;min-height:74px;border:1px solid #e2e8f0;border-radius:8px;background:#fff;cursor:pointer;transition:background .15s,border-color .15s}
            .elive-admission-choice:has(input:checked){border-color:#007ab2;box-shadow:inset 0 0 0 1px #007ab2;background:#eef7fc}
            .elive-admission-choice:focus-within{outline:2px solid #007ab2;outline-offset:3px}
            .elive-admission-choice input{position:absolute;opacity:0;width:1px;height:1px}
            .elive-admission-icon{color:#007ab2;flex-shrink:0;width:20px;height:20px}
            .elive-admission-copy{flex:1;min-width:0}
            .elive-admission-title{display:block;font-size:15px;font-weight:600;color:#161943}
            .elive-admission-description{display:block;margin-top:4px;font-size:14px;line-height:1.45;color:#64748b}
            .elive-admission-dot{width:16px;height:16px;border-radius:50%;background:#cbd5e1;flex-shrink:0}
            .elive-admission-choice:has(input:checked) .elive-admission-dot{background:transparent;border:2px solid #007ab2;display:grid;place-items:center}
            .elive-admission-choice:has(input:checked) .elive-admission-dot::after{content:'';width:6px;height:6px;border-radius:50%;background:#007ab2}
            .dark .elive-admission-choice{background:#18212f;border-color:#394557}
            .dark .elive-admission-choice:has(input:checked){background:#142e40;border-color:#38bdf8}
            .dark .elive-admission-title{color:#f1f5f9}
            .dark .elive-admission-description{color:#cbd5e1}
        </style>
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
