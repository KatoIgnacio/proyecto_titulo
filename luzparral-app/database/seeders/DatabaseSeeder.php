<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use RuntimeException;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('El seeder de demostración no se ejecuta en producción.');
        }

        $password = env('LUZPARRAL_DEMO_PASSWORD');
        if (! is_string($password) || strlen($password) < 12) {
            throw new RuntimeException('Defina LUZPARRAL_DEMO_PASSWORD con al menos 12 caracteres.');
        }

        $users = [
            ['Administración Demo', 'admin@luzparral.example.invalid', 'admin'],
            ['Supervisión Demo', 'supervisor@luzparral.example.invalid', 'supervisor'],
            ['Operación Turno A', 'operador.a@luzparral.example.invalid', 'operator'],
            ['Operación Turno B', 'operador.b@luzparral.example.invalid', 'operator'],
            ['Consulta Demo', 'consulta@luzparral.example.invalid', 'viewer'],
        ];

        foreach ($users as [$name, $email, $role]) {
            User::query()->updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => $password,
                    'role' => $role,
                    'active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
