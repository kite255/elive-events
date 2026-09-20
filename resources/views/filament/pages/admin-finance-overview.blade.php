<x-filament-panels::page>
    @php
        $metrics = $this->financeMetrics();

        $currency = $metrics['currency'] ?? 'TZS';

        $formatMoney = static function ($value) use ($currency): string {
            return $currency . ' ' . number_format((float) $value, 0);
        };
    @endphp

    <div class="space-y-6">

        {{-- ========================================================= --}}
        {{-- PAGE HEADER --}}
        {{-- ========================================================= --}}

        <div
            class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between"
        >
            <div>
                <div
                    class="mb-2 text-xs font-bold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400"
                >
                    Finance
                </div>

                <h1
                    class="text-3xl font-bold tracking-tight text-gray-950 dark:text-white sm:text-4xl"
                >
                    Finance Overview
                </h1>

                <p
                    class="mt-3 max-w-3xl text-sm text-gray-600 dark:text-gray-400 sm:text-base"
                >
                    Review platform revenue, internal charges, gateway costs,
                    and organizer liabilities across all paid ticket orders.
                </p>
            </div>

            <div>
                <div
                    class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-200"
                >
                    <span
                        class="h-2.5 w-2.5 rounded-full bg-emerald-500"
                    ></span>

                    Finance overview
                </div>
            </div>
        </div>


        {{-- ========================================================= --}}
        {{-- PLATFORM FINANCE OVERVIEW --}}
        {{-- ========================================================= --}}

        <div
            class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900 sm:p-7"
        >
            <div
                class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between"
            >
                <div>
                    <h2
                        class="text-xl font-bold text-gray-950 dark:text-white"
                    >
                        Platform Finance Overview
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                    >
                        Financial position calculated from successfully paid
                        ticket orders.
                    </p>
                </div>

                <div
                    class="rounded-2xl border border-gray-200 bg-gray-50 px-5 py-3 dark:border-white/10 dark:bg-white/5"
                >
                    <div
                        class="text-xs font-bold uppercase tracking-wider text-gray-400"
                    >
                        Currency
                    </div>

                    <div
                        class="mt-1 text-base font-bold text-gray-950 dark:text-white"
                    >
                        {{ $currency }}
                    </div>
                </div>
            </div>
        </div>


        {{-- ========================================================= --}}
        {{-- FINANCE KPI CARDS --}}
        {{-- ========================================================= --}}

        <div
            class="grid gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6"
        >

            {{-- Gross Sales --}}
            <div
                class="relative overflow-hidden rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1 bg-sky-500"
                ></div>

                <div
                    class="flex items-start justify-between gap-4"
                >
                    <div>
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-gray-400"
                        >
                            Gross Sales
                        </div>

                        <div
                            class="mt-4 text-2xl font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['gross_sales'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-sky-50 text-sky-600 dark:bg-sky-500/10 dark:text-sky-400"
                    >
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 6v12m3-9.75C15 7.007 13.657 6 12 6S9 7.007 9 8.25s1.343 2.25 3 2.25 3 1.007 3 2.25S13.657 15 12 15s-3-1.007-3-2.25"
                            />
                        </svg>
                    </div>
                </div>

                <p
                    class="mt-6 text-sm text-gray-500 dark:text-gray-400"
                >
                    Revenue from paid orders
                </p>
            </div>


            {{-- eLive Commission --}}
            <div
                class="relative overflow-hidden rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1 bg-emerald-500"
                ></div>

                <div
                    class="flex items-start justify-between gap-4"
                >
                    <div>
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-gray-400"
                        >
                            eLive Commission
                        </div>

                        <div
                            class="mt-4 text-2xl font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['platform_commission_amount'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400"
                    >
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M9 14.25 11.25 12 9 9.75M13.5 9.75h1.5a2.25 2.25 0 0 1 0 4.5h-1.5"
                            />
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M12 3.75a8.25 8.25 0 1 0 0 16.5 8.25 8.25 0 0 0 0-16.5Z"
                            />
                        </svg>
                    </div>
                </div>

                <p
                    class="mt-6 text-sm text-gray-500 dark:text-gray-400"
                >
                    Platform revenue earned
                </p>
            </div>


            {{-- Gateway Fee --}}
            <div
                class="relative overflow-hidden rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1 bg-orange-500"
                ></div>

                <div
                    class="flex items-start justify-between gap-4"
                >
                    <div>
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-gray-400"
                        >
                            Gateway Fee
                        </div>

                        <div
                            class="mt-4 text-2xl font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['gateway_fee_amount'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-orange-50 text-orange-600 dark:bg-orange-500/10 dark:text-orange-400"
                    >
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M3.75 7.5h16.5m-16.5 3h16.5m-15 6h4.5"
                            />
                            <rect
                                x="3.75"
                                y="5.25"
                                width="16.5"
                                height="13.5"
                                rx="2.25"
                            />
                        </svg>
                    </div>
                </div>

                <p
                    class="mt-6 text-sm text-gray-500 dark:text-gray-400"
                >
                    Payment processing costs
                </p>
            </div>


            {{-- Total Charges --}}
            <div
                class="relative overflow-hidden rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1 bg-rose-500"
                ></div>

                <div
                    class="flex items-start justify-between gap-4"
                >
                    <div>
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-gray-400"
                        >
                            Total Charges
                        </div>

                        <div
                            class="mt-4 text-2xl font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['total_charges'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400"
                    >
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M8.25 6.75h7.5M8.25 10.5h7.5M8.25 14.25h4.5"
                            />
                            <rect
                                x="5.25"
                                y="3.75"
                                width="13.5"
                                height="16.5"
                                rx="2"
                            />
                        </svg>
                    </div>
                </div>

                <p
                    class="mt-6 text-sm text-gray-500 dark:text-gray-400"
                >
                    Commission plus applicable fees
                </p>
            </div>


            {{-- Organizer Net Payable --}}
            <div
                class="relative overflow-hidden rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1 bg-indigo-500"
                ></div>

                <div
                    class="flex items-start justify-between gap-4"
                >
                    <div>
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-gray-400"
                        >
                            Organizer Net Payable
                        </div>

                        <div
                            class="mt-4 text-2xl font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['organizer_net_amount'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400"
                    >
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="M4.5 7.5h15v10.5h-15V7.5Zm3-3h9v3h-9v-3Zm3.75 8.25h1.5"
                            />
                        </svg>
                    </div>
                </div>

                <p
                    class="mt-6 text-sm text-gray-500 dark:text-gray-400"
                >
                    Amount owed to organizers
                </p>
            </div>


            {{-- Paid Orders --}}
            <div
                class="relative overflow-hidden rounded-3xl border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <div
                    class="absolute inset-x-0 top-0 h-1 bg-cyan-500"
                ></div>

                <div
                    class="flex items-start justify-between gap-4"
                >
                    <div>
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-gray-400"
                        >
                            Paid Orders
                        </div>

                        <div
                            class="mt-4 text-2xl font-bold text-gray-950 dark:text-white"
                        >
                            {{ number_format((int) ($metrics['paid_orders'] ?? 0)) }}
                        </div>
                    </div>

                    <div
                        class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-cyan-50 text-cyan-600 dark:bg-cyan-500/10 dark:text-cyan-400"
                    >
                        <svg
                            class="h-6 w-6"
                            viewBox="0 0 24 24"
                            fill="none"
                            stroke="currentColor"
                            stroke-width="1.8"
                        >
                            <path
                                stroke-linecap="round"
                                stroke-linejoin="round"
                                d="m8.25 12 2.25 2.25 5.25-5.25"
                            />
                            <circle
                                cx="12"
                                cy="12"
                                r="8.25"
                            />
                        </svg>
                    </div>
                </div>

                <p
                    class="mt-6 text-sm text-gray-500 dark:text-gray-400"
                >
                    Successfully completed orders
                </p>
            </div>
        </div>


        {{-- ========================================================= --}}
        {{-- INTERNAL FINANCE BREAKDOWN --}}
        {{-- ========================================================= --}}

        <div
            class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]"
        >

            {{-- Breakdown --}}
            <div
                class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <div class="mb-6">
                    <div
                        class="text-xs font-bold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400"
                    >
                        Internal
                    </div>

                    <h2
                        class="mt-1 text-xl font-bold text-gray-950 dark:text-white"
                    >
                        Internal Finance Breakdown
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                    >
                        Complete platform-level financial allocation.
                    </p>
                </div>

                <div
                    class="overflow-hidden rounded-2xl border border-gray-200 dark:border-white/10"
                >

                    <div
                        class="grid grid-cols-2 gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10"
                    >
                        <div
                            class="text-sm text-gray-500 dark:text-gray-400"
                        >
                            Gross Sales
                        </div>

                        <div
                            class="text-right text-sm font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['gross_sales'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10"
                    >
                        <div
                            class="text-sm text-gray-500 dark:text-gray-400"
                        >
                            eLive Commission
                        </div>

                        <div
                            class="text-right text-sm font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['platform_commission_amount'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10"
                    >
                        <div
                            class="text-sm text-gray-500 dark:text-gray-400"
                        >
                            Gateway Fee
                        </div>

                        <div
                            class="text-right text-sm font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['gateway_fee_amount'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-4 border-b border-gray-200 px-5 py-4 dark:border-white/10"
                    >
                        <div
                            class="text-sm text-gray-500 dark:text-gray-400"
                        >
                            Total Charges
                        </div>

                        <div
                            class="text-right text-sm font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['total_charges'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="grid grid-cols-2 gap-4 px-5 py-4"
                    >
                        <div
                            class="text-sm text-gray-500 dark:text-gray-400"
                        >
                            Organizer Net Payable
                        </div>

                        <div
                            class="text-right text-sm font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['organizer_net_amount'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>


            {{-- Financial Position --}}
            <div
                class="rounded-3xl border border-gray-200 bg-white p-6 shadow-sm dark:border-white/10 dark:bg-gray-900"
            >
                <div>
                    <div
                        class="text-xs font-bold uppercase tracking-[0.16em] text-gray-500 dark:text-gray-400"
                    >
                        Position
                    </div>

                    <h2
                        class="mt-1 text-xl font-bold text-gray-950 dark:text-white"
                    >
                        Financial Position
                    </h2>

                    <p
                        class="mt-1 text-sm text-gray-600 dark:text-gray-400"
                    >
                        High-level relationship between collected revenue,
                        platform charges, and organizer liabilities.
                    </p>
                </div>

                <div class="mt-6 space-y-4">

                    <div
                        class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5"
                    >
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-gray-400"
                        >
                            Total Collected
                        </div>

                        <div
                            class="mt-2 text-xl font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['gross_sales'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5"
                    >
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-gray-400"
                        >
                            Total Charges
                        </div>

                        <div
                            class="mt-2 text-xl font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['total_charges'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl bg-gray-50 p-4 dark:bg-white/5"
                    >
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-gray-400"
                        >
                            Organizer Liability
                        </div>

                        <div
                            class="mt-2 text-xl font-bold text-gray-950 dark:text-white"
                        >
                            {{ $formatMoney($metrics['organizer_net_amount'] ?? 0) }}
                        </div>
                    </div>

                    <div
                        class="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 dark:border-emerald-500/20 dark:bg-emerald-500/10"
                    >
                        <div
                            class="text-xs font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-400"
                        >
                            eLive Revenue
                        </div>

                        <div
                            class="mt-2 text-xl font-bold text-emerald-800 dark:text-emerald-300"
                        >
                            {{ $formatMoney($metrics['platform_commission_amount'] ?? 0) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-panels::page>