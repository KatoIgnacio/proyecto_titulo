import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, router } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type SearchFilters = {
    category: string;
    query: string;
    status: string | null;
    priority: string | null;
};

type SearchForm = {
    category: string;
    query: string;
    status: string;
    priority: string;
};

type ContingencyResult = {
    id: number;
    code: string;
    osf_code: string;
    commune: string | null;
    feeder: string | null;
    status: string;
    priority: string;
    description: string | null;
    affected_total: number;
    started_at: string | null;
};

type SearchResults = {
    data: ContingencyResult[];
    total: number;
    from: number | null;
    to: number | null;
    currentPage: number;
    lastPage: number;
    previousPageUrl: string | null;
    nextPageUrl: string | null;
};

type SearchProps = {
    filters: SearchFilters;
    hasSearched: boolean;
    results: SearchResults;
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

function SearchIcon({ className = 'h-5 w-5' }: { className?: string }) {
    return (
        <svg aria-hidden="true" className={className} fill="none" viewBox="0 0 24 24" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round">
            <path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />
        </svg>
    );
}

export default function Search({ filters, hasSearched, results }: SearchProps) {
    const [form, setForm] = useState<SearchForm>({
        category: filters.category,
        query: filters.query,
        status: filters.status ?? '',
        priority: filters.priority ?? '',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();

        router.get(
            route('contingencies.search'),
            {
                category: form.category,
                query: form.query.trim() || undefined,
                status: form.status || undefined,
                priority: form.priority || undefined,
            },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    const clear = () => {
        setForm({ category: 'all', query: '', status: '', priority: '' });
        router.get(route('contingencies.search'), {}, { replace: true });
    };

    return (
        <AuthenticatedLayout
            header={(
                <div>
                    <h1 className="text-2xl font-black tracking-tight text-slate-950">BUSCADOR OPERACIONAL</h1>
                    <p className="mt-1 text-sm text-slate-500">Consulta centralizada de contingencias eléctricas.</p>
                </div>
            )}
        >
            <Head title="Buscador operacional" />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <form onSubmit={submit} className="space-y-4">
                        <div className="grid gap-4 xl:grid-cols-[220px_minmax(280px,1fr)_190px_170px_auto] xl:items-end">
                            <label className="block text-sm font-medium text-slate-700">
                                Categoría
                                <select
                                    value={form.category}
                                    onChange={(event) => setForm((current) => ({ ...current, category: event.target.value }))}
                                    className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="all">Todo el registro</option>
                                    <option value="code">Código de contingencia</option>
                                    <option value="osf">Código OSF</option>
                                    <option value="commune">Comuna</option>
                                    <option value="feeder">Alimentador</option>
                                    <option value="description">Descripción o causa</option>
                                </select>
                            </label>

                            <label className="block text-sm font-medium text-slate-700">
                                Criterio de búsqueda
                                <div className="relative mt-1.5">
                                    <SearchIcon className="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-slate-400" />
                                    <input
                                        value={form.query}
                                        onChange={(event) => setForm((current) => ({ ...current, query: event.target.value }))}
                                        placeholder="Código, OSF, comuna, alimentador o descripción"
                                        maxLength={80}
                                        className="block w-full rounded-lg border-slate-300 py-2.5 pl-10 pr-3 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                    />
                                </div>
                            </label>

                            <label className="block text-sm font-medium text-slate-700">
                                Estado
                                <select
                                    value={form.status}
                                    onChange={(event) => setForm((current) => ({ ...current, status: event.target.value }))}
                                    className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="">Todos</option>
                                    {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </label>

                            <label className="block text-sm font-medium text-slate-700">
                                Criticidad
                                <select
                                    value={form.priority}
                                    onChange={(event) => setForm((current) => ({ ...current, priority: event.target.value }))}
                                    className="mt-1.5 block w-full rounded-lg border-slate-300 text-sm shadow-sm focus:border-blue-500 focus:ring-blue-500"
                                >
                                    <option value="">Todas</option>
                                    {Object.entries(priorityLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                                </select>
                            </label>

                            <button type="submit" className="inline-flex h-[42px] items-center justify-center gap-2 rounded-lg bg-slate-900 px-5 text-sm font-bold text-white transition hover:bg-blue-700">
                                <SearchIcon />
                                Buscar
                            </button>
                        </div>

                        {(hasSearched || form.query || form.status || form.priority) && (
                            <div className="flex justify-end">
                                <button type="button" onClick={clear} className="text-sm font-semibold text-slate-500 hover:text-blue-700">
                                    Limpiar búsqueda
                                </button>
                            </div>
                        )}
                    </form>
                </section>

                {!hasSearched ? (
                    <section className="flex min-h-72 flex-col items-center justify-center rounded-xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                        <span className="rounded-full bg-blue-50 p-4 text-blue-600"><SearchIcon className="h-8 w-8" /></span>
                        <h2 className="mt-4 text-lg font-bold text-slate-900">Buscar contingencias</h2>
                        <p className="mt-1 text-sm text-slate-500">Ingresa un criterio o selecciona un filtro.</p>
                    </section>
                ) : (
                    <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                            <div>
                                <h2 className="font-bold text-slate-900">Resultados</h2>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    {results.total === 0 ? 'Sin coincidencias' : `${numberFormatter.format(results.total)} contingencias encontradas`}
                                </p>
                            </div>
                            {results.total > 0 && (
                                <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                    {results.from}–{results.to} de {numberFormatter.format(results.total)}
                                </span>
                            )}
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                                    <tr>
                                        <th className="px-5 py-3">Código</th>
                                        <th className="px-5 py-3">OSF</th>
                                        <th className="px-5 py-3">Comuna</th>
                                        <th className="px-5 py-3">Alimentador</th>
                                        <th className="px-5 py-3">Criticidad</th>
                                        <th className="px-5 py-3">Estado</th>
                                        <th className="px-5 py-3 text-right">Afectados</th>
                                        <th className="px-5 py-3">Inicio</th>
                                        <th className="px-5 py-3 text-right">Acción</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {results.data.map((contingency) => (
                                        <tr key={contingency.id} className="transition hover:bg-blue-50/40">
                                            <td className="whitespace-nowrap px-5 py-4 font-mono text-xs font-bold text-slate-900">{contingency.code}</td>
                                            <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{contingency.osf_code}</td>
                                            <td className="whitespace-nowrap px-5 py-4 font-medium text-slate-700">{contingency.commune ?? '—'}</td>
                                            <td className="whitespace-nowrap px-5 py-4 font-mono text-xs text-slate-600">{contingency.feeder ?? '—'}</td>
                                            <td className="whitespace-nowrap px-5 py-4">
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${priorityStyles[contingency.priority] ?? 'bg-slate-100 text-slate-600'}`}>
                                                    {priorityLabels[contingency.priority] ?? contingency.priority}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4">
                                                <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[contingency.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                                    {statusLabels[contingency.status] ?? contingency.status}
                                                </span>
                                            </td>
                                            <td className="whitespace-nowrap px-5 py-4 text-right font-semibold text-slate-900">{numberFormatter.format(contingency.affected_total)}</td>
                                            <td className="whitespace-nowrap px-5 py-4 text-xs text-slate-600">{formatDate(contingency.started_at)}</td>
                                            <td className="whitespace-nowrap px-5 py-4 text-right">
                                                <Link href={route('contingencies.show', contingency.id)} className="font-semibold text-blue-700 hover:text-blue-900">
                                                    Ver detalle
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                    {results.data.length === 0 && (
                                        <tr>
                                            <td colSpan={9} className="px-5 py-14 text-center text-sm text-slate-500">No se encontraron contingencias para la búsqueda actual.</td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {results.total > 0 && (
                            <div className="flex items-center justify-between gap-4 border-t border-slate-200 px-5 py-4 text-sm">
                                <span className="text-slate-500">Página {results.currentPage} de {results.lastPage}</span>
                                <div className="flex gap-2">
                                    {results.previousPageUrl ? (
                                        <Link href={results.previousPageUrl} preserveScroll className="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:border-blue-400 hover:text-blue-700">Anterior</Link>
                                    ) : (
                                        <span className="rounded-lg border border-slate-200 px-4 py-2 text-slate-300">Anterior</span>
                                    )}
                                    {results.nextPageUrl ? (
                                        <Link href={results.nextPageUrl} preserveScroll className="rounded-lg border border-slate-300 px-4 py-2 font-semibold text-slate-700 hover:border-blue-400 hover:text-blue-700">Siguiente</Link>
                                    ) : (
                                        <span className="rounded-lg border border-slate-200 px-4 py-2 text-slate-300">Siguiente</span>
                                    )}
                                </div>
                            </div>
                        )}
                    </section>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
