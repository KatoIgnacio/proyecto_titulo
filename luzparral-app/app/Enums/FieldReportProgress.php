<?php

namespace App\Enums;

enum FieldReportProgress: string
{
    case Inspection = 'inspection';
    case Repair = 'repair';
    case AwaitingResources = 'awaiting_resources';
    case Completed = 'completed';

    public function label(): string
    {
        return match ($this) {
            self::Inspection => 'Inspección en terreno',
            self::Repair => 'Trabajos de reparación',
            self::AwaitingResources => 'En espera de recursos',
            self::Completed => 'Trabajo completado',
        };
    }
}
