<x-filament-panels::page>
    @php($orders = $this->successfulOrders())
    <div class="rounded-xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="border-b border-gray-200 px-5 py-4 dark:border-gray-700">
            <h2 class="text-lg font-semibold">Paid merchandise purchases</h2>
            <p class="mt-1 text-sm text-gray-500">Public Shop and Attendee Merchandise payments in one report. Pending and expired reservations are excluded.</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[950px] text-left text-sm">
                <thead class="bg-gray-50 text-gray-600 dark:bg-gray-800 dark:text-gray-200">
                    <tr>
                        @foreach(['Source', 'Reference', 'Event', 'Product', 'Customer', 'Phone', 'Qty', 'Amount', 'Ordered'] as $heading)
                            <th scope="col" class="whitespace-nowrap px-4 py-3 font-semibold">{{ $heading }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-700">
                    @forelse($orders as $order)
                        <tr>
                            <td class="whitespace-nowrap px-4 py-3">{{ $order->source }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ $order->reference }}</td>
                            <td class="px-4 py-3">{{ $order->event_name }}</td>
                            <td class="px-4 py-3">{{ $order->product_name ?? 'Merchandise' }}</td>
                            <td class="px-4 py-3">{{ $order->customer_name ?? '—' }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ $order->phone ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $order->quantity }}</td>
                            <td class="whitespace-nowrap px-4 py-3 font-semibold">{{ $order->currency ?: 'TZS' }} {{ number_format((float) $order->total, 0) }}</td>
                            <td class="whitespace-nowrap px-4 py-3">{{ \Illuminate\Support\Carbon::parse($order->ordered_at)->format('d M Y, H:i') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="9" class="px-5 py-12 text-center text-gray-500">No successful merchandise payments yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($orders->hasPages())
            <div class="border-t border-gray-200 px-5 py-4 dark:border-gray-700">{{ $orders->links() }}</div>
        @endif
    </div>
</x-filament-panels::page>
