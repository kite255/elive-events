<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'ticket_orders',
            function (Blueprint $table): void {
                $table
                    ->string(
                        'public_token',
                        64
                    )
                    ->nullable()
                    ->unique();
            }
        );

        /*
         * Backfill any existing ticket orders so older
         * records also receive secure public access tokens.
         */
        DB::table('ticket_orders')
            ->whereNull('public_token')
            ->orderBy('id')
            ->chunkById(
                100,
                function ($orders): void {
                    foreach ($orders as $order) {
                        do {
                            $token =
                                Str::random(48);

                            $exists =
                                DB::table(
                                    'ticket_orders'
                                )
                                    ->where(
                                        'public_token',
                                        $token
                                    )
                                    ->exists();
                        } while ($exists);

                        DB::table(
                            'ticket_orders'
                        )
                            ->where(
                                'id',
                                $order->id
                            )
                            ->update([
                                'public_token' =>
                                    $token,
                            ]);
                    }
                }
            );
    }

    public function down(): void
    {
        Schema::table(
            'ticket_orders',
            function (Blueprint $table): void {
                $table->dropColumn(
                    'public_token'
                );
            }
        );
    }
};