import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PeriodFields, { defaultCustomPeriod, type PeriodFilterValue } from '@/Components/PeriodFields';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, ReactNode, useEffect, useMemo, useState } from 'react';

type Filters = {
    range: string;
    date_day: string | null;
    date_month: string | null;
    date_year: string | null;
    date_from: string | null;
    date_to: string | null;
    commune: number | null;
    feeder: number | null;
    priority: string | null;
    status: string | null;
    search: string;
};

type FilterForm = PeriodFilterValue & {
    commune: string;
    feeder: string;
    priority: string;
    status: string;
    search: string;
};

type Option = { id: number; name: string };
type FeederOption = Option & { commune_id: number; code: string };

type Metrics = {
    total: number;
    active: number;
    affected: number;
    restored: number;
    critical: number;
    electrodependent: number;
    averageMinutes: number | null;
};

type TrendPoint = {
    date: string;
    incidents: number;
    affected: number;
    restored: number;
};

type CommuneDistribution = {
    id: number;
    name: string;
    incidents: number;
    affected: number;
};

type StatusDistribution = { status: string; total: number };

type ContingencyRow = {
    id: number;
    code: string;
    osf_code: string;
    commune: string | null;
    feeder: string | null;
    status: string;
    priority: string;
    affected_total: number;
    critical_affected: number;
    electrodependent_affected: number;
    started_at: string | null;
    restored_at: string | null;
};

type DashboardProps = {
    filters: Filters;
    referenceDate: string;
    filterOptions: {
        communes: Option[];
        feeders: FeederOption[];
    };
    metrics: Metrics;
    trend: TrendPoint[];
    communeDistribution: CommuneDistribution[];
    statusDistribution: StatusDistribution[];
    contingencies: ContingencyRow[];
};

const statusLabels: Record<string, string> = {
    reported: 'Reportada',
    assigned: 'Asignada',
    in_progress: 'En atención',
    restored: 'Repuesta',
    closed: 'Cerrada',
};

const priorityLabels: Record<string, string> = {
    critical: 'Crítica',
    high: 'Alta',
    medium: 'Media',
    low: 'Baja',
};

const statusStyles: Record<string, string> = {
    reported: 'bg-violet-100 text-violet-700',
    assigned: 'bg-sky-100 text-sky-700',
    in_progress: 'bg-amber-100 text-amber-700',
    restored: 'bg-emerald-100 text-emerald-700',
    closed: 'bg-slate-200 text-slate-700',
};

const priorityStyles: Record<string, string> = {
    critical: 'bg-rose-100 text-rose-700',
    high: 'bg-orange-100 text-orange-700',
    medium: 'bg-blue-100 text-blue-700',
    low: 'bg-slate-100 text-slate-600',
};

const numberFormatter = new Intl.NumberFormat('es-CL');

function formatNumber(value: number) {
    return numberFormatter.format(value);
}

function formatDate(value: string | null, withTime = true) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('es-CL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    }).format(new Date(value));
}

function MetricCard({
    label,
    value,
    note,
    accent,
    icon,
}: {
    label: string;
    value: string;
    note: string;
    accent: string;
    icon: ReactNode;
}) {
    return (
        <article className={`relative overflow-hidden rounded-xl border border-slate-200 bg-white p-5 shadow-sm ${accent}`}>
            <div className="flex items-start justify-between gap-3">
                <div>
                    <p className="text-[11px] font-bold uppercase tracking-[0.08em] text-slate-500">{label}</p>
                    <p className="mt-2 text-3xl font-black tracking-tight text-slate-900">{value}</p>
                </div>
                <div className="rounded-lg bg-slate-50 p-2 text-slate-500">{icon}</div>
            </div>
            <p className="mt-3 text-xs text-slate-500">{note}</p>
        </article>
    );
}

function MiniIcon({ path }: { path: ReactNode }) {
    return (
        <svg
            aria-hidden="true"
            className="h-5 w-5"
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            {path}
        </svg>
    );
}

