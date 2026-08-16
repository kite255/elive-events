<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('communication_templates', function (Blueprint $table) {
            if (! Schema::hasColumn('communication_templates', 'key')) {
                $table->string('key')
                    ->nullable()
                    ->after('name');

                $table->index(
                    ['organization_id', 'channel', 'key'],
                    'communication_templates_org_channel_key_index'
                );
            }
        });

        /*
        |--------------------------------------------------------------------------
        | Backfill known machine-style names
        |--------------------------------------------------------------------------
        |
        | Existing templates continue to work, but users will now see friendly
        | names while application code can rely on the stable `key`.
        |
        */

        $templates = [
            'registration_confirmed_email' => 'Registration Confirmation',
            'event_update_email' => 'Event Update',
            'event_reminder_email' => 'Event Reminder',
            'venue_change_email' => 'Venue Change Notice',
            'schedule_change_email' => 'Schedule Change Notice',
            'general_announcement_email' => 'General Announcement',
            'badge_delivery_email' => 'Badge Delivery',
            'payment_confirmation_email' => 'Payment Confirmation',
            'payment_reminder_email' => 'Payment Reminder',
            'waitlist_update_email' => 'Waitlist Update',
            'certificate_delivery_email' => 'Certificate Delivery',
            'feedback_request_email' => 'Feedback Request',
            'post_event_thank_you_email' => 'Post-Event Thank You',

            'registration_received_sms' => 'Registration Received',
            'registration_confirmed_sms' => 'Registration Confirmation',
            'event_update_sms' => 'Event Update',
            'event_reminder_sms' => 'Event Reminder',
            'venue_change_sms' => 'Venue Change Notice',
            'schedule_change_sms' => 'Schedule Change Notice',
            'general_announcement_sms' => 'General Announcement',

            'registration_received_whatsapp' => 'Registration Received',
            'registration_confirmed_whatsapp' => 'Registration Confirmation',
            'event_update_whatsapp' => 'Event Update',
            'event_reminder_whatsapp' => 'Event Reminder',
            'venue_change_whatsapp' => 'Venue Change Notice',
            'schedule_change_whatsapp' => 'Schedule Change Notice',
            'general_announcement_whatsapp' => 'General Announcement',
        ];

        foreach ($templates as $key => $friendlyName) {
            DB::table('communication_templates')
                ->where('name', $key)
                ->whereNull('key')
                ->update([
                    'key' => $key,
                    'name' => $friendlyName,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('communication_templates', function (Blueprint $table) {
            if (Schema::hasColumn('communication_templates', 'key')) {
                $table->dropIndex(
                    'communication_templates_org_channel_key_index'
                );

                $table->dropColumn('key');
            }
        });
    }
};
