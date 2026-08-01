<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendee_merchandises', function (
            Blueprint $table
        ): void {
            $table->string('payment_method', 50)
                ->nullable()
                ->after('payment_status');

            $table->string('payment_reference')
                ->nullable()
                ->after('payment_method');

            $table->index(
                ['payment_status', 'payment_method'],
                'attendee_merch_payment_status_method_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('attendee_merchandises', function (
            Blueprint $table
        ): void {
            $table->dropIndex(
                'attendee_merch_payment_status_method_index'
            );

            $table->dropColumn([
                'payment_method',
                'payment_reference',
            ]);
        });
    }
};
