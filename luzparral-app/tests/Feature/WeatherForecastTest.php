<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WeatherForecastTest extends TestCase
{
    use RefreshDatabase;

    public function test_forecast_requires_authentication(): void
    {
        $this->get('/pronostico-meteorologico')->assertRedirect('/login');
    }

    public function test_internal_active_roles_can_view_the_windy_forecast(): void
    {
        foreach ([UserRole::Admin, UserRole::Supervisor, UserRole::Operator, UserRole::Viewer] as $role) {
            $user = User::factory()->create(['role' => $role]);

            $this->actingAs($user)
                ->get('/pronostico-meteorologico')
                ->assertOk()
                ->assertInertia(fn (Assert $page) => $page
                    ->component('WeatherForecast')
                    ->where('embedUrl', fn (string $url) => str_starts_with($url, 'https://embed.windy.com/')));
        }
    }

    public function test_guest_cannot_view_the_windy_forecast(): void
    {
        $guest = User::factory()->create(['role' => UserRole::Guest]);

        $this->actingAs($guest)
            ->get('/pronostico-meteorologico')
            ->assertForbidden();
    }
}
