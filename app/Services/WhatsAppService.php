<?php

namespace App\Services;

use App\Models\Attendee;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppService
{
    public function sendRegistrationConfirmation(
        Attendee $attendee
    ): array {
        $attendee->loadMissing([
            'event',
            'category',
        ]);

        $event = $attendee->event;

        if (! $event) {
            throw new RuntimeException(
                'The attendee does not belong to an event.'
            );
        }

        if (blank($attendee->phone)) {
            throw new RuntimeException(
                'The attendee does not have a phone number.'
            );
        }

        if (blank($attendee->badge_path)) {
            throw new RuntimeException(
                'The attendee badge has not been generated yet.'
            );
        }

        $badgeUrl = $this->badgeUrl($attendee);

        return $this->sendTemplate(
            phone: $attendee->phone,
            templateName: 'event_registration_confirmation',
            languageCode: 'en',
            bodyParameters: [
                $attendee->full_name,
                $event->name,
                $attendee->category?->name
                    ?? 'Attendee',
                $event->venue
                    ?? '-',
            ],
            imageUrl: $badgeUrl,
        );
    }

    public function sendTemplate(
        string $phone,
        string $templateName,
        string $languageCode,
        array $bodyParameters = [],
        ?string $imageUrl = null,
    ): array {
        $this->validateConfiguration();

        $recipient = $this->normalizePhoneNumber(
            $phone
        );

        if (blank($recipient)) {
            throw new RuntimeException(
                'Invalid WhatsApp recipient phone number.'
            );
        }

        $components = [];

        /*
        |--------------------------------------------------------------------------
        | Optional media header
        |--------------------------------------------------------------------------
        */

        if (filled($imageUrl)) {
            $components[] = [
                'type' => 'header',
                'parameters' => [
                    [
                        'type' => 'image',
                        'image' => [
                            'link' => $imageUrl,
                        ],
                    ],
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Template body placeholders
        |--------------------------------------------------------------------------
        */

        if ($bodyParameters !== []) {
            $components[] = [
                'type' => 'body',
                'parameters' => collect(
                    $bodyParameters
                )
                    ->map(
                        fn ($value): array => [
                            'type' => 'text',
                            'text' => (string) (
                                $value ?? ''
                            ),
                        ]
                    )
                    ->values()
                    ->all(),
            ];
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'recipient_type' => 'individual',
            'to' => $recipient,
            'type' => 'template',

            'template' => [
                'name' => $templateName,

                'language' => [
                    'code' => $languageCode,
                ],

                'components' => $components,
            ],
        ];

        $response = Http::withToken(
            config(
                'services.whatsapp.access_token'
            )
        )
            ->acceptJson()
            ->asJson()
            ->timeout(30)
            ->retry(
                2,
                1000,
                throw: false
            )
            ->post(
                $this->messagesEndpoint(),
                $payload
            );

        if ($response->failed()) {
            $this->logFailure(
                $recipient,
                $templateName,
                $payload,
                $response
            );

            throw new RuntimeException(
                $this->extractErrorMessage(
                    $response
                )
            );
        }

        $responseData = $response->json();

        $providerMessageId = data_get(
            $responseData,
            'messages.0.id'
        );

        if (blank($providerMessageId)) {
            Log::warning(
                'WhatsApp API returned no message ID.',
                [
                    'recipient' => $recipient,
                    'template' => $templateName,
                    'response' => $responseData,
                ]
            );
        }

        return [
            'success' => true,
            'recipient' => $recipient,

            'provider_message_id' =>
                $providerMessageId,

            'template_name' =>
                $templateName,

            'response' =>
                $responseData,
        ];
    }

    public function isConfigured(): bool
    {
        return filled(
            config(
                'services.whatsapp.access_token'
            )
        )
            && filled(
                config(
                    'services.whatsapp.phone_number_id'
                )
            );
    }

    protected function validateConfiguration(): void
    {
        if (
            blank(
                config(
                    'services.whatsapp.access_token'
                )
            )
        ) {
            throw new RuntimeException(
                'WHATSAPP_ACCESS_TOKEN is not configured.'
            );
        }

        if (
            blank(
                config(
                    'services.whatsapp.phone_number_id'
                )
            )
        ) {
            throw new RuntimeException(
                'WHATSAPP_PHONE_NUMBER_ID is not configured.'
            );
        }
    }

    protected function messagesEndpoint(): string
    {
        $version = config(
            'services.whatsapp.graph_version',
            'v24.0'
        );

        $phoneNumberId = config(
            'services.whatsapp.phone_number_id'
        );

        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $version,
            $phoneNumberId
        );
    }

    protected function badgeUrl(
        Attendee $attendee
    ): string {
        $path = ltrim(
            (string) $attendee->badge_path,
            '/'
        );

        if (
            str_starts_with(
                $path,
                'http://'
            )
            || str_starts_with(
                $path,
                'https://'
            )
        ) {
            return $path;
        }

        /*
        |--------------------------------------------------------------------------
        | Support paths already prefixed with storage/
        |--------------------------------------------------------------------------
        */

        if (
            str_starts_with(
                $path,
                'storage/'
            )
        ) {
            return asset(
                $path
            );
        }

        return asset(
            'storage/' . $path
        );
    }

    protected function normalizePhoneNumber(
        string $phone
    ): string {
        $phone = preg_replace(
            '/[^0-9+]/',
            '',
            trim($phone)
        );

        $phone = ltrim(
            $phone,
            '+'
        );

        if (
            str_starts_with(
                $phone,
                '00'
            )
        ) {
            $phone = substr(
                $phone,
                2
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Tanzania local numbers
        |--------------------------------------------------------------------------
        |
        | 0768461644 -> 255768461644
        |
        */

        if (
            preg_match(
                '/^0[67]\d{8}$/',
                $phone
            )
        ) {
            return '255'
                . substr(
                    $phone,
                    1
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Tanzania number without country code
        |--------------------------------------------------------------------------
        |
        | 768461644 -> 255768461644
        |
        */

        if (
            preg_match(
                '/^[67]\d{8}$/',
                $phone
            )
        ) {
            return '255'
                . $phone;
        }

        return $phone;
    }

    protected function extractErrorMessage(
        Response $response
    ): string {
        $message = data_get(
            $response->json(),
            'error.message'
        );

        $code = data_get(
            $response->json(),
            'error.code'
        );

        $errorSubcode = data_get(
            $response->json(),
            'error.error_subcode'
        );

        if (filled($message)) {
            $parts = [
                'WhatsApp API error',
            ];

            if ($code !== null) {
                $parts[] =
                    'code ' . $code;
            }

            if ($errorSubcode !== null) {
                $parts[] =
                    'subcode '
                    . $errorSubcode;
            }

            return implode(
                ' - ',
                $parts
            )
                . ': '
                . $message;
        }

        return sprintf(
            'WhatsApp API request failed with HTTP status %s.',
            $response->status()
        );
    }

    protected function logFailure(
        string $recipient,
        string $templateName,
        array $payload,
        Response $response,
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Never log the access token.
        |--------------------------------------------------------------------------
        */

        Log::error(
            'WhatsApp message sending failed.',
            [
                'recipient' =>
                    $recipient,

                'template' =>
                    $templateName,

                'http_status' =>
                    $response->status(),

                'error' =>
                    $response->json(
                        'error'
                    ),

                'payload' => [
                    'messaging_product' =>
                        $payload[
                            'messaging_product'
                        ] ?? null,

                    'to' =>
                        $payload['to']
                        ?? null,

                    'type' =>
                        $payload['type']
                        ?? null,

                    'template' =>
                        $payload[
                            'template'
                        ] ?? null,
                ],
            ]
        );
    }
}