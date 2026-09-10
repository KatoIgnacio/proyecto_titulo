import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, ReactNode, useMemo, useState } from 'react';

type Filters = {
    range: string;
    commune: number | null;
    feeder: number | null;
    priority: string | null;
    status: string | null;
    search: string;
};

type FilterForm = {
    range: string;
    commune: string;
    feeder: string;
    priority: string;
    status: string;
    search: string;
};

type ReportType = 'executive' | 'development' | 'complete';

type Option = { id: number; name: string };
type FeederOption = Option & { commune_id: number; code: string };

type Summary = {
    total: number;
    active: number;
    affected: number;
    critical: number;
    electrodependent: number;
    averageMinutes: number | null;
};

type ReportRow = {
    id: number;
    code: string;
    osf_code: string;
    commune: string | null;
    feeder: string | null;
    status: string;
    priority: string;
    affected_total: number;
    started_at: string | null;
};

type Results = {
    data: ReportRow[];
    total: number;
    from: number | null;
    to: number | null;
    currentPage: number;
    lastPage: number;
    previousPageUrl: string | null;
    nextPageUrl: string | null;
};

type ReportProps = {
    filters: Filters;
    referenceDate: string;
    filterOptions: {
        communes: Option[];
        feeders: FeederOption[];
    };
    summary: Summary;
    results: Results;
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

const reportTypeOptions: Array<{
    value: ReportType;
    title: string;
    description: string;
    contents: string;
    recommended?: boolean;
}> = [
    {
        value: 'executive',
        title: 'Resumen ejecutivo',
        description: 'Para revisar el comportamiento general y apoyar decisiones sin recorrer cada expediente.',
        contents: 'Indicadores, gráficos, evolución temporal y comunas más afectadas.',
        recommended: true,
    },
    {
        value: 'development',
        title: 'Desarrollo de contingencias',
        description: 'Para consultar la evolución y trazabilidad de las contingencias incluidas por los filtros.',
        contents: 'Fichas operacionales, causas, impacto, duración y cambios de estado.',
    },
    {
        value: 'complete',
        title: 'Informe integral',
        description: 'Para auditoría, respaldo académico o revisión exhaustiva de la selección.',
        contents: 'Resumen ejecutivo, gráficos y desarrollo completo de cada contingencia.',
    },
];

const numberFormatter = new Intl.NumberFormat('es-CL');

function formatDate(value: string | null) {
    if (!value) return '—';

    return new Intl.DateTimeFormat('es-CL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function Icon({ path }: { path: ReactNode }) {
    return (
        <svg aria-hidden="true" className="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            {path}
        </svg>
    );
}

function Metric({ label, value, accent }: { label: string; value: string; accent: string }) {
    return (
        <article className={`rounded-xl border border-slate-200 bg-white p-4 shadow-sm ${accent}`}>
            <p className="text-[10px] font-bold uppercase tracking-[0.08em] text-slate-500">{label}</p>
            <p className="mt-2 text-2xl font-black text-slate-900">{value}</p>
        </article>
    );
}

export default function Index({ filters, referenceDate, filterOptions, summary, results }: ReportProps) {
    const [reportType, setReportType] = useState<ReportType>('executive');
    const [form, setForm] = useState<FilterForm>({
        range: filters.range,
        commune: filters.commune?.toString() ?? '',
        feeder: filters.feeder?.toString() ?? '',
        priority: filters.priority ?? '',
        status: filters.status ?? '',
        search: filters.search,
    });

    const feeders = useMemo(() => {
        if (!form.commune) return filterOptions.feeders;
        return filterOptions.feeders.filter((feeder) => feeder.commune_id === Number(form.commune));
    }, [filterOptions.feeders, form.commune]);

    const currentParams = useMemo(() => {
        const params = new URLSearchParams();
        params.set('range', filters.range);
        if (filters.commune) params.set('commune', filters.commune.toString());
        if (filters.feeder) params.set('feeder', filters.feeder.toString());
        if (filters.priority) params.set('priority', filters.priority);
        if (filters.status) params.set('status', filters.status);
        if (filters.search) params.set('search', filters.search);
        return params.toString();
    }, [filters]);

    const exportUrl = (routeName: string, selectedReportType?: ReportType) => {
        const params = new URLSearchParams(currentParams);
        if (selectedReportType) params.set('report_type', selectedReportType);

        const query = params.toString();
        return query ? `${route(routeName)}?${query}` : route(routeName);
    };

    const detailPages = Math.max(1, Math.ceil(results.total / 4));
    const estimatedPages = reportType === 'executive'
        ? 3
        : reportType === 'development'
            ? 1 + detailPages
            : 3 + detailPages;
    const selectedReport = reportTypeOptions.find((option) => option.value === reportType) ?? reportTypeOptions[0];

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('reports.index'), {
            range: form.range,
            commune: form.commune || undefined,
            feeder: form.feeder || undefined,
            priority: form.priority || undefined,
            status: form.status || undefined,
            search: form.search.trim() || undefined,
        }, { preserveState: true, preserveScroll: true, replace: true });
    };

    const reset = () => {
        setForm({ range: '12m', commune: '', feeder: '', priority: '', status: '', search: '' });
        router.get(route('reports.index'), {}, { replace: true });
    };

    return (
        <AuthenticatedLayout
            header={(
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-black tracking-tight text-slate-950">INFORMES OPERACIONALES</h1>
                        <p className="mt-1 text-sm text-slate-500">Análisis integral, evolución temporal y trazabilidad de las contingencias seleccionadas.</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <a href={exportUrl('reports.csv')} className="inline-flex items-center gap-2 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-bold text-emerald-700 transition hover:bg-emerald-100">
                            <Icon path={<path d="M4 4h16v16H4V4Zm4 4h8M8 12h8m-8 4h5" />} />
                            Exportar datos CSV
                        </a>
                    </div>
                </div>
            )}
        >
            <Head title="Informes" />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                            <label className="text-sm font-medium text-slate-700">
                                Período
                                <select value={form.range} onChange={(event) => setForm((current) => ({ ...current, range: event.target.value }))} className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="24h">Últimas 24 horas</option>
                                    <option value="7d">Últimos 7 días</option>
                                    <option value="30d">Últimos 30 días</option>
                                    <option value="12m">Últimos 12 meses</option>
                                    <option value="all">Todo el historial</option>
                                </select>
                            </label>

                            <label className="text-sm font-medium text-slate-700">
                                Comuna
                                <select
                                    value={form.commune}
                                    onChange={(event) => setForm((current) => ({ ...current, commune: event.target.value, feeder: '' }))}
                                    className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="">Todas</option>
                                    {filterOptions.communes.map((commune) => <option key={commune.id} value={commune.id}>{commune.name}</option>)}
                                </select>
                            </label>

                            <label className="text-sm font-medium text-slate-700">
                                Alimentador
                                <select value={form.feeder} onChange={(event) => setForm((current) => ({ ...current, feeder: event.target.value }))} className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todos</option>
                                    {feeders.map((feeder) => <option key={feeder.id} value={feeder.id}>{feeder.code}</option>)}
                                </select>
                            </label>

                            <label className="text-sm font-medium text-slate-700">
                                Estado
                                <select value={form.status} onChange={(event) => setForm((current) => ({ ...current, status: event.target.value }))} className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todos</option>
                                    {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </label>

                            <label className="text-sm font-medium text-slate-700">
                                Criticidad
                                <select value={form.priority} onChange={(event) => setForm((current) => ({ ...current, priority: event.target.value }))} className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500">
                                    <option value="">Todas</option>
                                    {Object.entries(priorityLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </label>

                            <div className="flex items-end gap-2">
                                <button type="submit" className="h-[42px] flex-1 rounded-lg bg-blue-600 px-4 text-sm font-bold text-white transition hover:bg-blue-700">Aplicar</button>
                                <button type="button" onClick={reset} className="h-[42px] rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-600 hover:border-blue-400 hover:text-blue-700">Limpiar</button>
                            </div>
                        </div>

                        <label className="block text-sm font-medium text-slate-700">
                            Búsqueda
                            <div className="relative mt-1.5">
                                <span className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-slate-400">
                                    <Icon path={<path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />} />
                                </span>
                                <input value={form.search} onChange={(event) => setForm((current) => ({ ...current, search: event.target.value }))} maxLength={80} placeholder="Código, OSF, comuna, alimentador, causa o descripción" className="block w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500" />
                            </div>
                        </label>
                    </form>
                </section>

                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 className="text-lg font-black text-slate-900">¿Qué informe necesita generar?</h2>
                            <p className="mt-1 text-sm text-slate-500">La selección usa los filtros aplicados y no cambia los datos del sistema.</p>
                        </div>
                        <span className="rounded-full bg-blue-50 px-3 py-1 text-xs font-bold text-blue-700">
                            {numberFormatter.format(results.total)} contingencias seleccionadas
                        </span>
                    </div>

                    <div className="mt-5 grid gap-4 lg:grid-cols-3">
                        {reportTypeOptions.map((option) => {
                            const selected = option.value === reportType;
                            const optionPages = option.value === 'executive'
                                ? 3
                                : option.value === 'development'
                                    ? 1 + detailPages
                                    : 3 + detailPages;

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    aria-pressed={selected}
                                    onClick={() => setReportType(option.value)}
                                    className={`relative rounded-xl border-2 p-4 text-left transition ${selected ? 'border-blue-600 bg-blue-50/70 shadow-sm' : 'border-slate-200 bg-white hover:border-blue-300 hover:bg-slate-50'}`}
                                >
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <div className="flex flex-wrap items-center gap-2">
                                                <h3 className="font-black text-slate-900">{option.title}</h3>
                                                {option.recommended && <span className="rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wide text-emerald-700">Recomendado</span>}
                                            </div>
                                            <p className="mt-2 text-sm leading-5 text-slate-600">{option.description}</p>
                                        </div>
                                        <span className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border-2 ${selected ? 'border-blue-600 bg-blue-600' : 'border-slate-300 bg-white'}`}>
                                            {selected && <span className="h-2 w-2 rounded-full bg-white" />}
                                        </span>
                                    </div>
                                    <p className="mt-3 border-t border-slate-200 pt-3 text-xs font-semibold leading-5 text-slate-500">{option.contents}</p>
                                    <p className="mt-2 text-xs font-bold text-blue-700">Aproximadamente {numberFormatter.format(optionPages)} páginas</p>
                                </button>
                            );
                        })}
                    </div>

                    <div className="mt-5 flex flex-wrap items-center justify-between gap-4 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <div>
                            <p className="font-bold text-slate-900">{selectedReport.title}</p>
                            <p className="mt-1 text-sm text-slate-600">
                                El documento tendrá aproximadamente {numberFormatter.format(estimatedPages)} páginas.
                                {reportType !== 'executive' && results.total > 40 && ' Conviene acotar el período o buscar una contingencia específica si no necesita toda la selección.'}
                            </p>
                        </div>
                        <a href={exportUrl('reports.pdf', reportType)} className="inline-flex items-center gap-2 rounded-lg bg-slate-900 px-5 py-3 text-sm font-bold text-white transition hover:bg-blue-700">
                            <Icon path={<path d="M6 2h9l4 4v16H6V2Zm9 0v5h5M9 12h7m-7 4h7" />} />
                            Generar {selectedReport.title.toLowerCase()}
                        </a>
                    </div>
                </section>

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
                    <Metric label="Contingencias" value={numberFormatter.format(summary.total)} accent="border-b-4 border-b-slate-700" />
                    <Metric label="Activas" value={numberFormatter.format(summary.active)} accent="border-b-4 border-b-rose-500" />
                    <Metric label="Afectados" value={numberFormatter.format(summary.affected)} accent="border-b-4 border-b-blue-600" />
                    <Metric label="Críticos" value={numberFormatter.format(summary.critical)} accent="border-b-4 border-b-orange-500" />
                    <Metric label="Electrodependientes" value={numberFormatter.format(summary.electrodependent)} accent="border-b-4 border-b-violet-500" />
                    <Metric label="Duración promedio" value={summary.averageMinutes === null ? '—' : `${numberFormatter.format(summary.averageMinutes)} min`} accent="border-b-4 border-b-emerald-500" />
                </section>

                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h2 className="font-bold text-slate-900">Contingencias incluidas</h2>
                            <p className="mt-0.5 text-xs text-slate-500">Corte: {formatDate(referenceDate)}</p>
                        </div>
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            {results.total === 0 ? 'Sin registros' : `${results.from}–${results.to} de ${numberFormatter.format(results.total)}`}
                        </span>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                <tr>
                                    <th className="px-5 py-3">Código</th>
                                    <th className="px-5 py-3">OSF</th>
                                    <th className="px-5 py-3">Comuna</th>
                                    <th className="px-5 py-3">Alimentador</th>
                                    <th className="px-5 py-3">Estado</th>
                                    <th className="px-5 py-3">Criticidad</th>
                                    <th className="px-5 py-3 text-right">Afectados</th>
                                    <th className="px-5 py-3">Inicio</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {results.data.map((contingency) => (
                                    <tr key={contingency.id} className="transition hover:bg-blue-50/40">
                                        <td className="whitespace-nowrap px-5 py-4 font-mono text-xs font-bold">
                                            <Link href={route('contingencies.show', contingency.id)} className="text-blue-700 hover:text-blue-900">{contingency.code}</Link>
                                        </td>
                                        <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{contingency.osf_code}</td>
                                        <td className="whitespace-nowrap px-5 py-4 font-medium text-slate-700">{contingency.commune ?? '—'}</td>
                                        <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{contingency.feeder ?? '—'}</td>
                                        <td className="whitespace-nowrap px-5 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[contingency.status] ?? 'bg-slate-100 text-slate-600'}`}>{statusLabels[contingency.status] ?? contingency.status}</span></td>
                                        <td className="whitespace-nowrap px-5 py-4"><span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${priorityStyles[contingency.priority] ?? 'bg-slate-100 text-slate-600'}`}>{priorityLabels[contingency.priority] ?? contingency.priority}</span></td>
                                        <td className="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-900">{numberFormatter.format(contingency.affected_total)}</td>
                                        <td className="whitespace-nowrap px-5 py-4 text-xs text-slate-600">{formatDate(contingency.started_at)}</td>
                                    </tr>
                                ))}
                                {results.data.length === 0 && <tr><td colSpan={8} className="px-5 py-14 text-center text-sm text-slate-500">No existen contingencias para los filtros seleccionados.</td></tr>}
                            </tbody>
                        </table>
                    </div>

                    {results.total > 0 && (
                        <div className="flex items-center justify-between gap-4 border-t border-slate-200 px-5 py-4 text-sm">
                            <span className="text-slate-500">Página {results.currentPage} de {results.lastPage}</span>
                            <div className="flex gap-2">
                                {results.previousPageUrl ? <Link href={results.previousPageUrl} preserveScroll className="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:text-blue-700">Anterior</Link> : <span className="rounded-lg border border-slate-200 px-4 py-2 text-slate-300">Anterior</span>}
                                {results.nextPageUrl ? <Link href={results.nextPageUrl} preserveScroll className="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:text-blue-700">Siguiente</Link> : <span className="rounded-lg border border-slate-200 px-4 py-2 text-slate-300">Siguiente</span>}
                            </div>
                        </div>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
