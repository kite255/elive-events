<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->string('public_theme')->nullable();
            $table->string('venue_address')->nullable();
            $table->string('map_url', 2048)->nullable();
            $table->unsignedSmallInteger('ministry_years')->nullable();
            $table->unsignedSmallInteger('group_members_count')->nullable();
            $table->text('dress_code')->nullable();
            $table->text('seating_policy')->nullable();
            $table->text('age_restriction')->nullable();
            $table->text('ticket_policy')->nullable();
            $table->text('refund_policy')->nullable();
            $table->string('organizer_contact_email')->nullable();
            $table->string('organizer_contact_phone', 40)->nullable();
            $table->string('website_url', 2048)->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('youtube_url', 2048)->nullable();
            $table->string('final_cta_title')->nullable();
            $table->text('final_cta_body')->nullable();
            $table->jsonb('public_highlights')->nullable();
            $table->jsonb('public_gallery')->nullable();
            $table->jsonb('public_faqs')->nullable();
            $table->jsonb('public_speakers')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropColumn([
                'public_theme',
                'venue_address',
                'map_url',
                'ministry_years',
                'group_members_count',
                'dress_code',
                'seating_policy',
                'age_restriction',
                'ticket_policy',
                'refund_policy',
                'organizer_contact_email',
                'organizer_contact_phone',
                'website_url',
                'facebook_url',
                'instagram_url',
                'youtube_url',
                'final_cta_title',
                'final_cta_body',
                'public_highlights',
                'public_gallery',
                'public_faqs',
                'public_speakers',
            ]);
        });
    }
};
