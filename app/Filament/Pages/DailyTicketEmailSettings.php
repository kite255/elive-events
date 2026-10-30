<?php

namespace App\Filament\Pages;

use App\Models\User;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class DailyTicketEmailSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-envelope';
    protected static ?string $navigationLabel = 'Daily Ticket Email Settings';
    protected static ?string $title = 'Daily Ticket Email Settings';
    protected static string|UnitEnum|null $navigationGroup = 'Reports';
    protected static ?int $navigationSort = 16;
    protected string $view = 'filament.pages.daily-ticket-email-settings';

    public bool $adminEnabled = false;
    public string $emails = '';

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
        $settings = DB::table('daily_ticket_summary_settings')->where('id', 1)->first();
        $this->adminEnabled = (bool) ($settings?->admin_enabled ?? false);
        $recipients = json_decode($settings?->admin_recipients ?? '[]', true);
        $this->emails = implode("\n", is_array($recipients) ? $recipients : []);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $emails = array_values(array_unique(array_filter(array_map(
            fn ($value) => strtolower(trim($value)),
            preg_split('/[\r\n,;]+/', $this->emails) ?: []
        ))));

        Validator::make(['emails' => $emails], [
            'emails' => ['array', 'max:20'],
            'emails.*' => ['required', 'email:rfc', 'max:254'],
        ])->validate();

        if ($this->adminEnabled && count($emails) === 0) {
            throw ValidationException::withMessages(['emails' => 'Add at least one recipient before enabling email reports.']);
        }

        DB::table('daily_ticket_summary_settings')->upsert([[
            'id' => 1,
            'admin_enabled' => $this->adminEnabled,
            'admin_recipients' => json_encode($emails, JSON_THROW_ON_ERROR),
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['id'], ['admin_enabled', 'admin_recipients', 'updated_at']);

        $this->emails = implode("\n", $emails);
        Notification::make()->title('Daily ticket email settings saved')->success()->send();
    }
}
