<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RoleAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_operational_roles_can_use_the_existing_read_only_modules(): void
    {
        foreach ([UserRole::Admin, UserRole::Supervisor, UserRole::Operator, UserRole::Viewer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/dashboard')->assertOk();
            $this->actingAs($user)->get('/contingencias/mapa')->assertOk();
            $this->actingAs($user)->get('/pronostico-meteorologico')->assertOk();
            $this->actingAs($user)->get('/buscador-operacional')->assertOk();
        }
    }

    public function test_guest_can_review_contingencies_and_reports_without_map_or_weather(): void
    {
        $guest = User::factory()->create([
            'role' => UserRole::Guest,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($guest)->get('/dashboard')->assertOk();
        $this->actingAs($guest)->get('/buscador-operacional')->assertOk();
        $this->actingAs($guest)->get('/informes')->assertOk();
        $this->actingAs($guest)->get('/contingencias/mapa')->assertForbidden();
        $this->actingAs($guest)->get('/contingencias/mapa/datos')->assertForbidden();
        $this->actingAs($guest)->get('/pronostico-meteorologico')->assertForbidden();
        $this->actingAs($guest)->get('/importaciones')->assertForbidden();

        $this->actingAs($guest)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.viewReports', true)
                ->where('auth.permissions.viewMap', false)
                ->where('auth.permissions.viewWeather', false)
                ->where('auth.permissions.updateContingencies', false)
                ->where('auth.permissions.viewSupplyIdentifiers', false));
    }

    public function test_admin_supervisor_and_guest_can_open_reports(): void
    {
        foreach ([UserRole::Admin, UserRole::Supervisor, UserRole::Guest] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get('/informes')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->where('auth.permissions.viewReports', true));
        }

        foreach ([UserRole::Operator, UserRole::Viewer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/informes')->assertForbidden();
            $this->actingAs($user)->get('/informes/contingencias.csv')->assertForbidden();
            $this->actingAs($user)->get('/informes/contingencias.pdf')->assertForbidden();
        }
    }

    public function test_backend_shares_account_management_only_with_admin(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);

        $this->actingAs($admin)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.manageGuestAccounts', true));

        $this->actingAs($supervisor)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.manageGuestAccounts', false));
    }

    public function test_an_inactive_or_expired_authenticated_user_is_logged_out(): void
    {
        foreach ([
            User::factory()->inactive()->create(),
            User::factory()->create(['expires_at' => now()->subMinute()]),
        ] as $user) {
            $this->actingAs($user)
                ->get('/dashboard')
                ->assertRedirect('/login')
                ->assertSessionHasErrors('email');

            $this->assertGuest();
        }
    }
}
