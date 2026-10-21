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

    public function test_only_admin_can_open_and_modify_temporary_viewer_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $supervisor = User::factory()->create(['role' => UserRole::Supervisor]);
        $temporaryViewer = User::factory()->create([
            'role' => UserRole::Viewer,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->get('/cuentas-consulta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GuestAccounts/Index')
                ->where('maxActiveUsers', 10));

        $this->actingAs($supervisor)->get('/cuentas-consulta')->assertForbidden();
        $this->actingAs($supervisor)->post('/cuentas-consulta', [
            'name' => 'Consulta temporal no autorizada',
            'duration_hours' => 24,
        ])->assertForbidden();
        $this->actingAs($supervisor)->delete('/cuentas-consulta/'.$temporaryViewer->id)->assertForbidden();
    }

    public function test_admin_creates_a_temporary_viewer_with_one_time_credentials(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $response = $this->actingAs($admin)->post('/cuentas-consulta', [
            'name' => 'Consulta comité sintética',
            'duration_hours' => 24,
        ]);

        $response
            ->assertRedirect('/cuentas-consulta')
            ->assertSessionHas('temporary_viewer_credentials');

        $temporaryViewer = User::query()
            ->where('role', UserRole::Viewer)
            ->whereNotNull('expires_at')
            ->sole();
        $credentials = session('temporary_viewer_credentials');

        $this->assertIsArray($credentials);
        $this->assertSame('Consulta comité sintética', $temporaryViewer->name);
        $this->assertStringStartsWith('consulta-temporal-', $temporaryViewer->email);
        $this->assertStringEndsWith('@luzparral.example.invalid', $temporaryViewer->email);
        $this->assertSame($admin->id, $temporaryViewer->created_by);
        $this->assertTrue($temporaryViewer->isActive());
        $this->assertTrue(Hash::check($credentials['password'], $temporaryViewer->password));
        $this->assertTrue($temporaryViewer->expires_at->between(now()->addHours(23), now()->addHours(25)));
    }

    public function test_admin_list_includes_self_registered_temporary_viewers(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $selfRegisteredViewer = User::factory()->create([
            'role' => UserRole::Viewer,
            'expires_at' => now()->addDays(7),
            'created_by' => null,
        ]);

        $this->actingAs($admin)
            ->get('/cuentas-consulta')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('GuestAccounts/Index')
                ->has('accounts', 1)
                ->where('accounts.0.id', $selfRegisteredViewer->id)
                ->where('accounts.0.email', $selfRegisteredViewer->email));
    }

    public function test_admin_can_deactivate_a_temporary_viewer_and_revoke_access(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $temporaryViewer = User::factory()->create([
            'role' => UserRole::Viewer,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($admin)
            ->delete('/cuentas-consulta/'.$temporaryViewer->id)
            ->assertRedirect('/cuentas-consulta');

        $this->assertFalse($temporaryViewer->fresh()->active);
        $this->actingAs($temporaryViewer->fresh())->get('/dashboard')->assertRedirect('/login');
    }

    public function test_admin_cannot_deactivate_the_permanent_viewer_account_from_temporary_accounts(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        $permanentViewer = User::factory()->create([
            'role' => UserRole::Viewer,
            'expires_at' => null,
        ]);

        $this->actingAs($admin)
            ->delete('/cuentas-consulta/'.$permanentViewer->id)
            ->assertNotFound();

        $this->assertTrue($permanentViewer->fresh()->active);
    }

    public function test_creation_respects_the_institutional_active_account_limit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        User::factory()->count(9)->create();

        $this->actingAs($admin)
            ->post('/cuentas-consulta', [
                'name' => 'Consulta temporal excedente',
                'duration_hours' => 24,
            ])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseMissing('users', ['name' => 'Consulta temporal excedente']);
    }

    public function test_temporary_viewer_duration_must_use_an_approved_window(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->post('/cuentas-consulta', [
                'name' => 'Consulta temporal inválida',
                'duration_hours' => 999,
            ])
            ->assertSessionHasErrors('duration_hours');
    }
}
