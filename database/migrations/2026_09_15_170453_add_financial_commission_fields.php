<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table(
            'event_payment_settings',
            function (Blueprint $table): void {
                $table
                    ->decimal(
                        'platform_commission_rate',
                        5,
                        2
                    )
                    ->default(7.00);

                $table
                    ->decimal(
                        'gateway_fee_rate',
                        5,
                        2
                    )
                    ->default(0.00);

                $table
                    ->string(
                        'gateway_fee_bearer',
                        20
                    )
                    ->default('organizer');
            }
        );

        Schema::table(
            'ticket_orders',
            function (Blueprint $table): void {
                $table
                    ->decimal(
                        'gross_amount',
                        15,
                        2
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'platform_commission_rate',
                        5,
                        2
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'platform_commission_amount',
                        15,
                        2
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'gateway_fee_rate',
                        5,
                        2
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'gateway_fee_amount',
                        15,
                        2
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'total_charges',
                        15,
                        2
                    )
                    ->nullable();

                $table
                    ->decimal(
                        'organizer_net_amount',
                        15,
                        2
                    )
                    ->nullable();

                $table
                    ->timestamp(
                        'financial_snapshot_at'
                    )
                    ->nullable();
            }
        );
    }

    public function down(): void
    {
        Schema::table(
            'ticket_orders',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'gross_amount',
                    'platform_commission_rate',
                    'platform_commission_amount',
                    'gateway_fee_rate',
                    'gateway_fee_amount',
                    'total_charges',
                    'organizer_net_amount',
                    'financial_snapshot_at',
                ]);
            }
        );

        Schema::table(
            'event_payment_settings',
            function (Blueprint $table): void {
                $table->dropColumn([
                    'platform_commission_rate',
                    'gateway_fee_rate',
                    'gateway_fee_bearer',
                ]);
            }
        );
    }
};