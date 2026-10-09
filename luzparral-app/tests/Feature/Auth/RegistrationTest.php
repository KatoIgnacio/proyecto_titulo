<?php

namespace Tests\Feature\Auth;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_is_available_from_the_public_entry_point(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Auth/Register'));
    }

    public function test_public_registration_creates_only_a_temporary_viewer_and_logs_it_in(): void
    {
        $response = $this->post('/register', [
            'name' => 'Evaluación Comité',
            'email' => 'evaluacion@example.com',
            'password' => 'Prueba-Segura-2026!',
            'password_confirmation' => 'Prueba-Segura-2026!',
            'role' => UserRole::Admin->value,
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'evaluacion@example.com')->sole();

        $this->assertSame('Evaluación Comité', $user->name);
        $this->assertSame(UserRole::Viewer, $user->role);
        $this->assertTrue($user->active);
        $this->assertNull($user->created_by);
        $this->assertTrue(Hash::check('Prueba-Segura-2026!', $user->password));
        $this->assertTrue($user->expires_at->between(
            now()->addDays(6)->addHours(23),
            now()->addDays(7)->addMinute(),
        ));
    }

    public function test_public_registration_enforces_password_policy_and_unique_email(): void
    {
        User::factory()->create(['email' => 'ocupado@example.com']);

        $this->post('/register', [
            'name' => 'Evaluación',
            'email' => 'ocupado@example.com',
            'password' => 'debil',
            'password_confirmation' => 'debil',
        ])->assertSessionHasErrors(['email', 'password']);

        $this->assertGuest();
    }

    public function test_public_registration_respects_the_active_account_limit(): void
    {
        User::factory()->count(10)->create();

        $this->post('/register', [
            'name' => 'Sin cupo',
            'email' => 'sin-cupo@example.com',
            'password' => 'Prueba-Segura-2026!',
            'password_confirmation' => 'Prueba-Segura-2026!',
        ])->assertSessionHasErrors('email');

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['email' => 'sin-cupo@example.com']);
    }
}
