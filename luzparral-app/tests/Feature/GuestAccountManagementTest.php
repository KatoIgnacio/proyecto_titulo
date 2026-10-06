<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class GuestAccountManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_admin_can_open_and_modify_guest_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $guest = User::factory()->create(['role' => UserRole::Guest]);

        $this->actingAs($admin)
            ->get('/cuentas-visita')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GuestAccounts/Index')
                ->where('maxActiveUsers', 10));

        $this->actingAs($supervisor)->get('/cuentas-visita')->assertForbidden();
        $this->actingAs($supervisor)->post('/cuentas-visita', [
            'name' => 'Visita no autorizada',
            'duration_hours' => 24,
        ])->assertForbidden();
        $this->actingAs($supervisor)->delete('/cuentas-visita/'.$guest->id)->assertForbidden();
    }

    public function test_admin_creates_a_temporary_guest_with_one_time_credentials(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->post('/cuentas-visita', [
            'name' => 'Visita comité sintética',
            'duration_hours' => 24,
        ]);

        $response
            ->assertRedirect('/cuentas-visita')
            ->assertSessionHas('guest_credentials');

        $guest = User::query()->where('role', UserRole::Guest)->sole();
        $credentials = session('guest_credentials');

        $this->assertIsArray($credentials);
        $this->assertSame('Visita comité sintética', $guest->name);
        $this->assertStringEndsWith('@luzparral.example.invalid', $guest->email);
        $this->assertSame($admin->id, $guest->created_by);
        $this->assertTrue($guest->isActive());
        $this->assertTrue(Hash::check($credentials['password'], $guest->password));
        $this->assertTrue($guest->expires_at->between(now()->addHours(23), now()->addHours(25)));
    }

    public function test_admin_can_deactivate_a_guest_and_revoke_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $guest = User::factory()->create([
            'role' => UserRole::Guest,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->delete('/cuentas-visita/'.$guest->id)
            ->assertRedirect('/cuentas-visita');

        $this->assertFalse($guest->fresh()->active);
        $this->actingAs($guest->fresh())->get('/dashboard')->assertRedirect('/login');
    }

    public function test_creation_respects_the_institutional_active_account_limit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->count(9)->create();

        $this->actingAs($admin)
            ->post('/cuentas-visita', [
                'name' => 'Visita excedente',
                'duration_hours' => 24,
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['name' => 'Visita excedente']);
    }

    public function test_guest_duration_must_use_an_approved_window(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post('/cuentas-visita', [
                'name' => 'Visita inválida',
                'duration_hours' => 999,
            ])
            ->assertSessionHasErrors('duration_hours');
    }
}
