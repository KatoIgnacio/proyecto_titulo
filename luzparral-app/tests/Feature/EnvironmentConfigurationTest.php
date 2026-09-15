<?php

namespace Tests\Feature;

use Tests\TestCase;

class EnvironmentConfigurationTest extends TestCase
{
    public function test_application_uses_chilean_regional_defaults(): void
    {
        $this->assertSame('America/Santiago', config('app.timezone'));
        $this->assertSame('es', config('app.locale'));
        $this->assertSame('es', config('app.fallback_locale'));
        $this->assertSame('es_CL', config('app.faker_locale'));
    }

    public function test_production_environment_example_has_safe_required_values(): void
    {
        $contents = file_get_contents(base_path('.env.production.example'));

        $this->assertIsString($contents);
        $this->assertMatchesRegularExpression('/^APP_ENV=production$/m', $contents);
        $this->assertMatchesRegularExpression('/^APP_DEBUG=false$/m', $contents);
        $this->assertMatchesRegularExpression('/^APP_TIMEZONE=America\/Santiago$/m', $contents);
        $this->assertMatchesRegularExpression('/^LOG_CHANNEL=stderr_json$/m', $contents);
        $this->assertMatchesRegularExpression('/^SESSION_ENCRYPT=true$/m', $contents);
        $this->assertMatchesRegularExpression('/^APP_KEY=$/m', $contents);
        $this->assertMatchesRegularExpression('/^DB_PASSWORD=$/m', $contents);
        $this->assertMatchesRegularExpression('/^PASSWORD_RESET_ENABLED=false$/m', $contents);
        $this->assertMatchesRegularExpression('/^SECURITY_HEADERS_ENABLED=true$/m', $contents);
    }
}
