import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

type ContingencyDetail = {
    id: number;
    code: string;
    osf_code: string;
    commune: string | null;
    feeder: { code: string | null; name: string | null };
    status: string;
    priority: string;
    cause: string;
    description: string;
    started_at: string | null;
    estimated_restore_at: string | null;
    restored_at: string | null;
    latitude: number;
    longitude: number;
    affected_total: number;
    critical_affected: number;
    electrodependent_affected: number;
    created_at: string | null;
};

type HistoryEvent = {
    id: number;
    status: string;
    note: string;
    event_at: string | null;
    source: string;
    user: string | null;
};

type StatusTransition = {
    value: string;
    label: string;
};

type ShowPageProps = {
    contingency: ContingencyDetail;
    impactSummary: {
        registered: number;
        restored: number;
        pending: number;
        averageMinutes: number | null;
    };
    history: HistoryEvent[];
    availableStatusTransitions: StatusTransition[];
    source: {
        name: string;
        file: string;
        completed_at: string | null;
    } | null;
    contingencyOptions: Array<{ id: number; code: string; status: string }>;
};

const statusLabels: Record<string, string> = {
    reported: 'Reportada',
    assigned: 'Asignada',
    in_progress: 'En atención',
    restored: 'Repuesta',
    closed: 'Cerrada',
};

const statusStyles: Record<string, string> = {
    reported: 'bg-violet-100 text-violet-700',
    assigned: 'bg-sky-100 text-sky-700',
    in_progress: 'bg-amber-100 text-amber-700',
    restored: 'bg-emerald-100 text-emerald-700',
    closed: 'bg-slate-200 text-slate-700',
};

const priorityLabels: Record<string, string> = {
    critical: 'Crítica',
    high: 'Alta',
    medium: 'Media',
    low: 'Baja',
};

const priorityStyles: Record<string, string> = {
    critical: 'bg-rose-100 text-rose-700',
    high: 'bg-amber-100 text-amber-700',
    medium: 'bg-blue-100 text-blue-700',
    low: 'bg-slate-100 text-slate-600',
};

const causeLabels: Record<string, string> = {
    equipment_failure: 'Falla de equipamiento',
    third_party: 'Daño provocado por terceros',
    unknown: 'Causa en evaluación',
    vegetation: 'Interferencia de vegetación',
    vehicle_collision: 'Colisión vehicular',
    weather: 'Condiciones meteorológicas',
};

const sourceLabels: Record<string, string> = {
    import: 'Importación',
    system: 'Sistema',
    manual: 'Registro manual',
    synthetic: 'Generador sintético',
};

const numberFormatter = new Intl.NumberFormat('es-CL');

