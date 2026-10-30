<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Throwable;

class SelcomWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        if (! $this->isSignedRequest($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $reference = trim((string) $request->input('order_id', ''));
        $payment = Payment::query()->where('reference', $reference)->with('gateway')->first();

        if (! $payment || strtolower((string) $payment->gateway?->code) !== 'selcom') {
            return response()->json(['message' => 'Unknown Selcom order'], 404);
        }

        try {
            // Never trust webhook's payment_status/amount. Query Selcom's
            // authenticated order status before the existing fulfillment path.
            $payments->syncFromGateway($payment);
        } catch (Throwable $e) {
            report($e);
            return response()->json(['message' => 'Verification temporarily unavailable'], 503);
        }

        return response()->json(['message' => 'Accepted']);
    }

    private function isSignedRequest(Request $request): bool
    {
        $secret = (string) config('services.selcom.api_secret');
        $key = (string) config('services.selcom.api_key');
        $timestamp = (string) $request->header('Timestamp', '');
        $fields = (string) $request->header('Signed-Fields', '');
        $digest = (string) $request->header('Digest', '');

        if ($secret === '' || $key === '' || $timestamp === ''
            || $fields === '' || $digest === ''
            || (string) $request->header('Authorization') !== 'SELCOM ' . base64_encode($key)
            || (string) $request->header('Digest-Method') !== 'HS256') {
            return false;
        }

        try {
            if (abs(Carbon::parse($timestamp)->diffInSeconds(now(), false)) > 300) {
                return false;
            }
        } catch (Throwable) {
            return false;
        }

        $message = 'timestamp=' . $timestamp;
        $names = explode(',', $fields);
        if (count($names) > 25 || count($names) !== count(array_unique($names))) {
            return false;
        }

        foreach ($names as $name) {
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)
                || ! $request->exists($name)
                || ! is_scalar($request->input($name))) {
                return false;
            }
            $message .= '&' . $name . '=' . $request->input($name);
        }

        return hash_equals(
            base64_encode(hash_hmac('sha256', $message, $secret, true)),
            $digest
        );
    }
}
