<?php

namespace App\Http\Controllers\Payments;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

class SelcomWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $rejectionReason = $this->signatureRejectionReason($request);
        if ($rejectionReason !== null) {
            // A single diagnostic per failure category per minute bounds
            // unauthenticated log volume, without logging request identifiers.
            if (Cache::add('selcom:webhook:rejected:' . $rejectionReason, true, 60)) {
                Log::notice('Selcom webhook authentication rejected.', [
                    'reason' => $rejectionReason,
                ]);
            }

            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $reference = trim((string) $request->input('order_id', ''));
        $payment = Payment::query()->where('reference', $reference)->with('gateway')->first();

        if (! $payment || strtolower((string) $payment->gateway?->code) !== 'selcom') {
            return response()->json(['message' => 'Unknown Selcom order'], 404);
        }

        Log::info('Selcom webhook accepted for verification.', [
            'payment_id' => $payment->getKey(),
            'previous_status' => $payment->status,
        ]);

        try {
            // Never trust webhook's payment_status/amount. Query Selcom's
            // authenticated order status before the existing fulfillment path.
            $payments->syncFromGateway($payment);
        } catch (Throwable $e) {
            Log::warning('Selcom webhook provider verification failed.', [
                'payment_id' => $payment->getKey(),
                'exception_class' => $e::class,
            ]);
            report($e);
            return response()->json(['message' => 'Verification temporarily unavailable'], 503);
        }

        $payment->refresh();
        Log::info('Selcom webhook provider verification completed.', [
            'payment_id' => $payment->getKey(),
            'payment_status' => $payment->status,
            'payment_completed' => $payment->isCompleted(),
            'fulfilled' => $payment->isFulfilled(),
        ]);

        return response()->json(['message' => 'Accepted']);
    }

    /**
     * Return a fixed diagnostic category only. No request body, credentials,
     * digest, IP, order reference or exception message is ever logged.
     */
    private function signatureRejectionReason(Request $request): ?string
    {
        $secret = (string) config('services.selcom.api_secret');
        $key = (string) config('services.selcom.api_key');
        $timestamp = (string) $request->header('Timestamp', '');
        $fields = (string) $request->header('Signed-Fields', '');
        $digest = (string) $request->header('Digest', '');
        $authorization = (string) $request->header('Authorization', '');
        $digestMethod = (string) $request->header('Digest-Method', '');

        if ($secret === '' || $key === '') {
            return 'credentials_not_configured';
        }

        if ($timestamp === '' || $fields === '' || $digest === ''
            || $authorization === '' || $digestMethod === '') {
            return 'missing_headers';
        }

        if (! hash_equals('SELCOM ' . base64_encode($key), $authorization)
            || $digestMethod !== 'HS256') {
            return 'invalid_authentication_headers';
        }

        try {
            if (abs(Carbon::parse($timestamp)->diffInSeconds(now(), false)) > 300) {
                return 'invalid_timestamp';
            }
        } catch (Throwable) {
            return 'invalid_timestamp';
        }

        $message = 'timestamp=' . $timestamp;
        $names = explode(',', $fields);
        if (count($names) > 25 || count($names) !== count(array_unique($names))) {
            return 'invalid_signed_fields';
        }

        foreach ($names as $name) {
            if (! preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $name)) {
                return 'invalid_signed_fields';
            }

            if (! $request->exists($name) || ! is_scalar($request->input($name))) {
                return 'missing_signed_field';
            }

            $message .= '&' . $name . '=' . $request->input($name);
        }

        if (! hash_equals(
            base64_encode(hash_hmac('sha256', $message, $secret, true)),
            $digest
        )) {
            return 'invalid_signature';
        }

        return null;
    }
}