function formatDate(value: string | null) {
    if (!value) return 'Sin registro';

    return new Intl.DateTimeFormat('es-CL', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function SummaryCard({ label, value, note, accent }: { label: string; value: string; note: string; accent: string }) {
    return (
        <article className={`rounded-xl border border-slate-200 bg-white p-5 shadow-sm ${accent}`}>
            <p className="text-[10px] font-bold uppercase tracking-[0.1em] text-slate-500">{label}</p>
            <p className="mt-2 text-2xl font-black text-slate-900">{value}</p>
            <p className="mt-2 text-xs text-slate-500">{note}</p>
        </article>
    );
}

function DataItem({ label, value, mono = false }: { label: string; value: string; mono?: boolean }) {
    return (
        <div>
            <dt className="text-xs text-slate-500">{label}</dt>
            <dd className={`mt-1 font-semibold text-slate-900 ${mono ? 'font-mono text-sm' : ''}`}>{value}</dd>
        </div>
    );
}

function StatusUpdateForm({
    contingency,
    transitions,
    canUpdate,
}: {
    contingency: ContingencyDetail;
    transitions: StatusTransition[];
    canUpdate: boolean;
}) {
    const form = useForm({
        current_status: contingency.status,
        status: transitions[0]?.value ?? '',
        note: '',
    });

    if (!canUpdate) return null;

    if (transitions.length === 0) {
        return (
            <div className="mt-5 rounded-lg border border-emerald-200 bg-emerald-50 p-4 text-sm font-medium text-emerald-800">
                La contingencia completó su ciclo de seguimiento.
            </div>
        );
    }

    const submit = (event: React.FormEvent) => {
        event.preventDefault();
        form.patch(route('contingencies.status.update', contingency.id), {
            preserveScroll: true,
            onSuccess: () => form.reset('note'),
        });
    };

    return (
        <form onSubmit={submit} className="mt-5 rounded-xl border border-blue-100 bg-blue-50/60 p-4">
            <h3 className="text-sm font-bold text-slate-900">Registrar cambio de estado</h3>
            <p className="mt-1 text-xs text-slate-500">La actualización quedará asociada a su usuario, fecha y hora.</p>

            <label className="mt-4 block text-xs font-semibold text-slate-700">
                Nuevo estado
                <select
                    value={form.data.status}
                    onChange={(event) => form.setData('status', event.target.value)}
                    className="mt-1 block w-full rounded-lg border-slate-300 bg-white text-sm focus:border-blue-500 focus:ring-blue-500"
                >
                    {transitions.map((transition) => (
                        <option key={transition.value} value={transition.value}>{transition.label}</option>
                    ))}
                </select>
            </label>
            {form.errors.status && <p className="mt-1 text-xs font-medium text-rose-600">{form.errors.status}</p>}

            <label className="mt-4 block text-xs font-semibold text-slate-700">
                Antecedente del cambio
                <textarea
                    value={form.data.note}
                    onChange={(event) => form.setData('note', event.target.value)}
                    rows={3}
                    maxLength={300}
                    placeholder="Describa brevemente el avance o antecedente que respalda la actualización."
                    className="mt-1 block w-full resize-y rounded-lg border-slate-300 bg-white text-sm focus:border-blue-500 focus:ring-blue-500"
                />
            </label>
            {form.errors.note && <p className="mt-1 text-xs font-medium text-rose-600">{form.errors.note}</p>}
            {form.errors.current_status && <p className="mt-2 text-xs font-medium text-rose-600">{form.errors.current_status}</p>}

            <button
                type="submit"
                disabled={form.processing || form.data.note.trim().length < 5}
                className="mt-4 w-full rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {form.processing ? 'Registrando...' : 'Registrar actualización'}
            </button>
        </form>
    );
}

export default function Show({ contingency, impactSummary, history, source, contingencyOptions, availableStatusTransitions }: ShowPageProps) {
    const { auth, flash } = usePage<PageProps>().props;
    const restorationPercentage = impactSummary.registered > 0
        ? Math.round((impactSummary.restored / impactSummary.registered) * 100)
        : 0;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col justify-between gap-4 xl:flex-row xl:items-center">
                    <div>
                        <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-blue-600">Expediente operacional sintético</p>
                        <h1 className="mt-1 font-mono text-xl font-black text-slate-900 sm:text-2xl">{contingency.code}</h1>
                        <p className="mt-1 text-sm text-slate-500">Registro consolidado del evento y su trazabilidad histórica.</p>
                    </div>
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                        <label className="text-xs font-semibold text-slate-600">
                            Cambiar expediente
                            <select
                                value={contingency.id}
                                onChange={(event) => router.visit(route('contingencies.show', event.target.value))}
                                className="mt-1 block w-full min-w-64 rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                {contingencyOptions.map((option) => (
                                    <option key={option.id} value={option.id}>{option.code} · {statusLabels[option.status] ?? option.status}</option>
                                ))}
                            </select>
                        </label>
                        <div className="flex gap-2">
                            <Link href={route('contingencies.map')} className="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                                Volver al mapa
                            </Link>
                            <Link href={route('dashboard')} className="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-blue-700">
                                Panel general
                            </Link>
                        </div>
                    </div>
                </div>
            }
        >
            <Head title={`Detalle ${contingency.code}`} />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                {flash.success && (
                    <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        {flash.success}
                    </div>
                )}
                <section aria-label="Resumen de la contingencia" className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Estado actual"
                        value={statusLabels[contingency.status] ?? contingency.status}
                        note={`Prioridad ${priorityLabels[contingency.priority]?.toLowerCase() ?? contingency.priority}`}
                        accent="border-b-4 border-b-amber-500"
                    />
                    <SummaryCard
                        label="Clientes afectados"
                        value={numberFormatter.format(contingency.affected_total)}
                        note={`${numberFormatter.format(impactSummary.restored)} reposiciones registradas`}
                        accent="border-b-4 border-b-slate-700"
                    />
                    <SummaryCard
                        label="Clientes críticos"
                        value={numberFormatter.format(contingency.critical_affected)}
                        note="Conteo agregado de instalaciones prioritarias"
                        accent="border-b-4 border-b-orange-500"
                    />
                    <SummaryCard
                        label="Electrodependientes"
                        value={numberFormatter.format(contingency.electrodependent_affected)}
                        note="Información anonimizada y agregada"
                        accent="border-b-4 border-b-blue-600"
                    />
                </section>

                <section className="grid gap-6 2xl:grid-cols-[minmax(0,1.6fr)_minmax(360px,1fr)]">
                    <div className="space-y-6">
                        <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                                <div>
                                    <h2 className="font-bold text-slate-900">Resumen de la contingencia</h2>
                                    <p className="mt-1 text-xs text-slate-500">Identificación, localización y tiempos operacionales.</p>
                                </div>
                                <div className="flex gap-2">
                                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${priorityStyles[contingency.priority]}`}>{priorityLabels[contingency.priority]}</span>
                                    <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[contingency.status]}`}>{statusLabels[contingency.status]}</span>
                                </div>
                            </div>

                            <dl className="mt-5 grid gap-x-8 gap-y-5 sm:grid-cols-2 xl:grid-cols-3">
                                <DataItem label="Código OSF" value={contingency.osf_code} mono />
                                <DataItem label="Comuna" value={contingency.commune ?? 'Sin comuna'} />
                                <DataItem label="Alimentador" value={contingency.feeder.code ?? 'Sin alimentador'} mono />
                                <DataItem label="Nombre del alimentador" value={contingency.feeder.name ?? 'Sin registro'} />
                                <DataItem label="Inicio informado" value={formatDate(contingency.started_at)} />
                                <DataItem label="Reposición estimada" value={formatDate(contingency.estimated_restore_at)} />
                                <DataItem label="Reposición efectiva" value={formatDate(contingency.restored_at)} />
                                <DataItem label="Causa" value={causeLabels[contingency.cause] ?? contingency.cause} />
                                <DataItem label="Coordenadas sintéticas" value={`${contingency.latitude.toFixed(5)}, ${contingency.longitude.toFixed(5)}`} mono />
                            </dl>

                            <div className="mt-6 rounded-lg bg-slate-50 p-4">
                                <p className="text-xs font-semibold text-slate-500">Descripción del evento</p>
                                <p className="mt-2 text-sm leading-relaxed text-slate-700">{contingency.description}</p>
                            </div>
                        </article>

                        <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <div className="border-b border-slate-100 pb-4">
                                <h2 className="font-bold text-slate-900">Impacto y reposición</h2>
                                <p className="mt-1 text-xs text-slate-500">Resumen agregado de los puntos de suministro relacionados.</p>
                            </div>

                            <div className="mt-5 grid gap-5 sm:grid-cols-4">
                                <DataItem label="Impactos registrados" value={numberFormatter.format(impactSummary.registered)} />
                                <DataItem label="Repuestos" value={numberFormatter.format(impactSummary.restored)} />
                                <DataItem label="Pendientes" value={numberFormatter.format(impactSummary.pending)} />
                                <DataItem label="Duración promedio" value={impactSummary.averageMinutes === null ? 'Sin registro' : `${numberFormatter.format(impactSummary.averageMinutes)} min`} />
                            </div>

                            <div className="mt-6">
                                <div className="mb-2 flex justify-between text-xs font-semibold text-slate-600">
                                    <span>Avance de reposición registrado</span>
                                    <span>{restorationPercentage}%</span>
                                </div>
                                <div className="h-3 overflow-hidden rounded-full bg-slate-100">
                                    <div className="h-full rounded-full bg-emerald-500" style={{ width: `${restorationPercentage}%` }} />
                                </div>
                            </div>
                        </article>

                        <article className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                            <h2 className="font-bold text-slate-900">Trazabilidad de origen</h2>
                            <p className="mt-1 text-xs text-slate-500">Procedencia técnica del registro incorporado al sistema.</p>
                            {source ? (
                                <dl className="mt-5 grid gap-5 sm:grid-cols-3">
                                    <DataItem label="Fuente" value={source.name} />
                                    <DataItem label="Archivo sintético" value={source.file} mono />
                                    <DataItem label="Importación completada" value={formatDate(source.completed_at)} />
                                </dl>
                            ) : (
                                <p className="mt-4 text-sm text-slate-500">El evento no tiene un lote de importación asociado.</p>
                            )}
                        </article>
                    </div>

                    <aside className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="border-b border-slate-100 pb-4">
                            <h2 className="font-bold text-slate-900">Bitácora histórica</h2>
                            <p className="mt-1 text-xs text-slate-500">Cambios de estado ordenados desde el más reciente.</p>
                        </div>

                        <StatusUpdateForm
                            key={`${contingency.id}-${contingency.status}`}
                            contingency={contingency}
                            transitions={availableStatusTransitions}
                            canUpdate={auth.permissions.updateContingencies}
                        />

                        {history.length > 0 ? (
                            <ol className="mt-5 space-y-0">
                                {history.map((event, index) => (
                                    <li key={event.id} className="relative pl-7">
                                        {index < history.length - 1 && <span className="absolute left-[7px] top-4 h-full w-px bg-slate-200" />}
                                        <span className="absolute left-0 top-1.5 h-[15px] w-[15px] rounded-full border-4 border-white bg-blue-600 shadow-sm ring-1 ring-blue-200" />
                                        <div className="pb-6">
                                            <div className="flex flex-wrap items-start justify-between gap-2">
                                                <span className={`rounded-full px-2 py-1 text-xs font-semibold ${statusStyles[event.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                                    {statusLabels[event.status] ?? event.status}
                                                </span>
                                                <time className="text-xs text-slate-500">{formatDate(event.event_at)}</time>
                                            </div>
                                            <p className="mt-2 text-sm leading-relaxed text-slate-700">{event.note}</p>
                                            <p className="mt-2 text-[11px] text-slate-400">
                                                {event.user ? `Responsable: ${event.user}` : 'Actualización automática'} · {sourceLabels[event.source] ?? event.source}
                                            </p>
                                        </div>
                                    </li>
                                ))}
                            </ol>
                        ) : (
                            <div className="mt-5 rounded-lg bg-slate-50 p-4 text-sm text-slate-500">Sin eventos históricos registrados.</div>
                        )}

                    </aside>
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
