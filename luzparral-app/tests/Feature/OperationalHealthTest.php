<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use PDOException;
use Tests\TestCase;

class OperationalHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_does_not_require_a_database_probe(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_command_reports_application_health_as_json(): void
    {
        $exitCode = Artisan::call('luzparral:health', ['--json' => true]);
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $result['status']);
        $this->assertSame('ok', $result['checks']['application']);
        $this->assertArrayNotHasKey('database', $result['checks']);
    }

    public function test_command_verifies_the_database_and_required_schema(): void
    {
        $exitCode = Artisan::call('luzparral:health', [
            '--database' => true,
            '--json' => true,
        ]);
        $result = json_decode(Artisan::output(), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(0, $exitCode);
        $this->assertSame('ok', $result['status']);
        $this->assertSame('ok', $result['checks']['database']);
        $this->assertSame('ok', $result['checks']['schema']);
    }

    public function test_database_connection_failure_returns_a_sanitized_service_error(): void
    {
        Route::get('/_test/database-unavailable', function (): never {
            $exception = new PDOException('host=db.internal user=root password=secreto', 2002);
            $exception->errorInfo = ['HY000', 2002, 'Connection refused'];

            throw $exception;
        });

        $response = $this->getJson('/_test/database-unavailable');

        $response
            ->assertStatus(503)
            ->assertHeader('Retry-After', '30')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertJsonPath('message', 'El servicio de datos no está disponible temporalmente.')
            ->assertJsonStructure(['message', 'diagnostic_id']);

        $this->assertNotEmpty($response->headers->get('X-Diagnostic-ID'));
        $this->assertStringNotContainsString('db.internal', $response->getContent());
        $this->assertStringNotContainsString('secreto', $response->getContent());
    }
}
