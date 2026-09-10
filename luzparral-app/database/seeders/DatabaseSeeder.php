<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
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
                    'password' => 'LuzparralDemo2026!',
                    'role' => $role,
                    'active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
