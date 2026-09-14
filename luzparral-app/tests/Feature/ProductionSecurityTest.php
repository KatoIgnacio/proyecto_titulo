<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ProductionSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_recovery_is_hidden_and_rejected_when_disabled(): void
    {
        config()->set('security.password_reset_enabled', false);

        $this->get('/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canResetPassword', false));

        $this->get('/forgot-password')->assertNotFound();
        $this->post('/forgot-password', ['email' => 'usuario@example.com'])->assertNotFound();
    }

    public function test_security_headers_are_applied_to_dynamic_responses(): void
    {
        config()->set('security.headers_enabled', true);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()')
            ->assertHeader('Cross-Origin-Opener-Policy', 'same-origin')
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Content-Security-Policy');
    }

    public function test_production_template_disables_log_based_recovery_and_persistent_sessions(): void
    {
        $contents = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression('/^PASSWORD_RESET_ENABLED=false$/m', $contents);
        $this->assertMatchesRegularExpression('/^SECURITY_HEADERS_ENABLED=true$/m', $contents);
        $this->assertMatchesRegularExpression('/^SESSION_EXPIRE_ON_CLOSE=true$/m', $contents);
        $this->assertMatchesRegularExpression('/^SESSION_LIFETIME=60$/m', $contents);
        $this->assertMatchesRegularExpression('/^AUTH_PASSWORD_TIMEOUT=900$/m', $contents);
    }

    public function test_synthetic_tools_require_an_external_password_and_never_print_it(): void
    {
        $generator = file_get_contents(base_path('database/synthetic/generate_synthetic.php'));
        $seeder = file_get_contents(base_path('database/seeders/DatabaseSeeder.php'));

        $this->assertIsString($generator);
        $this->assertIsString($seeder);
        $this->assertStringContainsString('LUZPARRAL_DEMO_PASSWORD', $generator);
        $this->assertStringContainsString('LUZPARRAL_DEMO_PASSWORD', $seeder);
        $this->assertStringNotContainsString('const DEMO_PASSWORD', $generator);
        $this->assertStringNotContainsString("'demo_password'", $generator);
        $this->assertStringContainsString('app()->isProduction()', $seeder);
    }

    public function test_private_user_files_are_ignored_by_git_and_container_builds(): void
    {
        foreach (['.gitignore', '.dockerignore', '.containerignore'] as $ignoreFile) {
            $contents = file_get_contents(base_path($ignoreFile));

            $this->assertIsString($contents);
            $this->assertMatchesRegularExpression('/^luzparral-users\*\.json$/m', $contents);
        }
    }
}
