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

    public function test_every_active_role_can_use_read_only_operational_modules(): void
    {
        foreach (UserRole::cases() as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)->get('/dashboard')->assertOk();
            $this->actingAs($user)->get('/contingencias/mapa')->assertOk();
            $this->actingAs($user)->get('/pronostico-meteorologico')->assertOk();
            $this->actingAs($user)->get('/buscador-operacional')->assertOk();
        }
    }

    public function test_only_admin_and_supervisor_can_open_reports(): void
    {
        foreach ([UserRole::Admin, UserRole::Supervisor] as $role) {
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

    public function test_backend_shares_the_report_permission_with_the_interface(): void
    {
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $viewer = User::factory()->create(['role' => UserRole::Viewer]);

        $this->actingAs($supervisor)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.viewReports', true));

        $this->actingAs($viewer)
            ->get('/dashboard')
            ->assertInertia(fn (Assert $page) => $page
                ->where('auth.permissions.viewReports', false));
    }

    public function test_an_inactive_authenticated_user_is_logged_out(): void
    {
        $user = User::factory()->inactive()->create();

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect('/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }
}
