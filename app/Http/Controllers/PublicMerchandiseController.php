<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\MerchandiseOrder;
use App\Models\MerchandiseProduct;
use App\Models\MerchandiseVariant;
use App\Services\Payments\PaymentService;
use RuntimeException;
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
        abort_if(in_array($event->status, ['draft', 'cancelled'], true) || ! $event->merchandise_section_enabled, 404);

        return view('public.merchandise.index', [
            'event' => $event,
            'products' => MerchandiseProduct::query()->where('event_id', $event->id)
                ->where('is_active', true)->whereNotNull('price_tzs')
                ->orderBy('sort_order')->get(),
        ]);
    }

    public function store(Request $request, Event $event, MerchandiseProduct $product): RedirectResponse
    {
        abort_if(in_array($event->status, ['draft', 'cancelled'], true) || ! $event->merchandise_section_enabled, 404);
        abort_unless($product->event_id === $event->id && $product->is_active && $product->price_tzs !== null, 404);

        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:150'],
            'buyer_phone' => ['required', 'string', 'max:30'],
            'buyer_email' => ['nullable', 'email', 'max:254'],
            'color' => [($product->event_merchandise_id || empty($product->colors)) ? 'nullable' : 'required', 'string', Rule::in($product->colors ?? [])],
            'size' => [($product->event_merchandise_id || empty($product->sizes)) ? 'nullable' : 'required', 'string', Rule::in($product->sizes ?? [])],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'merchandise_variant_id' => ['nullable', 'integer'],
            'fulfillment_method' => ['required', Rule::in(['event_pickup'])],
        ]);

        // Re-read server-side price; never trust a client-submitted amount.
        $order = DB::transaction(function () use ($product, $event, $data): MerchandiseOrder {
            $current = MerchandiseProduct::query()->lockForUpdate()->findOrFail($product->id);
            abort_unless($current->event_id === $event->id && $current->is_active && $current->price_tzs !== null, 422);
            abort_unless($current->event_merchandise_id || ((empty($current->colors) || in_array($data['color'] ?? null, $current->colors, true)) && (empty($current->sizes) || in_array($data['size'] ?? null, $current->sizes, true))), 422);
            $price = (int) $current->price_tzs;
            abort_unless($price > 0 && $price <= 100000000, 422);
            // Linked attendee merchandise requires shared variant reservations before checkout.
            $variant = null;
            if ($current->event_merchandise_id !== null) {
                abort_unless(filled($data['merchandise_variant_id'] ?? null), 422, 'Select a merchandise variant.');
                $variant = MerchandiseVariant::query()->lockForUpdate()
                    ->where('event_merchandise_id', $current->event_merchandise_id)
                    ->findOrFail($data['merchandise_variant_id']);
                abort_unless($variant->is_active && $variant->merchandise?->is_active, 422);
                abort_unless($variant->currency === 'TZS', 422, 'Only TZS variants are supported.');
                abort_unless($variant->remainingQuantity() >= (int) $data['quantity'], 422, 'Insufficient variant stock.');
                $price = (int) $variant->price;
                abort_unless($price > 0 && $price <= 100000000, 422);
            }
            if ($variant === null) {
                abort_unless($current->stock_quantity >= (int) $data['quantity'], 422, 'Insufficient stock.');
                $current->decrement('stock_quantity', (int) $data['quantity']);
            }
            return MerchandiseOrder::create([
                'event_id' => $event->id,
                'merchandise_product_id' => $current->id,
                'merchandise_variant_id' => $variant?->id,
                'public_token' => Str::random(48),
                'order_number' => 'ELV-MER-' . strtoupper(Str::random(12)),
                'buyer_name' => $data['buyer_name'],
                'buyer_phone' => $data['buyer_phone'],
                'buyer_email' => $data['buyer_email'] ?? null,
                'color' => $variant?->color_name ?? ($data['color'] ?? null),
                'size' => $variant?->size ?? ($data['size'] ?? null),
                'quantity' => $data['quantity'],
                'unit_price_tzs' => $price,
                'total_tzs' => $price * (int) $data['quantity'],
                'fulfillment_method' => 'event_pickup',
                'status' => 'pending_payment',
                'expires_at' => now()->addMinutes(15),
            ]);
        });

        return redirect()->route('public.merchandise.orders.show', $order->public_token);
    }

    public function payOrder(string $token, PaymentService $paymentService): RedirectResponse
    {
        $order = MerchandiseOrder::query()->where('public_token', $token)->firstOrFail();

        if ($order->status !== 'pending_payment' || ! $order->expires_at || $order->expires_at->isPast()) {
            return redirect()->route('public.merchandise.orders.show', $token)
                ->with('payment_error', 'This reservation is no longer available for payment.');
        }

        try {
            $payment = $paymentService->createForMerchandiseOrder($order);
        } catch (RuntimeException $exception) {
            return redirect()->route('public.merchandise.orders.show', $token)
                ->with('payment_error', 'Payment is currently unavailable. Please try again or contact the organizer.');
        }

        return redirect()->route('payments.pay', ['payment' => $payment->reference]);
    }

    public function showOrder(string $token): View
    {
        $order = MerchandiseOrder::query()->with(['event', 'product', 'payment'])
            ->where('public_token', $token)->firstOrFail();

        return view('public.merchandise.order', compact('order'));
    }
}
