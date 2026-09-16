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

    public function canViewReports(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Supervisor,
        ], true);
    }

    public function canUpdateContingencies(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Supervisor,
            self::Operator,
        ], true);
    }
}
