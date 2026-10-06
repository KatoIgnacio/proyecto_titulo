<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Supervisor = 'supervisor';
    case Operator = 'operator';
    case Viewer = 'viewer';
    case Guest = 'guest';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administración',
            self::Supervisor => 'Supervisión',
            self::Operator => 'Operación',
            self::Viewer => 'Consulta',
            self::Guest => 'Visita',
        };
    }

    public function canViewReports(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Supervisor,
            self::Guest,
        ], true);
    }

    public function canViewMap(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Supervisor,
            self::Operator,
            self::Viewer,
        ], true);
    }

    public function canViewWeather(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Supervisor,
            self::Operator,
            self::Viewer,
        ], true);
    }

    public function canManageGuestAccounts(): bool
    {
        return $this === self::Admin;
    }

    public function canUpdateContingencies(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Supervisor,
            self::Operator,
        ], true);
    }

    public function canRegisterFieldReports(): bool
    {
        return $this->canUpdateContingencies();
    }

    public function canManageFieldReports(): bool
    {
        return $this === self::Admin;
    }

    public function canImportContingencies(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Supervisor,
        ], true);
    }

    public function canViewSupplyIdentifiers(): bool
    {
        return in_array($this, [
            self::Admin,
            self::Supervisor,
            self::Operator,
        ], true);
    }
}
