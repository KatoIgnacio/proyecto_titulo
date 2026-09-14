<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserProvisioningTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $environmentNames = [];

    private ?string $accountsFile = null;

    protected function tearDown(): void
    {
        foreach ($this->environmentNames as $name) {
            putenv($name);
            unset($_ENV[$name], $_SERVER[$name]);
        }

        if ($this->accountsFile !== null && is_file($this->accountsFile)) {
            unlink($this->accountsFile);
        }

        parent::tearDown();
    }

    public function test_it_provisions_exactly_five_base_users_and_disables_synthetic_accounts(): void
    {
        $syntheticUser = User::factory()->create([
            'email' => 'admin@luzparral.example.invalid',
            'role' => UserRole::Admin,
        ]);
        DB::table('sessions')->insert([
            'id' => 'synthetic-session',
            'user_id' => $syntheticUser->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'test',
            'last_activity' => time(),
        ]);

        $records = [
            ['name' => 'Administración Base', 'email' => 'admin@empresa.test', 'role' => 'admin', 'password_env' => 'LUZPARRAL_USER_ADMIN_PASSWORD'],
            ['name' => 'Supervisión Base', 'email' => 'supervisor@empresa.test', 'role' => 'supervisor', 'password_env' => 'LUZPARRAL_USER_SUPERVISOR_PASSWORD'],
            ['name' => 'Operación Uno', 'email' => 'operador1@empresa.test', 'role' => 'operator', 'password_env' => 'LUZPARRAL_USER_OPERATOR1_PASSWORD'],
            ['name' => 'Operación Dos', 'email' => 'operador2@empresa.test', 'role' => 'operator', 'password_env' => 'LUZPARRAL_USER_OPERATOR2_PASSWORD'],
            ['name' => 'Consulta Base', 'email' => 'consulta@empresa.test', 'role' => 'viewer', 'password_env' => 'LUZPARRAL_USER_VIEWER_PASSWORD'],
        ];
        $this->accountsFile = tempnam(sys_get_temp_dir(), 'luzparral-users-');
        file_put_contents($this->accountsFile, json_encode($records, JSON_THROW_ON_ERROR));

        foreach (array_column($records, 'password_env') as $index => $name) {
            $this->setEnvironment($name, "ClaveSegura{$index}Aa!");
        }

        $this->artisan('luzparral:provision-users', [
            '--file' => $this->accountsFile,
            '--confirm' => true,
        ])->assertSuccessful();

        $this->assertSame(5, User::query()->where('active', true)->count());
        $this->assertFalse($syntheticUser->fresh()->active);
        $this->assertDatabaseMissing('sessions', ['id' => 'synthetic-session']);
        $this->assertSame(1, User::query()->where('role', UserRole::Admin)->where('active', true)->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Supervisor)->where('active', true)->count());
        $this->assertSame(2, User::query()->where('role', UserRole::Operator)->where('active', true)->count());
        $this->assertSame(1, User::query()->where('role', UserRole::Viewer)->where('active', true)->count());
        $this->assertTrue(Hash::check('ClaveSegura0Aa!', User::query()->where('email', 'admin@empresa.test')->firstOrFail()->password));
    }

    public function test_password_can_be_rotated_without_smtp_and_sessions_are_revoked(): void
    {
        $user = User::factory()->create(['email' => 'operador@empresa.test']);
        DB::table('sessions')->insert([
            'id' => 'active-session',
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'test',
            'last_activity' => time(),
        ]);
        $this->setEnvironment('LUZPARRAL_RESET_PASSWORD', 'RotacionSegura2026!');

        $this->artisan('luzparral:reset-user-password', [
            'email' => $user->email,
            '--confirm' => true,
        ])->assertSuccessful();

        $this->assertTrue(Hash::check('RotacionSegura2026!', $user->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'active-session']);
    }

    private function setEnvironment(string $name, string $value): void
    {
        putenv("{$name}={$value}");
        $_ENV[$name] = $value;
        $_SERVER[$name] = $value;
        $this->environmentNames[] = $name;
    }
}
