<?php

namespace App\Filament\Pages;

use App\Models\User;
use App\Notifications\PayoutEmailTestNotification;
use Illuminate\Support\Facades\Notification as MailNotification;
use Illuminate\Support\Facades\Log;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class SystemEmailSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'System Email Settings';
    protected static ?string $title = 'System Email Settings';
    protected static string|UnitEnum|null $navigationGroup = 'Administration';
    protected static ?int $navigationSort = 30;
    protected string $view = 'filament.pages.system-email-settings';

    public bool $payoutEnabled = false;
    public string $payoutEmails = '';

    public static function canAccess(): bool
    {
        return auth()->user() instanceof User && auth()->user()->isSuperAdmin();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);
        $settings = DB::table('system_notification_settings')->where('id', 1)->first();
        $this->payoutEnabled = (bool) ($settings?->payout_request_emails_enabled ?? false);
        $recipients = json_decode($settings?->payout_request_recipients ?? '[]', true);
        $this->payoutEmails = implode("\n", is_array($recipients) ? $recipients : []);
    }

    /**
     * Send an explicitly labelled test only to the saved alert recipients.
     * Queued means accepted for processing, not delivered.
     */
    public function sendTest(): void
    {
        abort_unless(static::canAccess(), 403);
        $settings = DB::table('system_notification_settings')->where('id', 1)->first();
        $recipients = json_decode($settings?->payout_request_recipients ?? '[]', true);
        $recipients = is_array($recipients) ? array_values(array_filter($recipients, fn ($email) =>
            is_string($email) && filter_var($email, FILTER_VALIDATE_EMAIL)
        )) : [];

        if (! $settings?->payout_request_emails_enabled || count($recipients) === 0) {
            Notification::make()->title('Save and enable payout alerts first')
                ->body('Enable payout request emails, add valid recipients, and save settings before sending a test.')
                ->warning()->send();
            return;
        }

        try {
            foreach ($recipients as $email) {
                MailNotification::route('mail', $email)->notify(new PayoutEmailTestNotification());
            }
        } catch (\Throwable $exception) {
            Log::warning('Payout email test dispatch failed', ['error' => $exception->getMessage()]);
            Notification::make()->title('Payout email test could not be queued')
                ->body('Check your application logs, queue connection and mail configuration.')
                ->danger()->send();
            return;
        }

        Notification::make()->title('Test email notifications queued')
            ->body(count($recipients).' recipient(s). Delivery is not confirmed until the queue worker processes the jobs and your inbox receives the test.')
            ->success()->send();
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $emails = array_values(array_unique(array_filter(array_map(
            fn ($email) => strtolower(trim($email)),
            preg_split('/[\r\n,;]+/', $this->payoutEmails) ?: []
        ))));

        Validator::make(['emails' => $emails], [
            'emails' => ['array', 'max:20'],
            'emails.*' => ['required', 'email:rfc', 'max:254'],
        ])->validate();

        if ($this->payoutEnabled && count($emails) === 0) {
            throw ValidationException::withMessages([
                'payoutEmails' => 'Add at least one recipient before enabling payout alerts.',
            ]);
        }

        DB::table('system_notification_settings')->upsert([[
            'id' => 1,
            'payout_request_emails_enabled' => $this->payoutEnabled,
            'payout_request_recipients' => json_encode($emails, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['id'], ['payout_request_emails_enabled', 'payout_request_recipients', 'updated_at']);

        $this->payoutEmails = implode("\n", $emails);
        Notification::make()->title('System email settings saved')->success()->send();
    }
}
