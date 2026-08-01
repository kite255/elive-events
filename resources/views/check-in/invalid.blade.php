<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invalid Check-in QR - eLive Events</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="min-h-screen bg-slate-100 flex items-center justify-center px-4 py-8">
    <div class="w-full max-w-lg bg-white rounded-3xl shadow-xl border border-red-200 overflow-hidden">

        <div class="bg-red-600 px-6 py-5 text-center">
            <div class="mx-auto mb-3 h-16 w-16 rounded-full bg-white flex items-center justify-center">
                <span class="text-red-600 text-4xl font-bold">×</span>
            </div>

            <h1 class="text-2xl font-bold text-white">
                Invalid QR Code
            </h1>

            <p class="mt-1 text-red-50 text-sm">
                This QR code cannot be used for check-in.
            </p>
        </div>

        <div class="p-6 space-y-5">
            <div class="text-center">
                <h2 class="text-xl font-bold text-slate-900">
                    Check-in Failed
                </h2>

                <p class="text-slate-500 text-sm mt-2">
                    This QR code is invalid, expired, removed, or not recognized by eLive Events.
                </p>
            </div>

            <div class="rounded-2xl bg-slate-50 border border-slate-200 p-4 space-y-3 text-sm">
                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Status</span>
                    <span class="text-red-700 font-bold text-right">
                        Rejected
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Reason</span>
                    <span class="text-slate-900 text-right">
                        Invalid or expired QR token
                    </span>
                </div>

                <div class="flex justify-between gap-4">
                    <span class="font-semibold text-slate-600">Action Required</span>
                    <span class="text-slate-900 text-right">
                        Verify attendee manually
                    </span>
                </div>
            </div>

            <div class="rounded-xl bg-red-50 border border-red-200 p-4 text-center">
                <p class="text-sm font-semibold text-red-800">
                    Do not allow entry until the attendee is verified by event admin.
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