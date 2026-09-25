<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\TicketUpgrade;
use App\Services\Payments\TicketUpgradePaymentService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

class PublicTicketUpgradeController extends Controller
{
    public function __construct(
        protected TicketUpgradePaymentService $paymentService
    ) {
    }

    public function show(string $token): View
    {
        $upgrade = $this->findUpgrade($token);

        $latestPayment = $upgrade->payments()
            ->latest('id')
            ->first();

        return view('public.tickets.upgrade', [
            'upgrade' => $upgrade,
            'latestPayment' => $latestPayment,
            'maskedEmail' => $this->maskEmail($upgrade->order?->buyer_email),
            'maskedPhone' => $this->maskPhone($upgrade->order?->buyer_phone),
        ]);
    }

    public function pay(string $token): RedirectResponse
    {
        $upgrade = $this->findUpgrade($token);

        if ($upgrade->isCompleted()) {
            return redirect()
                ->route('tickets.upgrades.show', ['token' => $upgrade->public_token])
                ->with('status', 'This ticket upgrade is already completed.');
        }

        if ($upgrade->isExpired()) {
            abort(410, 'This ticket upgrade has expired.');
        }

        try {
            $payment = $this->paymentService->createForTicketUpgrade($upgrade);

            if ($payment->status === Payment::STATUS_PROCESSING) {
                $existingUrl = trim((string) data_get($payment->metadata, 'checkout_redirect_url', ''));

                if ($existingUrl !== '') {
                    return redirect()->away($existingUrl);
                }
            }

            $response = $this->paymentService->start($payment);
            $redirectUrl = trim((string) data_get($response, 'redirect_url', ''));

            if ($redirectUrl === '') {
                throw new RuntimeException('Payment provider did not return a checkout URL.');
            }

            return redirect()->away($redirectUrl);
        } catch (RuntimeException $exception) {
            return redirect()
                ->route('tickets.upgrades.show', ['token' => $upgrade->public_token])
                ->withErrors(['payment' => $exception->getMessage()]);
        }
    }

    private function findUpgrade(string $token): TicketUpgrade
    {
        return TicketUpgrade::query()
            ->with([
                'event',
                'order',
                'ticket',
                'fromTicketType',
                'toTicketType',
            ])
            ->where('public_token', $token)
            ->firstOrFail();
    }

    private function maskEmail(?string $email): ?string
    {
        $email = trim((string) $email);
        if ($email === '' || ! str_contains($email, '@')) {
            return null;
        }

        [$local, $domain] = explode('@', $email, 2);
        $visible = mb_substr($local, 0, min(2, mb_strlen($local)));

        return $visible . str_repeat('*', max(3, mb_strlen($local) - mb_strlen($visible))) . '@' . $domain;
    }

    private function maskPhone(?string $phone): ?string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone) ?: '';
        if ($phone === '') {
            return null;
        }

        if (strlen($phone) <= 4) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, 4)
            . str_repeat('*', max(4, strlen($phone) - 7))
            . substr($phone, -3);
    }
}
