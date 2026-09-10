<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Operator = 'operator';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administración',
            self::Supervisor => 'Supervisión',
            self::Operator => 'Operación',
            self::Viewer => 'Consulta',
        };
    }
}
