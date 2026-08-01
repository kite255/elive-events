<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Check-in Successful - eLive Events</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-lg bg-white rounded-3xl shadow-xl border border-green-200 overflow-hidden">

        <div class="bg-green-600 px-6 py-5 text-center">
            <div class="mx-auto mb-3 h-16 w-16 rounded-full bg-white flex items-center justify-center">
                <span class="text-green-600 text-4xl font-bold">✓</span>
            </div>

            <h1 class="text-2xl font-bold text-white">
                Check-in Successful
            </h1>

            <p class="mt-1 text-green-50 text-sm">
                Attendance has been recorded successfully.
            </p>
        </div>

        <div class="p-6 space-y-5">
            <div class="text-center">
                <h2 class="text-xl font-bold text-slate-900">
                    {{ $attendee->full_name }}
                </h2>

                <p class="text-slate-500 text-sm mt-1">
                    Welcome to {{ $attendee->event?->name ?? 'the event' }}
                </p>
            </div>

            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Event</span>
                    <span class="text-slate-900 text-right">
                        {{ $attendee->event?->name ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Venue</span>
                    <span class="text-slate-900 text-right">
                        {{ $attendee->event?->venue ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Phone</span>
                    <span class="text-slate-900 text-right">
                        {{ $attendee->phone ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Organization</span>
                    <span class="text-slate-900 text-right">
                        {{ $attendee->organization_name ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Position</span>
                    <span class="text-slate-900 text-right">
                        {{ $attendee->position ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Category</span>
                    <span class="text-slate-900 text-right">
                        {{ $attendee->category?->name ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Badge Type</span>
                    <span class="text-slate-900 text-right">
                        {{ $attendee->badgeType?->name ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Badge Number</span>
                    <span class="text-slate-900 text-right font-bold">
                        {{ $attendee->badge_number ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Checked In At</span>
                    <span class="text-slate-900 text-right">
                        {{ $checkIn?->checked_in_at?->format('d M Y, H:i') ?? $attendee->checked_in_at?->format('d M Y, H:i') ?? 'N/A' }}
                    </span>
                </div>
            </div>

            <div class="rounded-xl bg-green-50 border border-green-200 p-4 text-center">
                <p class="text-sm font-semibold text-green-800">
                    Entry approved. Allow attendee to proceed.
                </p>
            </div>

            <div class="text-center">
                <a href="/admin/manual-check-in"
                   class="inline-flex items-center justify-center rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800">
                    Go to Manual Check-In
                </a>
            </div>
        </div>
    </div>
</body>
</html>