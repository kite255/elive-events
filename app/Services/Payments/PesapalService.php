<?php

namespace App\Services\Payments;

use App\Contracts\PaymentGateway;
use App\Models\Payment;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PesapalService implements PaymentGateway
{
    public function code(): string
    {
        return 'pesapal';
    }

    public function createPayment(
        Payment $payment
    ): array {
        $payment->loadMissing([
            'event',
            'attendee',
        ]);

        $notificationId =
            (string) config(
                'services.pesapal.ipn_id'
            );

        if (blank($notificationId)) {
            throw new RuntimeException(
                'PESAPAL_IPN_ID is not configured.'
            );
        }

        $attendee =
            $payment->attendee;

        $name =
            trim(
                (string) (
                    $attendee?->full_name
                    ?? ''
                )
            );

        $nameParts =
            preg_split(
                '/\s+/',
                $name,
                -1,
                PREG_SPLIT_NO_EMPTY
            ) ?: [];

        $firstName =
            $nameParts[0]
            ?? '';

        $lastName =
            count($nameParts) > 1
                ? end($nameParts)
                : '';

        $description =
            Str::limit(
                $payment->description
                    ?: (
                        'Registration payment for '
                        . (
                            $payment->event?->name
                            ?? 'eLive Event'
                        )
                    ),
                100,
                ''
            );

        $payload = [
            'id' =>
                $payment->reference,

            'currency' =>
                Str::upper(
                    $payment->currency
                ),

            'amount' =>
                (float) $payment->amount,

            'description' =>
                $description,

            'callback_url' =>
                route(
                    'payments.pesapal.callback'
                ),

            'redirect_mode' =>
                'TOP_WINDOW',

            'notification_id' =>
                $notificationId,

            'billing_address' => [
                'email_address' =>
                    (string) (
                        $attendee?->email
                        ?? ''
                    ),

                'phone_number' =>
                    (string) (
                        $attendee?->phone
                        ?? ''
                    ),

                'country_code' =>
                    (string) config(
                        'services.pesapal.country_code',
                        'TZ'
                    ),

                'first_name' =>
                    $firstName,

                'middle_name' =>
                    '',

                'last_name' =>
                    $lastName,

                'line_1' =>
                    '',

                'line_2' =>
                    '',

                'city' =>
                    '',

                'state' =>
                    '',

                'postal_code' =>
                    '',

                'zip_code' =>
                    '',
            ],
        ];

        $response =
            $this->authenticatedRequest()
                ->post(
                    $this->endpoint(
                        '/api/Transactions/SubmitOrderRequest'
                    ),
                    $payload
                );

        $data =
            $response->json();

        if (
            ! $response->successful()
            || filled(
                data_get(
                    $data,
                    'error.message'
                )
            )
            || blank(
                data_get(
                    $data,
                    'redirect_url'
                )
            )
        ) {
            throw new RuntimeException(
                $this->errorMessage(
                    $data,
                    'Pesapal could not create the payment request.'
                )
            );
        }

        return $data;
    }

    public function getPaymentStatus(
        string $providerTrackingId
    ): array {
        if (blank($providerTrackingId)) {
            throw new RuntimeException(
                'Pesapal order tracking ID is required.'
            );
        }

        $response =
            $this->authenticatedRequest()
                ->get(
                    $this->endpoint(
                        '/api/Transactions/GetTransactionStatus'
                    ),
                    [
                        'orderTrackingId' =>
                            $providerTrackingId,
                    ]
                );

        $data =
            $response->json();

        if (! $response->successful()) {
            throw new RuntimeException(
                $this->errorMessage(
                    $data,
                    'Pesapal transaction status could not be retrieved.'
                )
            );
        }

        $errorMessage =
            trim(
                (string) data_get(
                    $data,
                    'error.message',
                    ''
                )
            );

        if (
            $errorMessage !== ''
            && ! str_contains(
                Str::lower($errorMessage),
                'pending payment'
            )
        ) {
            throw new RuntimeException(
                $this->errorMessage(
                    $data,
                    'Pesapal transaction status could not be retrieved.'
                )
            );
        }

        if (
            $errorMessage !== ''
            && str_contains(
                Str::lower($errorMessage),
                'pending payment'
            )
        ) {
            $data['payment_status_description'] =
                'PENDING';
        }

        return $data;
    }

    public function registerIpn(
        string $url,
        string $method = 'POST'
    ): array {
        if (blank($url)) {
            throw new RuntimeException(
                'A public IPN URL is required.'
            );
        }

        $method =
            Str::upper($method);

        if (
            ! in_array(
                $method,
                ['GET', 'POST'],
                true
            )
        ) {
            throw new RuntimeException(
                'Pesapal IPN method must be GET or POST.'
            );
        }

        $response =
            $this->authenticatedRequest()
                ->post(
                    $this->endpoint(
                        '/api/URLSetup/RegisterIPN'
                    ),
                    [
                        'url' =>
                            $url,

                        'ipn_notification_type' =>
                            $method,
                    ]
                );

        $data =
            $response->json();

        if (
            ! $response->successful()
            || filled(
                data_get(
                    $data,
                    'error.message'
                )
            )
            || blank(
                data_get(
                    $data,
                    'ipn_id'
                )
            )
        ) {
            throw new RuntimeException(
                $this->errorMessage(
                    $data,
                    'Pesapal IPN registration failed.'
                )
            );
        }

        return $data;
    }

    /**
     * Obtain and cache the Pesapal API bearer token.
     *
     * Pesapal tokens are short lived, so we cache for four minutes.
     */
    public function token(): string
    {
        $environment =
            (string) config(
                'services.pesapal.environment',
                'sandbox'
            );

        return Cache::remember(
            'pesapal:token:' . $environment,
            now()->addMinutes(4),
            function (): string {
                $consumerKey =
                    (string) config(
                        'services.pesapal.consumer_key'
                    );

                $consumerSecret =
                    (string) config(
                        'services.pesapal.consumer_secret'
                    );

                if (
                    blank($consumerKey)
                    || blank($consumerSecret)
                ) {
                    throw new RuntimeException(
                        'Pesapal consumer key and consumer secret are not configured.'
                    );
                }

                $response =
                    Http::acceptJson()
                        ->asJson()
                        ->timeout(30)
                        ->retry(
                            2,
                            500,
                            throw: false
                        )
                        ->post(
                            $this->endpoint(
                                '/api/Auth/RequestToken'
                            ),
                            [
                                'consumer_key' =>
                                    $consumerKey,

                                'consumer_secret' =>
                                    $consumerSecret,
                            ]
                        );

                $data =
                    $response->json();

                $token =
                    data_get(
                        $data,
                        'token'
                    );

                if (
                    ! $response->successful()
                    || blank($token)
                ) {
                    throw new RuntimeException(
                        $this->errorMessage(
                            $data,
                            'Pesapal authentication failed.'
                        )
                    );
                }

                return (string) $token;
            }
        );
    }

    protected function authenticatedRequest(): PendingRequest
    {
        return Http::acceptJson()
            ->asJson()
            ->withToken(
                $this->token()
            )
            ->timeout(30)
            ->retry(
                2,
                500,
                throw: false
            );
    }

    protected function endpoint(
        string $path
    ): string {
        return rtrim(
            $this->baseUrl(),
            '/'
        )
            . '/'
            . ltrim(
                $path,
                '/'
            );
    }

    protected function baseUrl(): string
    {
        $configured =
            (string) config(
                'services.pesapal.base_url'
            );

        if (filled($configured)) {
            return $configured;
        }

        return config(
            'services.pesapal.environment',
            'sandbox'
        ) === 'live'
            ? 'https://pay.pesapal.com/v3'
            : 'https://cybqa.pesapal.com/pesapalv3';
    }

    protected function errorMessage(
        mixed $data,
        string $fallback
    ): string {
        return (string) (
            data_get(
                $data,
                'error.message'
            )
            ?: data_get(
                $data,
                'message'
            )
            ?: $fallback
        );
    }
}
