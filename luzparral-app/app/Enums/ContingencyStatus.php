<?php

namespace App\Enums;

enum ContingencyStatus: string
{
    case Reported = 'reported';
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case Restored = 'restored';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Reported => 'Reportada',
            self::Assigned => 'Asignada',
            self::InProgress => 'En atención',
            self::Restored => 'Repuesta',
            self::Closed => 'Cerrada',
        };
    }

    /** @return list<self> */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Reported => [self::Assigned],
            self::Assigned => [self::InProgress],
            self::InProgress => [self::Restored],
            self::Restored => [self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }
}
