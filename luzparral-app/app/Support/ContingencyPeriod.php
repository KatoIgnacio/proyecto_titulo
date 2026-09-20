<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;

final class ContingencyPeriod
{
    /** @var list<string> */
    public const RANGES = ['custom', 'day', 'month', 'year', 'all'];

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
    public static function normalize(array $validated, ?CarbonImmutable $referenceDate = null): array
    {
        $timezone = (string) config('app.timezone', 'America/Santiago');
        $referenceDate = ($referenceDate ?? CarbonImmutable::now($timezone))->setTimezone($timezone);
        $range = (string) ($validated['range'] ?? 'custom');
        $dateDay = $range === 'day' ? ($validated['date_day'] ?? null) : null;
        $dateMonth = $range === 'month' ? ($validated['date_month'] ?? null) : null;
        $dateYear = $range === 'year' ? ($validated['date_year'] ?? null) : null;
        $dateFrom = null;
        $dateTo = null;

        if ($range === 'custom') {
            $explicitCustomRange = ($validated['range'] ?? null) === 'custom';
            $dateFrom = $explicitCustomRange
                ? ($validated['date_from'] ?? null)
                : $referenceDate->startOfMonth()->format('Y-m-d');
            $dateTo = $explicitCustomRange
                ? ($validated['date_to'] ?? null)
                : $referenceDate->format('Y-m-d');
        }

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

        // `all` intentionally leaves the query without date restrictions.
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

        return 'Todo el historial';
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
