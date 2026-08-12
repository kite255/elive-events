<?php

namespace App\Services;

use App\Models\Attendee;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppService
{
    /*
    |--------------------------------------------------------------------------
    | Registration Confirmation
    |--------------------------------------------------------------------------
    */

    public function sendRegistrationConfirmation(
        Attendee $attendee
    ): array {
        $attendee->loadMissing([
            'event',
            'category',
        ]);

        $event =
            $attendee->event;

        if (! $event) {
            throw new RuntimeException(
                'The attendee does not belong to an event.'
            );
        }

        if (
            blank(
                $attendee->phone
            )
        ) {
            throw new RuntimeException(
                'The attendee does not have a phone number.'
            );
        }

        if (
            blank(
                $attendee->badge_path
            )
        ) {
            throw new RuntimeException(
                'The attendee badge has not been generated yet.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Delivery Badge
        |--------------------------------------------------------------------------
        |
        | WhatsApp always uses the PNG delivery badge.
        |
        | SVG = master
        | PNG = WhatsApp / email / public delivery
        | PDF = printing
        |
        */

        $badgeUrl =
            $attendee
                ->badgePngUrl();

        if (
            blank(
                $badgeUrl
            )
        ) {
            throw new RuntimeException(
                'The attendee PNG badge has not been generated yet.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Send Registration Template
        |--------------------------------------------------------------------------
        */

        return $this->sendTemplate(
            phone:
                $attendee->phone,

            templateName:
                config(
                    'services.whatsapp.templates.registration_confirmation',
                    'event_registration_confirmation'
                ),

            languageCode:
                config(
                    'services.whatsapp.default_language',
                    'en'
                ),

            bodyParameters: [
                $attendee->full_name,

                $event->name,

                $attendee->category?->name
                    ?? 'Attendee',

                $event->venue
                    ?? '-',
            ],

            imageUrl:
                $badgeUrl,
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Send Template
    |--------------------------------------------------------------------------
    */

    public function sendTemplate(
        string $phone,
        string $templateName,
        string $languageCode,
        array $bodyParameters = [],
        ?string $imageUrl = null,
    ): array {
        $this->validateConfiguration();

        /*
        |--------------------------------------------------------------------------
        | Normalize Recipient
        |--------------------------------------------------------------------------
        */

        $recipient =
            $this->normalizePhoneNumber(
                $phone
            );

        if (
            blank(
                $recipient
            )
        ) {
            throw new RuntimeException(
                'Invalid WhatsApp recipient phone number.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Template Components
        |--------------------------------------------------------------------------
        */

        $components = [];

        /*
        |--------------------------------------------------------------------------
        | Image Header
        |--------------------------------------------------------------------------
        */

        if (
            filled(
                $imageUrl
            )
        ) {
            $this->validateImageUrl(
                $imageUrl
            );

            $components[] = [
                'type' =>
                    'header',

                'parameters' => [
                    [
                        'type' =>
                            'image',

                        'image' => [
                            'link' =>
                                $imageUrl,
                        ],
                    ],
                ],
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | Template Body Parameters
        |--------------------------------------------------------------------------
        */

        if (
            $bodyParameters
            !== []
        ) {
            $components[] = [
                'type' =>
                    'body',

                'parameters' =>
                    collect(
                        $bodyParameters
                    )
                        ->map(
                            fn (
                                $value
                            ): array => [
                                'type' =>
                                    'text',

                                'text' =>
                                    (string) (
                                        $value
                                        ?? ''
                                    ),
                            ]
                        )
                        ->values()
                        ->all(),
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | WhatsApp Cloud API Payload
        |--------------------------------------------------------------------------
        */

        $payload = [
            'messaging_product' =>
                'whatsapp',

            'recipient_type' =>
                'individual',

            'to' =>
                $recipient,

            'type' =>
                'template',

            'template' => [
                'name' =>
                    $templateName,

                'language' => [
                    'code' =>
                        $languageCode,
                ],

                'components' =>
                    $components,
            ],
        ];

        /*
        |--------------------------------------------------------------------------
        | Send To Meta
        |--------------------------------------------------------------------------
        */

        $response =
            Http::withToken(
                config(
                    'services.whatsapp.access_token'
                )
            )
                ->acceptJson()
                ->asJson()
                ->timeout(
                    (int) config(
                        'services.whatsapp.timeout',
                        30
                    )
                )
                ->retry(
                    2,
                    1000,
                    throw: false
                )
                ->post(
                    $this->messagesEndpoint(),
                    $payload
                );

        /*
        |--------------------------------------------------------------------------
        | API Failure
        |--------------------------------------------------------------------------
        */

        if (
            $response->failed()
        ) {
            $this->logFailure(
                recipient:
                    $recipient,

                templateName:
                    $templateName,

                payload:
                    $payload,

                response:
                    $response,
            );

            throw new RuntimeException(
                $this->extractErrorMessage(
                    $response
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | API Response
        |--------------------------------------------------------------------------
        */

        $responseData =
            $response->json();

        $providerMessageId =
            data_get(
                $responseData,
                'messages.0.id'
            );

        if (
            blank(
                $providerMessageId
            )
        ) {
            Log::warning(
                'WhatsApp API returned no message ID.',
                [
                    'recipient' =>
                        $recipient,

                    'template' =>
                        $templateName,

                    'response' =>
                        $responseData,
                ]
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Success Log
        |--------------------------------------------------------------------------
        */

        Log::info(
            'WhatsApp template accepted by Meta.',
            [
                'recipient' =>
                    $recipient,

                'template' =>
                    $templateName,

                'provider_message_id' =>
                    $providerMessageId,

                'has_media' =>
                    filled(
                        $imageUrl
                    ),

                'media_url' =>
                    $imageUrl,
            ]
        );

        return [
            'success' =>
                true,

            'recipient' =>
                $recipient,

            'provider_message_id' =>
                $providerMessageId,

            'template_name' =>
                $templateName,

            'response' =>
                $responseData,
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Validate Image URL
    |--------------------------------------------------------------------------
    |
    | WhatsApp registration confirmation currently expects an image header.
    |
    | eLive Events uses PNG for digital badge delivery.
    |
    |--------------------------------------------------------------------------
    */

    protected function validateImageUrl(
        string $imageUrl
    ): void {
        $path =
            parse_url(
                $imageUrl,
                PHP_URL_PATH
            );

        if (
            blank(
                $path
            )
        ) {
            throw new RuntimeException(
                'WhatsApp badge image URL is invalid.'
            );
        }

        $extension =
            strtolower(
                pathinfo(
                    (string) $path,
                    PATHINFO_EXTENSION
                )
            );

        if (
            ! in_array(
                $extension,
                [
                    'png',
                    'jpg',
                    'jpeg',
                ],
                true
            )
        ) {
            throw new RuntimeException(
                sprintf(
                    'WhatsApp image header must use PNG or JPEG. Current file extension: %s.',
                    $extension
                        ?: 'unknown'
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Ensure HTTPS In Production
        |--------------------------------------------------------------------------
        |
        | Meta needs to fetch the image from a publicly accessible URL.
        |
        */

        $scheme =
            strtolower(
                (string) parse_url(
                    $imageUrl,
                    PHP_URL_SCHEME
                )
            );

        if (
            app()->environment(
                'production'
            )
            && $scheme !== 'https'
        ) {
            throw new RuntimeException(
                'WhatsApp media URLs must use HTTPS in production.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Configuration
    |--------------------------------------------------------------------------
    */

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
            )
            && filled(
                config(
                    'services.whatsapp.templates.registration_confirmation'
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

        if (
            blank(
                config(
                    'services.whatsapp.templates.registration_confirmation'
                )
            )
        ) {
            throw new RuntimeException(
                'WHATSAPP_TEMPLATE_REGISTRATION_CONFIRMATION is not configured.'
            );
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Meta Messages Endpoint
    |--------------------------------------------------------------------------
    */

    protected function messagesEndpoint(): string
    {
        $version =
            config(
                'services.whatsapp.graph_version',
                'v25.0'
            );

        $phoneNumberId =
            config(
                'services.whatsapp.phone_number_id'
            );

        return sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            $version,
            $phoneNumberId
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Normalize Phone Number
    |--------------------------------------------------------------------------
    */

    protected function normalizePhoneNumber(
        string $phone
    ): string {
        $phone =
            preg_replace(
                '/[^0-9+]/',
                '',
                trim(
                    $phone
                )
            );

        $phone =
            ltrim(
                (string) $phone,
                '+'
            );

        if (
            str_starts_with(
                $phone,
                '00'
            )
        ) {
            $phone =
                substr(
                    $phone,
                    2
                );
        }

        /*
        |--------------------------------------------------------------------------
        | Tanzania Local Number
        |--------------------------------------------------------------------------
        |
        | 0768461644
        | ↓
        | 255768461644
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
        | Tanzania Number Without Country Code
        |--------------------------------------------------------------------------
        |
        | 768461644
        | ↓
        | 255768461644
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

    /*
    |--------------------------------------------------------------------------
    | Extract Meta Error Message
    |--------------------------------------------------------------------------
    */

    protected function extractErrorMessage(
        Response $response
    ): string {
        $responseData =
            $response->json();

        $message =
            data_get(
                $responseData,
                'error.message'
            );

        $code =
            data_get(
                $responseData,
                'error.code'
            );

        $errorSubcode =
            data_get(
                $responseData,
                'error.error_subcode'
            );

        $errorType =
            data_get(
                $responseData,
                'error.type'
            );

        $fbtraceId =
            data_get(
                $responseData,
                'error.fbtrace_id'
            );

        if (
            filled(
                $message
            )
        ) {
            $parts = [
                'WhatsApp API error',
            ];

            if (
                $code !== null
            ) {
                $parts[] =
                    'code '
                    . $code;
            }

            if (
                $errorSubcode
                !== null
            ) {
                $parts[] =
                    'subcode '
                    . $errorSubcode;
            }

            if (
                filled(
                    $errorType
                )
            ) {
                $parts[] =
                    'type '
                    . $errorType;
            }

            $errorMessage =
                implode(
                    ' - ',
                    $parts
                )
                . ': '
                . $message;

            if (
                filled(
                    $fbtraceId
                )
            ) {
                $errorMessage .=
                    ' [Trace: '
                    . $fbtraceId
                    . ']';
            }

            return $errorMessage;
        }

        return sprintf(
            'WhatsApp API request failed with HTTP status %s.',
            $response->status()
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Failure Logging
    |--------------------------------------------------------------------------
    */

    protected function logFailure(
        string $recipient,
        string $templateName,
        array $payload,
        Response $response,
    ): void {
        /*
        |--------------------------------------------------------------------------
        | Never Log Access Token
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

                    'recipient_type' =>
                        $payload[
                            'recipient_type'
                        ] ?? null,

                    'to' =>
                        $payload[
                            'to'
                        ] ?? null,

                    'type' =>
                        $payload[
                            'type'
                        ] ?? null,

                    'template_name' =>
                        data_get(
                            $payload,
                            'template.name'
                        ),

                    'language' =>
                        data_get(
                            $payload,
                            'template.language.code'
                        ),

                    'has_header' =>
                        collect(
                            data_get(
                                $payload,
                                'template.components',
                                []
                            )
                        )
                            ->contains(
                                fn (
                                    array $component
                                ): bool =>
                                    data_get(
                                        $component,
                                        'type'
                                    )
                                    === 'header'
                            ),
                ],
            ]
        );
    }
}