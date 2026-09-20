<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SmsService
{
    /**
     * Send one SMS message.
     */
    public function send(
        string $recipient,
        string $message
    ): array {
        $recipient = $this->normalizeRecipient(
            $recipient
        );

        $message = trim(
            $message
        );

        /*
        |--------------------------------------------------------------------------
        | Basic validation
        |--------------------------------------------------------------------------
        */

        if (blank($recipient)) {
            throw new RuntimeException(
                'SMS recipient is empty.'
            );
        }

        if (! $this->isValidRecipient($recipient)) {
            throw new RuntimeException(
                'SMS recipient format is invalid.'
            );
        }

        if (blank($message)) {
            throw new RuntimeException(
                'SMS message is empty.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Provider configuration
        |--------------------------------------------------------------------------
        */

        $apiUrl = config(
            'services.sms.api_url'
        );

        $apiKey = config(
            'services.sms.api_key'
        );

        $apiSecret = config(
            'services.sms.api_secret'
        );

        $senderId = config(
            'services.sms.sender_id',
            'eLive'
        );

        $timeout = (int) config(
            'services.sms.timeout',
            30
        );

        if (blank($apiUrl)) {
            throw new RuntimeException(
                'SMS API URL is not configured.'
            );
        }

        if (blank($apiKey)) {
            throw new RuntimeException(
                'SMS API key is not configured.'
            );
        }

        if (blank($apiSecret)) {
            throw new RuntimeException(
                'SMS API secret is not configured.'
            );
        }

        if (blank($senderId)) {
            throw new RuntimeException(
                'SMS sender ID is not configured.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Send request
        |--------------------------------------------------------------------------
        */

        try {
            Log::info(
                'Sending SMS through eLive SMS.',
                [
                    'recipient' =>
                        $this->maskRecipient(
                            $recipient
                        ),

                    'sender_id' =>
                        $senderId,

                    'message_length' =>
                        mb_strlen(
                            $message
                        ),

                    'estimated_segments' =>
                        $this->segmentCount(
                            $message
                        ),
                ]
            );

            $response = Http::timeout(
                    max(
                        5,
                        $timeout
                    )
                )
                ->connectTimeout(10)
                ->retry(
                    2,
                    1000,
                    throw: false
                )
                ->acceptJson()
                ->asJson()
                ->withHeaders([
                    'api_key' =>
                        $apiKey,

                    'api_secret' =>
                        $apiSecret,
                ])
                ->post(
                    $apiUrl,
                    [
                        'senderId' =>
                            $senderId,

                        'messageType' =>
                            'text',

                        'message' =>
                            $message,

                        'contacts' =>
                            $recipient,
                    ]
                );

            return $this->parseResponse(
                $response,
                $recipient
            );
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            Log::error(
                'SMS provider request failed.',
                [
                    'recipient' =>
                        $this->maskRecipient(
                            $recipient
                        ),

                    'error' =>
                        $exception->getMessage(),
                ]
            );

            throw new RuntimeException(
                'SMS provider request failed: '
                . $exception->getMessage(),
                previous: $exception
            );
        }
    }

    /**
     * Interpret the eLive SMS API response.
     */
    protected function parseResponse(
        Response $response,
        string $recipient
    ): array {
        $data = $response->json();

        /*
        |--------------------------------------------------------------------------
        | HTTP error
        |--------------------------------------------------------------------------
        */

        if (! $response->successful()) {
            Log::error(
                'SMS provider returned an HTTP error.',
                [
                    'recipient' =>
                        $this->maskRecipient(
                            $recipient
                        ),

                    'http_status' =>
                        $response->status(),

                    'response' =>
                        $this->safeResponse(
                            $data
                        ),
                ]
            );

            throw new RuntimeException(
                'SMS provider returned HTTP '
                . $response->status()
                . ': '
                . $this->responseMessage(
                    $response,
                    $data
                )
            );
        }

        /*
         * eLive SMS can return HTTP 200 but still
         * report failure inside the JSON payload.
         */

        $providerSuccess = data_get(
            $data,
            'success'
        );

        $providerCode = data_get(
            $data,
            'code'
        );

        if (
            $providerSuccess === false
            || (
                filled($providerCode)
                && is_numeric($providerCode)
                && (int) $providerCode >= 400
            )
        ) {
            Log::error(
                'SMS provider rejected the message.',
                [
                    'recipient' =>
                        $this->maskRecipient(
                            $recipient
                        ),

                    'provider_code' =>
                        $providerCode,

                    'response' =>
                        $this->safeResponse(
                            $data
                        ),
                ]
            );

            throw new RuntimeException(
                'SMS provider rejected message: '
                . $this->responseMessage(
                    $response,
                    $data
                )
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Contact validation response
        |--------------------------------------------------------------------------
        */

        $validContacts = data_get(
            $data,
            'data.validContacts'
        );

        $invalidContacts = data_get(
            $data,
            'data.invalidContacts'
        );

        if (
            $validContacts !== null
            && (int) $validContacts < 1
        ) {
            throw new RuntimeException(
                'SMS provider did not accept the recipient.'
            );
        }

        /*
        |--------------------------------------------------------------------------
        | Provider message ID
        |--------------------------------------------------------------------------
        */

        $providerMessageId =
            $this->extractMessageId(
                $data
            );

        Log::info(
            'SMS accepted by eLive SMS provider.',
            [
                'recipient' =>
                    $this->maskRecipient(
                        $recipient
                    ),

                'http_status' =>
                    $response->status(),

                'provider_code' =>
                    $providerCode,

                'provider_message_id' =>
                    $providerMessageId,

                'valid_contacts' =>
                    $validContacts,

                'invalid_contacts' =>
                    $invalidContacts,
            ]
        );

        return [
            'success' =>
                true,

            'recipient' =>
                $recipient,

            'provider_message_id' =>
                $providerMessageId,

            /*
             * Keep shoot_id for backwards compatibility.
             */
            'shoot_id' =>
                $providerMessageId,

            'http_status' =>
                $response->status(),

            'provider_code' =>
                $providerCode,

            'provider_message' =>
                data_get(
                    $data,
                    'message'
                ),

            'valid_contacts' =>
                $validContacts !== null
                    ? (int) $validContacts
                    : null,

            'invalid_contacts' =>
                $invalidContacts !== null
                    ? (int) $invalidContacts
                    : null,

            'duplicated_contacts' =>
                $this->nullableInteger(
                    data_get(
                        $data,
                        'data.duplicatedContacts'
                    )
                ),

            'message_size' =>
                $this->nullableInteger(
                    data_get(
                        $data,
                        'data.messageSize'
                    )
                ),

            'response' =>
                $data,
        ];
    }

    /**
     * Extract provider/Shoot ID from known response formats.
     */
    protected function extractMessageId(
        mixed $data
    ): ?string {
        if (! is_array($data)) {
            return null;
        }

        $messageId =
            data_get(
                $data,
                'data.shootId'
            )
            ?? data_get(
                $data,
                'data.shoot_id'
            )
            ?? data_get(
                $data,
                'shootId'
            )
            ?? data_get(
                $data,
                'shoot_id'
            )
            ?? data_get(
                $data,
                'message_id'
            )
            ?? data_get(
                $data,
                'messageId'
            )
            ?? data_get(
                $data,
                'id'
            )
            ?? data_get(
                $data,
                'data.message_id'
            )
            ?? data_get(
                $data,
                'data.messageId'
            )
            ?? data_get(
                $data,
                'data.id'
            );

        return filled($messageId)
            ? (string) $messageId
            : null;
    }

    /**
     * Extract a useful provider error message.
     */
    protected function responseMessage(
        Response $response,
        mixed $data
    ): string {
        if (is_array($data)) {
            $message =
                data_get(
                    $data,
                    'message'
                )
                ?? data_get(
                    $data,
                    'error.message'
                )
                ?? data_get(
                    $data,
                    'error'
                )
                ?? data_get(
                    $data,
                    'data.message'
                );

            if (filled($message)) {
                return is_string($message)
                    ? $message
                    : (
                        json_encode(
                            $message
                        )
                        ?: 'Unknown SMS provider error.'
                    );
            }

            return json_encode(
                $this->safeResponse(
                    $data
                )
            ) ?: 'Unknown SMS provider error.';
        }

        $body = trim(
            $response->body()
        );

        return filled($body)
            ? $body
            : 'Unknown SMS provider error.';
    }

    /**
     * Remove sensitive values before logging responses.
     */
    protected function safeResponse(
        mixed $data
    ): mixed {
        if (! is_array($data)) {
            return $data;
        }

        $sensitiveKeys = [
            'api_key',
            'api_secret',
            'apiKey',
            'apiSecret',
            'token',
            'access_token',
            'password',
        ];

        array_walk_recursive(
            $data,
            function (
                mixed &$value,
                string|int $key
            ) use (
                $sensitiveKeys
            ): void {
                if (
                    is_string($key)
                    && in_array(
                        $key,
                        $sensitiveKeys,
                        true
                    )
                ) {
                    $value =
                        '***HIDDEN***';
                }
            }
        );

        return $data;
    }

    /**
     * Normalize phone number before sending.
     *
     * Supported Tanzania examples:
     *
     * +255768461644
     * 255768461644
     * 0768461644
     * 768461644
     */
    public function normalizeRecipient(
        string $recipient
    ): string {
        $recipient = preg_replace(
            '/[^0-9+]/',
            '',
            trim(
                $recipient
            )
        ) ?? '';

        if (
            str_starts_with(
                $recipient,
                '+'
            )
        ) {
            $recipient = substr(
                $recipient,
                1
            );
        }

        /*
         * 0768461644
         * →
         * 255768461644
         */
        if (
            str_starts_with(
                $recipient,
                '0'
            )
            && strlen(
                $recipient
            ) === 10
        ) {
            return '255'
                . substr(
                    $recipient,
                    1
                );
        }

        /*
         * 768461644
         * →
         * 255768461644
         */
        if (
            (
                str_starts_with(
                    $recipient,
                    '6'
                )
                || str_starts_with(
                    $recipient,
                    '7'
                )
            )
            && strlen(
                $recipient
            ) === 9
        ) {
            return '255'
                . $recipient;
        }

        return $recipient;
    }

    /**
     * Basic international recipient validation.
     *
     * Allows 8 to 15 digits.
     */
    public function isValidRecipient(
        string $recipient
    ): bool {
        $recipient =
            $this->normalizeRecipient(
                $recipient
            );

        return (bool) preg_match(
            '/^[1-9][0-9]{7,14}$/',
            $recipient
        );
    }

    /**
     * Estimate the number of SMS segments.
     *
     * MVP calculation:
     *
     * 1 SMS       = 160 characters
     * Multipart   = 153 characters per SMS
     */
    public function segmentCount(
        string $message
    ): int {
        $length = mb_strlen(
            trim(
                $message
            )
        );

        if ($length === 0) {
            return 0;
        }

        if ($length <= 160) {
            return 1;
        }

        return (int) ceil(
            $length / 153
        );
    }

    /**
     * Hide most of the recipient number in logs.
     *
     * Example:
     *
     * 255768461644
     * →
     * ********1644
     */
    protected function maskRecipient(
        string $recipient
    ): string {
        $length = strlen(
            $recipient
        );

        if ($length <= 4) {
            return $recipient;
        }

        return str_repeat(
            '*',
            $length - 4
        ) . substr(
            $recipient,
            -4
        );
    }

    protected function nullableInteger(
        mixed $value
    ): ?int {
        if (
            $value === null
            || $value === ''
        ) {
            return null;
        }

        return is_numeric($value)
            ? (int) $value
            : null;
    }
}