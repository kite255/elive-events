<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('donation_campaigns', function (Blueprint $table): void {
            $table->dateTime('starts_at')->nullable()->after('description');
            $table->dateTime('ends_at')->nullable()->after('starts_at');
            $table->string('venue')->nullable()->after('ends_at');
            $table->string('venue_address')->nullable()->after('venue');
            $table->string('map_url', 2048)->nullable()->after('venue_address');
            $table->string('organizer_contact_phone', 40)->nullable()->after('map_url');
            $table->string('organizer_contact_email')->nullable()->after('organizer_contact_phone');
            $table->json('public_highlights')->nullable()->after('organizer_contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('donation_campaigns', function (Blueprint $table): void {
            $table->dropColumn([
                'starts_at',
                'ends_at',
                'venue',
                'venue_address',
                'map_url',
                'organizer_contact_phone',
                'organizer_contact_email',
                'public_highlights',
            ]);
        });
    }
};
