<?php

namespace Tests\Feature\Auth;

use App\Filament\Pages\Auth\ChangePassword;
use App\Filament\Pages\Auth\RequestPasswordReset;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Filament\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PanelPasswordRecoveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_panels_show_password_reset_links(): void
    {
        $this->withoutVite();

        foreach (['admin', 'gate'] as $panel) {
            $route = route("filament.{$panel}.auth.password-reset.request");

            $this->get("/{$panel}/login")->assertOk()->assertSee($route, false);
            $this->get($route)->assertOk();
        }
    }

    #[DataProvider('panels')]
    public function test_only_the_matching_portal_can_send_a_password_reset_link(string $panel): void
    {
        $this->withoutVite();
        Notification::fake();
        config(['app.url' => 'https://events.example.test']);
        $gateStaff = $this->gateStaff();
        $admin = User::factory()->create(['is_super_admin' => true]);
        [$eligible, $ineligible] = $panel === 'gate' ? [$gateStaff, $admin] : [$admin, $gateStaff];
        Filament::setCurrentPanel(Filament::getPanel($panel));

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $ineligible->email])
            ->call('request')
            ->assertHasNoErrors()
            ->assertSet('data.email', null);
        $this->assertFalse(Password::broker()->getRepository()->recentlyCreatedToken($ineligible));

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $eligible->email])
            ->call('request')
            ->assertHasNoErrors()
            ->assertSet('data.email', null);
        Notification::assertSentTo(
            $eligible,
            ResetPasswordNotification::class,
            fn ($notification): bool => parse_url($notification->url, PHP_URL_HOST) === 'events.example.test'
                && parse_url($notification->url, PHP_URL_PATH) === "/{$panel}/password-reset/reset",
        );
    }

    public static function panels(): array
    {
        return [['admin'], ['gate']];
    }

    public function test_gate_staff_can_reset_and_change_their_own_password(): void
    {
        $this->withoutVite();
        $gateStaff = $this->gateStaff();
        Filament::setCurrentPanel(Filament::getPanel('gate'));
        $token = Password::broker()->createToken($gateStaff);

        Livewire::test(Filament::getCurrentPanel()->getResetPasswordRouteAction(), ['email' => $gateStaff->email, 'token' => $token])
            ->fillForm(['password' => 'reset-password-921', 'passwordConfirmation' => 'reset-password-921'])
            ->call('resetPassword')
            ->assertRedirect('/gate/login');

        $this->assertTrue(Hash::check('reset-password-921', $gateStaff->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($gateStaff, $token));

        $this->actingAs($gateStaff->fresh(), 'gate');
        $this->get('/gate/profile')->assertOk();
        $previousToken = Password::broker()->createToken($gateStaff);
        $rememberToken = $gateStaff->fresh()->getRememberToken();

        Livewire::test(ChangePassword::class)
            ->fillForm([
                'currentPassword' => 'wrong-password',
                'password' => 'changed-password-921',
                'passwordConfirmation' => 'changed-password-921',
            ])
            ->call('save')
            ->assertHasFormErrors(['currentPassword' => 'current_password'])
            ->fillForm(['currentPassword' => 'reset-password-921'])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue(Hash::check('changed-password-921', $gateStaff->fresh()->password));
        $this->assertFalse(Password::broker()->tokenExists($gateStaff, $previousToken));
        $this->assertNotSame($rememberToken, $gateStaff->fresh()->getRememberToken());
        $this->assertAuthenticatedAs($gateStaff, 'gate');
    }

    public function test_suspended_gate_staff_cannot_request_a_reset_token(): void
    {
        $this->withoutVite();
        Notification::fake();
        $gateStaff = $this->gateStaff();
        $gateStaff->checkInEvents()->sole()->suspendAssignedUser($gateStaff);
        Filament::setCurrentPanel(Filament::getPanel('gate'));

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => $gateStaff->email])
            ->call('request')
            ->assertHasNoErrors()
            ->assertSet('data.email', null);

        Notification::assertNothingSent();
        $this->assertFalse(Password::broker()->getRepository()->recentlyCreatedToken($gateStaff));
    }

    public function test_wrong_portal_cannot_consume_another_portals_token(): void
    {
        $this->withoutVite();
        $gateStaff = $this->gateStaff();
        $token = Password::broker()->createToken($gateStaff);
        Filament::setCurrentPanel(Filament::getPanel('admin'));

        Livewire::test(Filament::getCurrentPanel()->getResetPasswordRouteAction(), ['email' => $gateStaff->email, 'token' => $token])
            ->fillForm(['password' => 'wrong-portal-password', 'passwordConfirmation' => 'wrong-portal-password'])
            ->call('resetPassword')
            ->assertNoRedirect();

        $this->assertTrue(Password::broker()->tokenExists($gateStaff, $token));
        $this->assertFalse(Hash::check('wrong-portal-password', $gateStaff->fresh()->password));
    }

    private function gateStaff(): User
    {
        $staff = User::factory()->create(['is_gate_staff' => true]);
        $organization = Organization::query()->create(['name' => 'Gate staff', 'slug' => 'gate-staff']);
        $staff->organizations()->attach($organization, [
            'role' => User::ORGANIZATION_ROLE_MEMBER,
            'status' => User::ORGANIZATION_STATUS_ACTIVE,
        ]);
        $event = Event::query()->create(['organization_id' => $organization->id, 'name' => 'Concert', 'slug' => 'concert']);
        $event->assignUser($staff, User::ORGANIZATION_ROLE_CHECK_IN_OFFICER);

        return $staff;
    }
}
