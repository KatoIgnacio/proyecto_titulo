import { Fragment } from 'react';

export type PeriodFilterValue = {
    range: string;
    date_day: string;
    date_month: string;
    date_year: string;
    date_from: string;
    date_to: string;
};

type PeriodFieldsProps<T extends PeriodFilterValue> = {
    value: T;
    onChange: (value: T) => void;
    maxDate: string;
    labelClassName: string;
    controlClassName: string;
};

export default function PeriodFields<T extends PeriodFilterValue>({
    value,
    onChange,
    maxDate,
    labelClassName,
    controlClassName,
}: PeriodFieldsProps<T>) {
    const updateRange = (range: string) => {
        onChange({
            ...value,
            range,
            date_day: range === 'day' ? value.date_day || maxDate : '',
            date_month: range === 'month' ? value.date_month || maxDate.slice(0, 7) : '',
            date_year: range === 'year' ? value.date_year || maxDate.slice(0, 4) : '',
            date_from: range === 'custom' ? value.date_from : '',
            date_to: range === 'custom' ? value.date_to : '',
        });
    };

    return (
        <Fragment>
            <label className={labelClassName}>
                Período
                <select
                    value={value.range}
                    onChange={(event) => updateRange(event.target.value)}
                    className={controlClassName}
                >
                    <option value="24h">Últimas 24 horas</option>
                    <option value="7d">Últimos 7 días</option>
                    <option value="30d">Últimos 30 días</option>
                    <option value="12m">Últimos 12 meses</option>
                    <option value="all">Todo el historial</option>
                    <option value="day">Día específico</option>
                    <option value="month">Mes completo</option>
                    <option value="year">Año completo</option>
                    <option value="custom">Rango personalizado</option>
                </select>
            </label>

            {value.range === 'day' && (
                <label className={labelClassName}>
                    Día
                    <input
                        type="date"
                        value={value.date_day}
                        max={maxDate}
                        onChange={(event) => onChange({ ...value, date_day: event.target.value })}
                        className={controlClassName}
                        required
                    />
                </label>
            )}

            {value.range === 'month' && (
                <label className={labelClassName}>
                    Mes
                    <input
                        type="month"
                        value={value.date_month}
                        max={maxDate.slice(0, 7)}
                        onChange={(event) => onChange({ ...value, date_month: event.target.value })}
                        className={controlClassName}
                        required
                    />
                </label>
            )}

            {value.range === 'year' && (
                <label className={labelClassName}>
                    Año
                    <input
                        type="number"
                        inputMode="numeric"
                        min="2000"
                        max={maxDate.slice(0, 4)}
                        step="1"
                        value={value.date_year}
                        onChange={(event) => onChange({ ...value, date_year: event.target.value })}
                        className={controlClassName}
                        required
                    />
                </label>
            )}

            {value.range === 'custom' && (
                <>
                    <label className={labelClassName}>
                        Desde
                        <input
                            type="date"
                            value={value.date_from}
                            max={value.date_to || maxDate}
                            onChange={(event) => onChange({ ...value, date_from: event.target.value })}
                            className={controlClassName}
                            required
                        />
                    </label>
                    <label className={labelClassName}>
                        Hasta
                        <input
                            type="date"
                            value={value.date_to}
                            min={value.date_from || undefined}
                            max={maxDate}
                            onChange={(event) => onChange({ ...value, date_to: event.target.value })}
                            className={controlClassName}
                            required
                        />
                    </label>
                </>
            )}
        </Fragment>
    );
}
