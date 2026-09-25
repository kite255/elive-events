<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex,nofollow">
    <title>Ticket Upgrade | {{ $upgrade->event?->name ?? 'eLive Events' }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <main class="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:py-16">
        <div class="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
            <div class="border-b border-slate-100 px-6 py-7 sm:px-8">
                <p class="text-sm font-semibold uppercase tracking-[0.18em] text-orange-600">Ticket Upgrade</p>
                <h1 class="mt-2 text-2xl font-bold sm:text-3xl">{{ $upgrade->event?->name ?? 'eLive Event' }}</h1>
                <p class="mt-2 text-sm text-slate-500">Upgrade reference: {{ $upgrade->reference }}</p>
            </div>

            <div class="space-y-7 px-6 py-7 sm:px-8">
                @if (session('status'))
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('status') }}
                    </div>
                @endif

                @if ($errors->any())
                    <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                        {{ $errors->first() }}
                    </div>
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="rounded-2xl border border-slate-200 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Current Ticket</p>
                        <p class="mt-2 text-xl font-bold">{{ $upgrade->fromTicketType?->name ?? 'Ticket' }}</p>
                        <p class="mt-1 text-sm text-slate-500">{{ $upgrade->currency }} {{ number_format((float) $upgrade->original_price, 0) }}</p>
                    </div>

                    <div class="rounded-2xl border border-orange-200 bg-orange-50 p-5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-orange-700">Upgrade To</p>
                        <p class="mt-2 text-xl font-bold">{{ $upgrade->toTicketType?->name ?? 'Upgraded Ticket' }}</p>
                        <p class="mt-1 text-sm text-orange-700">{{ $upgrade->currency }} {{ number_format((float) $upgrade->target_price, 0) }}</p>
                    </div>
                </div>

                <div class="rounded-2xl bg-slate-950 p-6 text-white">
                    <div class="flex items-end justify-between gap-4">
                        <div>
                            <p class="text-sm text-slate-300">Balance due</p>
                            <p class="mt-1 text-3xl font-bold">{{ $upgrade->currency }} {{ number_format((float) $upgrade->upgrade_amount, 0) }}</p>
                        </div>
                        <span class="rounded-full bg-white/10 px-3 py-1 text-xs font-semibold uppercase tracking-wide">
                            {{ str($upgrade->status)->replace('_', ' ')->headline() }}
                        </span>
                    </div>
                </div>

                @if ($maskedEmail || $maskedPhone)
                    <div>
                        <h2 class="text-sm font-semibold text-slate-800">Purchaser contact</h2>
                        <div class="mt-2 space-y-1 text-sm text-slate-600">
                            @if ($maskedEmail)
                                <p>Email: {{ $maskedEmail }}</p>
                            @endif
                            @if ($maskedPhone)
                                <p>Phone: {{ $maskedPhone }}</p>
                            @endif
                        </div>
                    </div>
                @endif

                @if ($upgrade->isCompleted())
                    <div class="rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                        <p class="font-semibold text-emerald-900">Upgrade completed</p>
                        <p class="mt-1 text-sm text-emerald-800">Your existing ticket has been updated to {{ $upgrade->toTicketType?->name }}. Your ticket number and QR access remain tied to the same ticket.</p>
                        @if ($upgrade->order)
                            <a href="{{ route('public.ticket-orders.show', ['token' => $upgrade->order->public_token]) }}" class="mt-4 inline-flex rounded-xl bg-emerald-700 px-5 py-3 text-sm font-semibold text-white">
                                View Updated Ticket
                            </a>
                        @endif
                    </div>
                @elseif ($upgrade->isExpired())
                    <div class="rounded-2xl border border-amber-200 bg-amber-50 p-5 text-sm text-amber-900">
                        This upgrade request has expired. Contact the event organizer if you still want to change your ticket type.
                    </div>
                @else
                    @if ($latestPayment?->isCompleted())
                        <div class="rounded-2xl border border-blue-200 bg-blue-50 p-5 text-sm text-blue-900">
                            Your payment has been confirmed and the ticket upgrade is being finalized. Refresh this page shortly.
                        </div>
                    @else
                        <form method="POST" action="{{ route('tickets.upgrades.pay', ['token' => $upgrade->public_token]) }}">
                            @csrf
                            <button type="submit" class="w-full rounded-2xl bg-orange-600 px-6 py-4 text-base font-bold text-white transition hover:bg-orange-700">
                                Pay {{ $upgrade->currency }} {{ number_format((float) $upgrade->upgrade_amount, 0) }}
                            </button>
                        </form>
                        <p class="text-center text-xs text-slate-500">Payment confirmation is verified securely with the payment provider before your ticket is upgraded.</p>
                    @endif
                @endif
            </div>
        </div>
    </main>
</body>
</html>