function TrendChart({ points }: { points: TrendPoint[] }) {
    if (points.length === 0) {
        return (
            <div className="flex h-64 items-center justify-center rounded-lg bg-slate-50 text-sm text-slate-500">
                No hay datos para el período seleccionado.
            </div>
        );
    }

    const width = 760;
    const height = 260;
    const paddingX = 42;
    const paddingTop = 20;
    const paddingBottom = 40;
    const plotHeight = height - paddingTop - paddingBottom;
    const maxValue = Math.max(1, ...points.flatMap((point) => [point.affected, point.restored]));
    const stepX = points.length === 1 ? 0 : (width - paddingX * 2) / (points.length - 1);
    const toY = (value: number) => paddingTop + plotHeight - (value / maxValue) * plotHeight;
    const toPolyline = (key: 'affected' | 'restored') =>
        points.map((point, index) => `${paddingX + index * stepX},${toY(point[key])}`).join(' ');

    return (
        <div className="overflow-hidden">
            <div className="mb-3 flex justify-end gap-5 text-xs text-slate-500">
                <span className="flex items-center gap-2"><i className="h-0.5 w-5 bg-rose-500" />Afectados</span>
                <span className="flex items-center gap-2"><i className="h-0.5 w-5 bg-emerald-500" />Repuestos</span>
            </div>
            <svg
                aria-label="Evolución de clientes afectados y repuestos"
                className="h-auto w-full min-w-[600px]"
                role="img"
                viewBox={`0 0 ${width} ${height}`}
            >
                {[0, 0.25, 0.5, 0.75, 1].map((ratio) => {
                    const y = paddingTop + plotHeight - ratio * plotHeight;
                    return (
                        <g key={ratio}>
                            <line x1={paddingX} x2={width - paddingX} y1={y} y2={y} stroke="#e2e8f0" strokeDasharray="4 6" />
                            <text x="3" y={y + 4} fill="#94a3b8" fontSize="10">{formatNumber(Math.round(maxValue * ratio))}</text>
                        </g>
                    );
                })}
                <polyline fill="none" points={toPolyline('affected')} stroke="#f43f5e" strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" />
                <polyline fill="none" points={toPolyline('restored')} stroke="#10b981" strokeLinecap="round" strokeLinejoin="round" strokeWidth="3" />
                {points.map((point, index) => {
                    const x = paddingX + index * stepX;
                    const showLabel = points.length <= 7 || index === 0 || index === points.length - 1 || index % 2 === 0;
                    return (
                        <g key={`${point.date}-${index}`}>
                            <circle cx={x} cy={toY(point.affected)} fill="white" r="4" stroke="#f43f5e" strokeWidth="2.5" />
                            <circle cx={x} cy={toY(point.restored)} fill="white" r="4" stroke="#10b981" strokeWidth="2.5" />
                            {showLabel && (
                                <text x={x} y={height - 13} fill="#64748b" fontSize="10" textAnchor="middle">
                                    {new Intl.DateTimeFormat('es-CL', { day: '2-digit', month: 'short' }).format(new Date(`${point.date}T12:00:00`))}
                                </text>
                            )}
                        </g>
                    );
                })}
            </svg>
        </div>
    );
}

type PieChartItem = {
    key: string | number;
    label: string;
    value: number;
    color: string;
};

