<?php

namespace App\Filament\Pages;

use App\Models\Event;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use UnitEnum;

class SuccessfulMerchandiseOrders extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-check-badge';
    protected static string|UnitEnum|null $navigationGroup = 'Merchandise';
    protected static ?string $navigationLabel = 'Successful Orders';
    protected static ?string $title = 'Successful Merchandise Orders';
    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.successful-merchandise-orders';

    private static function authorizedEventIds(): array
    {
        $user = auth()->user();

        if (! $user instanceof User) {
            return [];
        }

        return Event::query()->accessibleBy($user)->get()
            ->filter(fn (Event $event): bool => $event->canBeManagedBy($user)
                && ($user->isSuperAdmin()
                    || ! ($user->isOrganizationOwner($event->organization_id)
                        || $user->hasOrganizationRole($event->organization_id, User::ORGANIZATION_ROLE_OWNER))))
            ->pluck('id')->all();
    }

    public static function canAccess(): bool
    {
        return count(static::authorizedEventIds()) > 0;
    }

    public function successfulOrders(): \Illuminate\Contracts\Pagination\LengthAwarePaginator
    {
        $ids = static::authorizedEventIds();

        $shop = DB::table('merchandise_orders as mo')
            ->join('events as e', 'e.id', '=', 'mo.event_id')
            ->leftJoin('merchandise_products as mp', 'mp.id', '=', 'mo.merchandise_product_id')
            ->whereIn('mo.event_id', $ids)
            ->where('mo.status', 'paid')
            ->select([
                DB::raw("'Shop' as source"),
                'mo.order_number as reference',
                'e.name as event_name',
                'mp.name as product_name',
                'mo.buyer_name as customer_name',
                'mo.buyer_phone as phone',
                'mo.quantity as quantity',
                'mo.total_tzs as total',
                DB::raw("'TZS' as currency"),
                'mo.created_at as ordered_at',
            ]);

        $attendee = DB::table('attendee_merchandises as am')
            ->join('events as e', 'e.id', '=', 'am.event_id')
            ->leftJoin('attendees as a', 'a.id', '=', 'am.attendee_id')
            ->leftJoin('event_merchandises as em', 'em.id', '=', 'am.event_merchandise_id')
            ->whereIn('am.event_id', $ids)
            ->where('am.payment_status', 'paid')
            ->select([
                DB::raw("'Attendee' as source"),
                DB::raw("COALESCE(am.payment_reference, CONCAT('ATT-', am.id)) as reference"),
                'e.name as event_name',
                'em.name as product_name',
                'a.full_name as customer_name',
                'a.phone as phone',
                'am.quantity as quantity',
                'am.total_price as total',
                'am.currency as currency',
                'am.created_at as ordered_at',
            ]);

        return DB::query()
            ->fromSub($shop->unionAll($attendee), 'successful_orders')
            ->orderByDesc('ordered_at')
            ->paginate(25);
    }
}
