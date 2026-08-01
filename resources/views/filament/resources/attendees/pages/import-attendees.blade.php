<x-filament-panels::page>
    <div class="space-y-6">
        <div style="
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        ">
            <h2 style="font-size: 22px; font-weight: 800; color: #0f172a; margin: 0;">
                Import Attendees
            </h2>

            <p style="margin-top: 6px; color: #64748b; font-size: 14px;">
                Upload an Excel file to add attendees into the selected event.
            </p>

            @if ($eventName)
                <div style="
                    margin-top: 14px;
                    display: inline-flex;
                    background: #eff6ff;
                    color: #1d4ed8;
                    border: 1px solid #bfdbfe;
                    border-radius: 999px;
                    padding: 6px 12px;
                    font-size: 13px;
                    font-weight: 700;
                ">
                    Event: {{ $eventName }}
                </div>
            @endif
        </div>

        <div style="
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 18px;
            padding: 22px;
            box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
        ">
            <form wire:submit.prevent="import" class="space-y-6">
                {{ $this->form }}

                <x-filament::button
                    type="submit"
                    icon="heroicon-o-arrow-up-tray"
                    color="success"
                >
                    Import Attendees
                </x-filament::button>
            </form>
        </div>

        @if ($errorReportUrl)
            <div style="
                background: #fff7ed;
                border: 1px solid #fed7aa;
                border-radius: 18px;
                padding: 22px;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.08);
            ">
                <h3 style="font-size: 18px; font-weight: 800; color: #9a3412; margin: 0;">
                    Import completed with skipped rows
                </h3>

                <p style="margin-top: 8px; color: #7c2d12; font-size: 14px;">
                    Some attendees were not imported. Download the error report, fix the affected rows, and upload the corrected file again.
                </p>

                <a
                    href="{{ $errorReportUrl }}"
                    target="_blank"
                    style="
                        display: inline-flex;
                        margin-top: 14px;
                        background: #ea580c;
                        color: #ffffff;
                        border-radius: 12px;
                        padding: 10px 16px;
                        font-weight: 800;
                        text-decoration: none;
                    "
                >
                    Download Error Report
                </a>
            </div>
        @endif

        <div style="
            background: #f8fafc;
            border: 1px dashed #cbd5e1;
            border-radius: 18px;
            padding: 22px;
        ">
            <h3 style="font-size: 16px; font-weight: 800; color: #0f172a; margin: 0 0 10px 0;">
                Excel Columns
            </h3>

            <p style="margin-top: 0; margin-bottom: 14px; color: #64748b; font-size: 14px;">
                Use the eLive Excel template. The column names must remain on row 5, and attendee data should start from row 6.
            </p>

            <div style="overflow-x: auto;">
                <table style="width: 100%; border-collapse: collapse; font-size: 14px;">
                    <thead>
                        <tr>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #e2e8f0;">Column</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #e2e8f0;">Required</th>
                            <th style="text-align: left; padding: 10px; border-bottom: 1px solid #e2e8f0;">Example</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">full_name</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Yes</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">John Michael</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">phone</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">No</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">255712345678</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">email</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">No</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">john@example.com</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">organization_name</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">No</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">ABC Company</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">position</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">No</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">Manager</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">category</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">No</td>
                            <td style="padding: 10px; border-bottom: 1px solid #e2e8f0;">VIP</td>
                        </tr>
                        <tr>
                            <td style="padding: 10px;">badge_type</td>
                            <td style="padding: 10px;">No</td>
                            <td style="padding: 10px;">VIP Badge</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-filament-panels::page>