<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            // Organization information
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('city')->nullable();
            $table->string('country')->default('Tanzania');
            $table->string('timezone')->default('Africa/Dar_es_Salaam');
            $table->string('currency', 10)->default('TZS');

            // General settings
            $table->string('registration_prefix')->nullable();
            $table->string('default_language', 10)->default('en');
            $table->string('date_format')->default('d/m/Y');
            $table->string('time_format')->default('H:i');

            // Subscription
            $table->string('subscription_plan')->nullable();
            $table->string('subscription_status')->nullable();
            $table->timestamp('subscription_starts_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();

            // Limits
            $table->unsignedInteger('maximum_users')->nullable();
            $table->unsignedInteger('maximum_events')->nullable();
            $table->unsignedInteger('maximum_attendees')->nullable();

            // Communication channels
            $table->boolean('sms_enabled')->default(false);
            $table->boolean('email_enabled')->default(false);
            $table->boolean('whatsapp_enabled')->default(false);

            // SMTP
            $table->string('smtp_host')->nullable();
            $table->unsignedInteger('smtp_port')->nullable();
            $table->string('smtp_username')->nullable();
            $table->text('smtp_password')->nullable();
            $table->string('smtp_encryption')->nullable();
            $table->string('mail_from_address')->nullable();
            $table->string('mail_from_name')->nullable();

            // SMS
            $table->string('sms_provider')->nullable();
            $table->string('sms_sender_id')->nullable();
            $table->text('sms_api_key')->nullable();
            $table->text('sms_api_secret')->nullable();

            // WhatsApp
            $table->string('whatsapp_phone_number_id')->nullable();
            $table->string('whatsapp_business_account_id')->nullable();
            $table->text('whatsapp_access_token')->nullable();

            // Additional settings
            $table->jsonb('settings')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table): void {
            $table->dropColumn([
                'description',
                'address',
                'city',
                'country',
                'timezone',
                'currency',
                'registration_prefix',
                'default_language',
                'date_format',
                'time_format',
                'subscription_plan',
                'subscription_status',
                'subscription_starts_at',
                'subscription_ends_at',
                'maximum_users',
                'maximum_events',
                'maximum_attendees',
                'sms_enabled',
                'email_enabled',
                'whatsapp_enabled',
                'smtp_host',
                'smtp_port',
                'smtp_username',
                'smtp_password',
                'smtp_encryption',
                'mail_from_address',
                'mail_from_name',
                'sms_provider',
                'sms_sender_id',
                'sms_api_key',
                'sms_api_secret',
                'whatsapp_phone_number_id',
                'whatsapp_business_account_id',
                'whatsapp_access_token',
                'settings',
            ]);
        });
    }
};