<?php

namespace App\Services\Audit;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class AuditLogService
{
    private const SENSITIVE_KEYS = [
        'qr_token_encrypted',
        'qr_token_hash',
        'qr_secret',
        'access_token',
        'api_key',
        'api_secret',
        'consumer_secret',
        'password',
        'webhook_secret',
    ];

    public function record(
        string $action,
        Model $subject,
        ?User $actor = null,
        array $metadata = [],
        ?array $before = null,
        ?array $after = null
    ): AuditLog {
        $eventId = $this->resolveEventId($subject);

        return AuditLog::query()->create([
            'actor_id' => $actor?->getKey(),
            'event_id' => $eventId,
            'action' => trim($action),
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'reference' => $this->resolveReference($subject),
            'before_data' => $this->sanitize($before),
            'after_data' => $this->sanitize($after),
            'metadata' => $this->sanitize($metadata),
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
            'created_at' => now(),
        ]);
    }

    private function resolveEventId(Model $subject): ?int
    {
        $eventId = data_get($subject, 'event_id');

        if ($eventId) {
            return (int) $eventId;
        }

        if (method_exists($subject, 'event')) {
            $subject->loadMissing('event');
            $event = data_get($subject, 'event');

            if ($event instanceof Event) {
                return (int) $event->getKey();
            }
        }

        return null;
    }

    private function resolveReference(Model $subject): ?string
    {
        foreach (['ticket_number', 'order_number', 'reference', 'name'] as $attribute) {
            $value = data_get($subject, $attribute);

            if (filled($value)) {
                return Str::limit((string) $value, 255, '');
            }
        }

        return null;
    }

    private function sanitize(?array $data): ?array
    {
        if ($data === null) {
            return null;
        }

        return collect($data)
            ->mapWithKeys(function ($value, $key): array {
                $normalizedKey = Str::of((string) $key)->snake()->lower()->toString();

                if ($this->isSensitiveKey($normalizedKey)) {
                    return [];
                }

                if (is_array($value)) {
                    $value = $this->sanitize($value) ?? [];
                }

                return [$key => $value];
            })
            ->all();
    }

    private function isSensitiveKey(string $key): bool
    {
        if (in_array($key, self::SENSITIVE_KEYS, true)) {
            return true;
        }

        if (str_contains($key, 'password')) {
            return true;
        }

        if (
            str_ends_with($key, '_secret')
            || str_ends_with($key, '_token')
            || str_ends_with($key, '_api_key')
        ) {
            return true;
        }

        return str_starts_with($key, 'qr_')
            && (str_contains($key, 'token') || str_contains($key, 'hash') || str_contains($key, 'secret'));
    }
}