function DistributionPieChart({
    items,
    ariaLabel,
    prominent = false,
}: {
    items: PieChartItem[];
    ariaLabel: string;
    prominent?: boolean;
}) {
    const visibleItems = items.filter((item) => item.value > 0);
    const total = visibleItems.reduce((sum, item) => sum + item.value, 0);
    const radius = 48;
    const circumference = 2 * Math.PI * radius;
    let offset = 0;
    const segments = visibleItems.map((item) => {
        const length = total === 0 ? 0 : (item.value / total) * circumference;
        const segment = {
            ...item,
            length,
            offset,
        };
        offset += length;

        return segment;
    });

    if (total === 0) {
        return <p className="py-8 text-center text-sm text-slate-500">Sin contingencias para mostrar.</p>;
    }

    return (
        <div className={`grid items-center gap-8 ${prominent ? 'md:grid-cols-[220px_minmax(0,1fr)]' : 'md:grid-cols-[150px_minmax(0,1fr)]'}`}>
            <div className={`mx-auto w-full ${prominent ? 'max-w-[220px]' : 'max-w-[150px]'}`}>
                <svg
                    aria-label={ariaLabel}
                    className="h-auto w-full"
                    role="img"
                    viewBox="0 0 140 140"
                >
                    <title>{ariaLabel}</title>
                    <circle cx="70" cy="70" r={radius} fill="none" stroke="#e2e8f0" strokeWidth={prominent ? 22 : 18} />
                    {segments.map((segment) => (
                        <circle
                            key={segment.key}
                            cx="70"
                            cy="70"
                            r={radius}
                            fill="none"
                            stroke={segment.color}
                            strokeDasharray={`${segment.length} ${circumference - segment.length}`}
                            strokeDashoffset={-segment.offset}
                            strokeWidth={prominent ? 22 : 18}
                            transform="rotate(-90 70 70)"
                        />
                    ))}
                    <text x="70" y="66" fill="#0f172a" fontSize="20" fontWeight="800" textAnchor="middle">{formatNumber(total)}</text>
                    <text x="70" y="82" fill="#64748b" fontSize="8" fontWeight="600" textAnchor="middle">CONTINGENCIAS</text>
                </svg>
            </div>

            <div className={`grid gap-x-8 gap-y-3 ${prominent ? 'sm:grid-cols-2' : 'grid-cols-1'}`}>
                {segments.map((segment) => (
                    <div key={segment.key} className="flex items-center justify-between gap-4 border-b border-slate-100 pb-3 text-sm">
                        <span className="flex min-w-0 items-center gap-2.5 font-semibold text-slate-700">
                            <i className="h-2.5 w-2.5 shrink-0 rounded-full" style={{ backgroundColor: segment.color }} />
                            <span className="truncate">{segment.label}</span>
                        </span>
                        <span className="whitespace-nowrap text-right tabular-nums">
                            <strong className="text-slate-900">{Math.round((segment.value / total) * 100)}%</strong>
                            <span className="ml-2 text-xs text-slate-500">{segment.value}</span>
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function Dashboard({
    filters,
    referenceDate,
    filterOptions,
    metrics,
    trend,
    communeDistribution,
    statusDistribution,
    contingencies,
}: DashboardProps) {
    const toForm = (source: Filters): FilterForm => ({
        range: source.range,
        date_day: source.date_day ?? '',
        date_month: source.date_month ?? '',
        date_year: source.date_year ?? '',
        date_from: source.date_from ?? '',
        date_to: source.date_to ?? '',
        commune: source.commune?.toString() ?? '',
        feeder: source.feeder?.toString() ?? '',
        priority: source.priority ?? '',
        status: source.status ?? '',
        search: source.search,
    });

    const [form, setForm] = useState<FilterForm>(() => toForm(filters));

    useEffect(() => setForm(toForm(filters)), [filters]);

    const availableFeeders = useMemo(
        () => filterOptions.feeders.filter((feeder) => !form.commune || feeder.commune_id === Number(form.commune)),
        [filterOptions.feeders, form.commune],
    );

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('dashboard'), form, { preserveScroll: true, replace: true });
    };

    const reset = () => {
        setForm({ ...defaultCustomPeriod(referenceDate), commune: '', feeder: '', priority: '', status: '', search: '' });
        router.get(route('dashboard'), {}, { replace: true });
    };

    const defaultPeriod = defaultCustomPeriod(referenceDate);
    const hasFilters = Boolean(
        form.commune
        || form.feeder
        || form.priority
        || form.status
        || form.search
        || form.range !== defaultPeriod.range
        || form.date_from !== defaultPeriod.date_from
        || form.date_to !== defaultPeriod.date_to,
    );
    const communeColors = ['#1d4ed8', '#0369a1', '#0f766e', '#64748b', '#ca8a04', '#475569'];
    const statusColors: Record<string, string> = {
        reported: '#94a3b8',
        assigned: '#2563eb',
        in_progress: '#ca8a04',
        restored: '#0f766e',
        closed: '#334155',
    };
    const communePieItems: PieChartItem[] = communeDistribution.map((commune, index) => ({
        key: commune.id,
        label: commune.name,
        value: commune.incidents,
        color: communeColors[index % communeColors.length],
    }));
    const statusPieItems: PieChartItem[] = statusDistribution.map((item) => ({
        key: item.status,
        label: statusLabels[item.status] ?? item.status,
        value: item.total,
        color: statusColors[item.status] ?? '#64748b',
    }));

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-xl font-black uppercase tracking-tight text-slate-900 sm:text-2xl">
                            Dashboard operacional
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Monitoreo consolidado de contingencias eléctricas.
                        </p>
                    </div>
                    <div className="flex items-center gap-2 self-start rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700 sm:self-auto">
                        <span className="h-2 w-2 rounded-full bg-emerald-500" />
                        Base conectada · corte {formatDate(referenceDate, false)}
                    </div>
                </div>
            }
        >
            <Head title="Dashboard operacional" />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-4 flex items-center justify-between gap-4">
                        <div>
                            <h2 className="font-bold text-slate-900">Filtros operacionales</h2>
                            <p className="mt-1 text-xs text-slate-500">Todos los indicadores y registros responden a la misma selección.</p>
                        </div>
                        {hasFilters && (
                            <button type="button" onClick={reset} className="text-xs font-semibold text-blue-700 hover:text-blue-900">
                                Limpiar filtros
                            </button>
                        )}
                    </div>

                    <form onSubmit={submit} className="grid gap-3 sm:grid-cols-2 xl:grid-cols-6">
                        <PeriodFields
                            value={form}
                            onChange={setForm}
                            maxDate={referenceDate.slice(0, 10)}
                            labelClassName="text-xs font-semibold text-slate-600"
                            controlClassName="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                        />
                        <label className="text-xs font-semibold text-slate-600">
                            Comuna
                            <select
                                value={form.commune}
                                onChange={(event) => setForm({ ...form, commune: event.target.value, feeder: '' })}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="">Todas</option>
                                {filterOptions.communes.map((commune) => <option key={commune.id} value={commune.id}>{commune.name}</option>)}
                            </select>
                        </label>
                        <label className="text-xs font-semibold text-slate-600">
                            Alimentador
                            <select value={form.feeder} onChange={(event) => setForm({ ...form, feeder: event.target.value })} className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Todos</option>
                                {availableFeeders.map((feeder) => <option key={feeder.id} value={feeder.id}>{feeder.code}</option>)}
                            </select>
                        </label>
                        <label className="text-xs font-semibold text-slate-600">
                            Criticidad
                            <select value={form.priority} onChange={(event) => setForm({ ...form, priority: event.target.value })} className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Todas</option>
                                {Object.entries(priorityLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </label>
                        <label className="text-xs font-semibold text-slate-600">
                            Estado
                            <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })} className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Todos</option>
                                {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </label>
                        <div className="flex items-end">
                            <button type="submit" className="h-[42px] w-full rounded-lg bg-slate-900 px-4 text-sm font-bold text-white transition hover:bg-blue-700">
                                Aplicar filtros
                            </button>
                        </div>
                        <label className="sm:col-span-2 xl:col-span-6">
                            <span className="sr-only">Buscar contingencia</span>
                            <div className="relative">
                                <svg aria-hidden="true" className="absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8"><path strokeLinecap="round" strokeLinejoin="round" d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" /></svg>
                                <input
                                    value={form.search}
                                    onChange={(event) => setForm({ ...form, search: event.target.value })}
                                    className="block w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-4 text-sm focus:border-blue-500 focus:ring-blue-500"
                                    placeholder="Buscar por código de contingencia, OSF, alimentador o descripción…"
                                />
                            </div>
                        </label>
                    </form>
                </section>

                <section aria-label="Indicadores principales" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    <MetricCard label="Activas" value={formatNumber(metrics.active)} note={`de ${formatNumber(metrics.total)} contingencias filtradas`} accent="border-b-4 border-b-rose-500" icon={<MiniIcon path={<path d="M12 9v4m0 4h.01M10.3 3.8 2.6 17.2A2 2 0 0 0 4.3 20h15.4a2 2 0 0 0 1.7-2.8L13.7 3.8a2 2 0 0 0-3.4 0Z" />} />} />
                    <MetricCard label="Clientes afectados" value={formatNumber(metrics.affected)} note="en contingencias activas" accent="border-b-4 border-b-slate-700" icon={<MiniIcon path={<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2m7-10a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm13 10v-2a4 4 0 0 0-3-3.87" />} />} />
                    <MetricCard label="Clientes repuestos" value={formatNumber(metrics.restored)} note="registros de reposición del período" accent="border-b-4 border-b-emerald-500" icon={<MiniIcon path={<path d="m20 6-11 11-5-5" />} />} />
                    <MetricCard label="Clientes críticos" value={formatNumber(metrics.critical)} note="afectados en eventos activos" accent="border-b-4 border-b-orange-500" icon={<MiniIcon path={<path d="M12 3 3 7v5c0 5 3.8 8.5 9 9 5.2-.5 9-4 9-9V7l-9-4Zm0 5v5m0 4h.01" />} />} />
                    <MetricCard label="Electrodependientes" value={formatNumber(metrics.electrodependent)} note="afectados en eventos activos" accent="border-b-4 border-b-blue-600" icon={<MiniIcon path={<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z" />} />} />
                    <MetricCard label="Tiempo promedio" value={metrics.averageMinutes === null ? '—' : `${formatNumber(metrics.averageMinutes)} min`} note="interrupciones con duración registrada" accent="border-b-4 border-b-slate-400" icon={<MiniIcon path={<path d="M12 6v6l4 2m5-2a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />} />} />
                </section>

                <section>
                    <article className="min-w-0 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="mb-2">
                            <h2 className="font-bold text-slate-900">Evolución reciente de clientes</h2>
                            <p className="mt-1 text-xs text-slate-500">Últimos 12 días con actividad dentro del filtro aplicado.</p>
                        </div>
                        <div className="overflow-x-auto"><TrendChart points={trend} /></div>
                    </article>
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col justify-between gap-2 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center">
                        <div>
                            <h2 className="font-bold text-slate-900">Contingencias eléctricas</h2>
                            <p className="mt-1 text-xs text-slate-500">Últimos 10 registros según los filtros aplicados.</p>
                        </div>
                        <span className="self-start rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600 sm:self-auto">
                            {formatNumber(metrics.total)} resultados
                        </span>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-left">
                            <thead className="bg-slate-50">
                                <tr className="text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                    <th className="px-5 py-3">Código</th>
                                    <th className="px-5 py-3">OSF</th>
                                    <th className="px-5 py-3">Comuna</th>
                                    <th className="px-5 py-3">Alimentador</th>
                                    <th className="px-5 py-3">Criticidad</th>
                                    <th className="px-5 py-3">Estado</th>
                                    <th className="px-5 py-3 text-right">Afectados</th>
                                    <th className="px-5 py-3">Inicio</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100 bg-white text-sm">
                                {contingencies.map((contingency) => (
                                    <tr key={contingency.id} className="transition hover:bg-slate-50">
                                        <td className="whitespace-nowrap px-5 py-4 font-mono text-xs font-bold">
                                            <Link href={route('contingencies.show', contingency.id)} className="text-blue-700 hover:text-blue-900 hover:underline">
                                                {contingency.code}
                                            </Link>
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-500">{contingency.osf_code}</td>
                                        <td className="whitespace-nowrap px-5 py-4 font-medium text-slate-700">{contingency.commune ?? '—'}</td>
                                        <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{contingency.feeder ?? '—'}</td>
                                        <td className="whitespace-nowrap px-5 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${priorityStyles[contingency.priority] ?? 'bg-slate-100 text-slate-600'}`}>{priorityLabels[contingency.priority] ?? contingency.priority}</span></td>
                                        <td className="whitespace-nowrap px-5 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[contingency.status] ?? 'bg-slate-100 text-slate-600'}`}>{statusLabels[contingency.status] ?? contingency.status}</span></td>
                                        <td className="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-800">{formatNumber(contingency.affected_total)}</td>
                                        <td className="whitespace-nowrap px-5 py-4 text-xs text-slate-500">{formatDate(contingency.started_at)}</td>
                                    </tr>
                                ))}
                                {contingencies.length === 0 && (
                                    <tr><td colSpan={8} className="px-5 py-12 text-center text-sm text-slate-500">No existen contingencias para la selección actual.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section aria-label="Distribuciones complementarias" className="grid gap-6 xl:grid-cols-[minmax(0,1.55fr)_minmax(320px,0.85fr)]">
                    <article aria-labelledby="commune-distribution-title" className="rounded-xl border border-slate-200 border-t-4 border-t-blue-700 bg-white p-5 shadow-sm sm:p-7">
                        <div className="mb-6">
                            <h2 id="commune-distribution-title" className="text-lg font-bold text-slate-950">Contingencias por comuna</h2>
                            <p className="mt-1 text-xs text-slate-500">Participación territorial dentro de los filtros aplicados.</p>
                        </div>
                        <DistributionPieChart
                            ariaLabel="Distribución de contingencias por comuna"
                            items={communePieItems}
                            prominent
                        />
                    </article>

                    <article aria-labelledby="status-distribution-title" className="rounded-xl border border-slate-200 bg-slate-50/60 p-5 shadow-sm sm:p-6">
                        <div className="mb-6">
                            <h2 id="status-distribution-title" className="font-bold text-slate-900">Contingencias por estado</h2>
                            <p className="mt-1 text-xs text-slate-500">Situación operativa de los eventos incluidos en los filtros.</p>
                        </div>
                        <DistributionPieChart
                            ariaLabel="Distribución de contingencias por estado"
                            items={statusPieItems}
                        />
                    </article>
                </section>

            </div>
        </AuthenticatedLayout>
    );
}
