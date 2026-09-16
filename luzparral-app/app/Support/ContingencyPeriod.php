<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

final class ContingencyPeriod
{
    /** @var list<string> */
    public const RANGES = ['24h', '7d', '30d', '12m', 'all', 'day', 'month', 'year', 'custom'];

    /** @return array<string, array<int, mixed>> */
    public static function validationRules(): array
    {
        return [
            'range' => ['nullable', Rule::in(self::RANGES)],
            'date_day' => ['nullable', 'date_format:Y-m-d', 'required_if:range,day'],
            'date_month' => ['nullable', 'date_format:Y-m', 'required_if:range,month'],
            'date_year' => ['nullable', 'regex:/^\d{4}$/', 'required_if:range,year'],
            'date_from' => ['nullable', 'date_format:Y-m-d', 'required_if:range,custom'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'required_if:range,custom', 'after_or_equal:date_from'],
        ];
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array{range: string, date_day: ?string, date_month: ?string, date_year: ?string, date_from: ?string, date_to: ?string}
     */
    public static function normalize(array $validated, string $defaultRange = '12m'): array
    {
        $range = (string) ($validated['range'] ?? $defaultRange);
        $timezone = (string) config('app.timezone', 'America/Santiago');
        $dateDay = $range === 'day' ? ($validated['date_day'] ?? null) : null;
        $dateMonth = $range === 'month' ? ($validated['date_month'] ?? null) : null;
        $dateYear = $range === 'year' ? ($validated['date_year'] ?? null) : null;
        $dateFrom = $range === 'custom' ? ($validated['date_from'] ?? null) : null;
        $dateTo = $range === 'custom' ? ($validated['date_to'] ?? null) : null;

        if ($dateDay !== null) {
            $dateFrom = $dateDay;
            $dateTo = $dateDay;
        } elseif ($dateMonth !== null) {
            $month = CarbonImmutable::parse($dateMonth.'-01', $timezone);
            $dateFrom = $month->startOfMonth()->format('Y-m-d');
            $dateTo = $month->endOfMonth()->format('Y-m-d');
        } elseif ($dateYear !== null) {
            $year = CarbonImmutable::parse($dateYear.'-01-01', $timezone);
            $dateFrom = $year->startOfYear()->format('Y-m-d');
            $dateTo = $year->endOfYear()->format('Y-m-d');
        }

        return [
            'range' => $range,
            'date_day' => $dateDay,
            'date_month' => $dateMonth,
            'date_year' => $dateYear,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ];
    }

    /**
     * @param  array{range: string, date_from: ?string, date_to: ?string}  $filters
     */
    public static function apply(
        Builder $query,
        array $filters,
        CarbonImmutable $referenceDate,
        string $column = 'contingencies.started_at',
    ): void {
        if (in_array($filters['range'], ['day', 'month', 'year', 'custom'], true)) {
            $timezone = (string) config('app.timezone', 'America/Santiago');

            $query
                ->where($column, '>=', CarbonImmutable::parse($filters['date_from'], $timezone)->startOfDay())
                ->where($column, '<=', CarbonImmutable::parse($filters['date_to'], $timezone)->endOfDay());

            return;
        }

        $startDate = match ($filters['range']) {
            '24h' => $referenceDate->subDay(),
            '7d' => $referenceDate->startOfDay()->subDays(6),
            '30d' => $referenceDate->startOfDay()->subDays(29),
            '12m' => $referenceDate->subYear(),
            default => null,
        };

        if ($startDate !== null) {
            $query->where($column, '>=', $startDate);
        }
    }

    /** @param array{range: string, date_day?: ?string, date_month?: ?string, date_year?: ?string, date_from: ?string, date_to: ?string} $filters */
    public static function label(array $filters): string
    {
        if (in_array($filters['range'], ['day', 'month', 'year', 'custom'], true)) {
            $from = CarbonImmutable::parse($filters['date_from']);
            $to = CarbonImmutable::parse($filters['date_to']);

            return match ($filters['range']) {
                'day' => 'Día '.$from->format('d-m-Y'),
                'month' => 'Mes '.self::monthName((int) $from->format('n')).' de '.$from->format('Y'),
                'year' => 'Año '.$from->format('Y'),
                default => $from->format('d-m-Y').' al '.$to->format('d-m-Y'),
            };
        }

        return match ($filters['range']) {
            '24h' => 'Últimas 24 horas',
            '7d' => 'Últimos 7 días',
            '30d' => 'Últimos 30 días',
            '12m' => 'Últimos 12 meses',
            default => 'Todo el historial',
        };
    }

    /** @param array{range: string, date_from: ?string, date_to: ?string} $filters */
    public static function trendRange(array $filters): string
    {
        if (! in_array($filters['range'], ['day', 'month', 'year', 'custom'], true)) {
            return $filters['range'];
        }

        $days = CarbonImmutable::parse($filters['date_from'])
            ->diffInDays(CarbonImmutable::parse($filters['date_to']));

        return $days === 0 ? '24h' : ($days <= 62 ? '30d' : '12m');
    }

    private static function monthName(int $month): string
    {
        return [
            1 => 'enero',
            2 => 'febrero',
            3 => 'marzo',
            4 => 'abril',
            5 => 'mayo',
            6 => 'junio',
            7 => 'julio',
            8 => 'agosto',
            9 => 'septiembre',
            10 => 'octubre',
            11 => 'noviembre',
            12 => 'diciembre',
        ][$month];
    }
}
