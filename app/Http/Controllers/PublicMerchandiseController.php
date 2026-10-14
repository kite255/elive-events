<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class PublicMerchandiseController extends Controller
{
    public function index(Event $event): View
    {
        abort_if(in_array($event->status, ['draft', 'cancelled'], true), 404);

        return view('public.merchandise.index', [
            'event' => $event,
            'products' => MerchandiseProduct::query()->where('event_id', $event->id)
                ->where('is_active', true)->whereNotNull('price_tzs')
                ->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, Event $event, MerchandiseProduct $product): RedirectResponse
    {
        abort_if(in_array($event->status, ['draft', 'cancelled'], true), 404);
        abort_unless($product->event_id === $event->id && $product->is_active && $product->price_tzs !== null, 404);

        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:150'],
            'buyer_phone' => ['required', 'string', 'max:30'],
            'buyer_email' => ['nullable', 'email', 'max:254'],
            'color' => ['required', 'string', Rule::in($product->colors ?? [])],
            'size' => ['required', 'string', Rule::in($product->sizes ?? [])],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'fulfillment_method' => ['required', Rule::in(['event_pickup'])],
        ]);

        // Re-read server-side price; never trust a client-submitted amount.
        $order = DB::transaction(function () use ($product, $event, $data): MerchandiseOrder {
            $current = MerchandiseProduct::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($current->event_id === $event->id && $current->is_active && $current->price_tzs !== null, 422);
            abort_unless(in_array($data['color'], $current->colors ?? [], true) && in_array($data['size'], $current->sizes ?? [], true), 422);
            $price = (int) $current->price_tzs;
            abort_unless($price > 0 && $price <= 100000000, 422);
            return MerchandiseOrder::create([
                'event_id' => $event->id,
                'merchandise_product_id' => $current->id,
                'public_token' => Str::random(48),
                'order_number' => 'ELV-MER-' . strtoupper(Str::random(12)),
                'buyer_name' => $data['buyer_name'],
                'buyer_phone' => $data['buyer_phone'],
                'buyer_email' => $data['buyer_email'] ?? null,
                'color' => $data['color'],
                'size' => $data['size'],
                'quantity' => $data['quantity'],
                'unit_price_tzs' => $price,
                'total_tzs' => $price * (int) $data['quantity'],
                'fulfillment_method' => 'event_pickup',
                'status' => 'pending_payment',
            ]);
        });

        return redirect()->route('public.merchandise.orders.show', $order->public_token);
    }

    public function showOrder(string $token): View
    {
        $order = MerchandiseOrder::query()->with(['event', 'product'])
            ->where('public_token', $token)->firstOrFail();

        return view('public.merchandise.order', compact('order'));
    }
}
