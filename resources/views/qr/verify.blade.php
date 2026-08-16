<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR Verification - eLive Events</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 min-h-screen flex items-center justify-center px-4">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-lg p-6 border border-slate-200">
        <div class="text-center mb-6">
            <h1 class="text-2xl font-bold text-[#161943]">Valid QR Code</h1>
            <p class="text-sm text-slate-500 mt-1">Attendee verification successful</p>
        </div>

        <div class="space-y-4">
            <div>
                <p class="text-xs uppercase text-slate-400">Attendee Name</p>
                <p class="text-lg font-semibold text-slate-900">{{ $attendee->full_name }}</p>
            </div>

            <div>
                <p class="text-xs uppercase text-slate-400">Event</p>
                <p class="text-base font-medium text-slate-800">{{ $attendee->event?->name ?? '-' }}</p>
            </div>

            <div>
                <p class="text-xs uppercase text-slate-400">Category</p>
                <p class="text-base font-medium text-slate-800">{{ $attendee->category?->name ?? '-' }}</p>
            </div>

            <div>
                <p class="text-xs uppercase text-slate-400">Badge Type</p>
                <p class="text-base font-medium text-slate-800">{{ $attendee->badgeType?->name ?? '-' }}</p>
            </div>

            <div>
                <p class="text-xs uppercase text-slate-400">Badge Number</p>
                <p class="text-base font-medium text-slate-800">{{ $attendee->badge_number ?? '-' }}</p>
            </div>

            <div>
                <p class="text-xs uppercase text-slate-400">Status</p>
                <p class="inline-flex mt-1 px-3 py-1 rounded-full text-sm font-medium bg-green-100 text-green-700">
                    {{ ucfirst(str_replace('_', ' ', $attendee->status)) }}
                </p>
            </div>
        </div>
    </div>
</body>
</html>