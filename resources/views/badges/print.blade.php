<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Print Badges - eLive Events</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <style>
        @page {
            size: A4 portrait;
            margin: 10mm;
        }

        body {
            margin: 0;
            background: #f1f5f9;
            font-family: Arial, sans-serif;
        }

        .toolbar {
            position: sticky;
            top: 0;
            z-index: 20;
            background: #0B1F3A;
            color: #ffffff;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
        }

        .toolbar h1 {
            margin: 0;
            font-size: 18px;
            font-weight: 800;
        }

        .toolbar-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .toolbar button,
        .toolbar a {
            border: none;
            background: #F99A12;
            color: #111827;
            font-weight: 800;
            padding: 10px 16px;
            border-radius: 10px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .toolbar a {
            background: #ffffff;
            color: #0B1F3A;
        }

        .page {
            padding: 24px;
        }

        .badge-grid {
            display: grid;
            grid-template-columns: repeat(2, 420px);
            gap: 24px;
            justify-content: center;
            align-items: start;
        }

        .badge-card {
            background: #ffffff;
            border-radius: 16px;
            padding: 10px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
            break-inside: avoid;
            page-break-inside: avoid;
        }

        .badge-card img {
            width: 420px;
            height: 620px;
            display: block;
        }

        .empty {
            max-width: 600px;
            margin: 80px auto;
            background: #ffffff;
            padding: 30px;
            border-radius: 18px;
            text-align: center;
            color: #64748b;
            font-weight: 700;
        }

        @media print {
            body {
                background: #ffffff;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }

            .toolbar {
                display: none;
            }

            .page {
                padding: 0;
            }

            .badge-grid {
                display: grid;
                grid-template-columns: repeat(2, 90mm);
                gap: 6mm;
                justify-content: center;
                align-items: start;
            }

            .badge-card {
                box-shadow: none;
                border-radius: 0;
                padding: 0;
                margin: 0;
                background: transparent;
                break-inside: avoid;
                page-break-inside: avoid;
            }

            .badge-card img {
                width: 90mm;
                height: auto;
                display: block;
            }

            .empty {
                box-shadow: none;
            }
        }

        @media screen and (max-width: 950px) {
            .badge-grid {
                grid-template-columns: 1fr;
            }

            .badge-card {
                width: fit-content;
                margin: 0 auto;
            }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <h1>Print Badges — {{ $attendees->count() }} selected</h1>

        <div class="toolbar-actions">
            <a href="{{ url('/admin/attendees') }}">
                Back to Attendees
            </a>

            <button onclick="window.print()">
                Print Badges
            </button>
        </div>
    </div>

    <div class="page">
        @if ($attendees->isEmpty())
            <div class="empty">
                No attendees selected for printing.
            </div>
        @else
            <div class="badge-grid">
                @foreach ($attendees as $attendee)
                    <div class="badge-card">
                        <img
                            src="{{ asset('storage/' . $attendee->badge_path) }}"
                            alt="Badge for {{ $attendee->full_name }}"
                        >
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</body>
</html>