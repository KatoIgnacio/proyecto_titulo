import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { PageProps } from '@/types';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import axios from 'axios';
import { FormEvent, useRef, useState } from 'react';

type SourceOption = { value: string; label: string };

type ImportError = {
    source_row_number?: number;
    field_name?: string | null;
    error_code?: string;
    synthetic_reference?: string;
    row?: number;
    field?: string | null;
    code?: string;
    reference?: string;
    message: string;
};

type Preview = {
    checksum: string;
    encoding: string;
    delimiter: string;
    total_rows: number;
    accepted_rows: number;
    rejected_rows: number;
    can_import: boolean;
    accepted_references: string[];
    errors: ImportError[];
    errors_truncated: number;
};

type ImportBatch = {
    id: number;
    source: string;
    file: string;
    status: string;
    total_rows: number;
    accepted_rows: number;
    rejected_rows: number;
    errors_count: number;
    started_at: string | null;
    completed_at: string | null;
    user: string | null;
    reference: string;
};

type SelectedBatch = ImportBatch & {
    errors: ImportError[];
    errors_truncated: number;
};

type ImportPageProps = {
    sources: SourceOption[];
    recentBatches: ImportBatch[];
    selectedBatch: SelectedBatch | null;
};

const statusLabels: Record<string, string> = {
    completed: 'Completada',
    completed_with_warnings: 'Con observaciones',
    rejected: 'Rechazada',
};

const statusStyles: Record<string, string> = {
    completed: 'bg-emerald-100 text-emerald-700',
    completed_with_warnings: 'bg-amber-100 text-amber-700',
    rejected: 'bg-rose-100 text-rose-700',
};

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

function errorValue(error: ImportError, preview: boolean) {
    return {
        row: preview ? error.source_row_number : error.row,
        field: preview ? error.field_name : error.field,
        code: preview ? error.error_code : error.code,
        reference: preview ? error.synthetic_reference : error.reference,
    };
}

function ErrorTable({ errors, preview = false }: { errors: ImportError[]; preview?: boolean }) {
    if (errors.length === 0) return null;

    return (
        <div className="overflow-x-auto rounded-xl border border-slate-200">
            <table className="min-w-full divide-y divide-slate-200 text-left text-xs">
                <thead className="bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                    <tr>
                        <th className="px-3 py-2">Fila</th>
                        <th className="px-3 py-2">Referencia</th>
                        <th className="px-3 py-2">Campo</th>
                        <th className="px-3 py-2">Código</th>
                        <th className="px-3 py-2">Detalle</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-100 bg-white text-slate-700">
                    {errors.map((error, index) => {
                        const values = errorValue(error, preview);
                        return (
                            <tr key={`${values.row}-${values.code}-${index}`}>
                                <td className="px-3 py-2 font-mono">{values.row ?? '—'}</td>
                                <td className="px-3 py-2 font-mono">{values.reference ?? '—'}</td>
                                <td className="px-3 py-2">{values.field ?? '—'}</td>
                                <td className="px-3 py-2 font-mono">{values.code ?? '—'}</td>
                                <td className="px-3 py-2">{error.message}</td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}

export default function Index({ sources, recentBatches, selectedBatch }: ImportPageProps) {
    const { flash } = usePage<PageProps>().props;
    const fileInput = useRef<HTMLInputElement>(null);
    const [preview, setPreview] = useState<Preview | null>(null);
    const [previewing, setPreviewing] = useState(false);
    const [previewError, setPreviewError] = useState<string | null>(null);
    const form = useForm<{
        source: string;
        file: File | null;
        checksum: string;
    }>({
        source: sources[0]?.value ?? '',
        file: null,
        checksum: '',
    });

    const resetPreview = () => {
        setPreview(null);
        setPreviewError(null);
        form.setData('checksum', '');
    };

    const validateFile = async () => {
        if (!form.data.file) {
            setPreviewError('Seleccione un archivo CSV o TXT antes de validarlo.');
            return;
        }

        setPreviewing(true);
        setPreviewError(null);
        form.clearErrors();
        const payload = new FormData();
        payload.append('source', form.data.source);
        payload.append('file', form.data.file);

        try {
            const response = await axios.post<Preview>(route('imports.preview'), payload, {
                headers: { Accept: 'application/json' },
            });
            setPreview(response.data);
            form.setData('checksum', response.data.checksum);
        } catch (error) {
            if (axios.isAxiosError(error)) {
                const validationErrors = error.response?.data?.errors as Record<string, string[]> | undefined;
                setPreviewError(validationErrors?.file?.[0] ?? error.response?.data?.message ?? 'No fue posible validar el archivo.');
            } else {
                setPreviewError('No fue posible validar el archivo.');
            }
        } finally {
            setPreviewing(false);
        }
    };

    const submit = (event: FormEvent) => {
        event.preventDefault();
        if (!preview?.can_import || form.data.checksum !== preview.checksum) return;

        form.post(route('imports.store'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setPreview(null);
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-blue-600">Incorporación de antecedentes</p>
                    <h1 className="mt-1 text-2xl font-black text-slate-900">Importación controlada</h1>
                    <p className="mt-1 text-sm text-slate-500">Valide el archivo antes de incorporar contingencias al sistema.</p>
                </div>
            }
        >
            <Head title="Importación controlada" />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                {flash.success && (
                    <div role="status" className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        {flash.success}
                    </div>
                )}

                <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                        <div>
                            <h2 className="font-bold text-slate-900">1. Seleccionar y validar archivo</h2>
                            <p className="mt-1 text-xs text-slate-500">Hasta 1.000 filas y 2 MB. Se admiten UTF-8 y Windows-1252.</p>
                        </div>
                        <a href={route('imports.template')} className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-blue-700 hover:bg-blue-50">
                            Descargar plantilla CSV
                        </a>
                    </div>

                    <div className="mt-5 grid gap-4 lg:grid-cols-[minmax(220px,0.7fr)_minmax(320px,1.5fr)_auto] lg:items-end">
                        <label className="text-xs font-semibold text-slate-700">
                            Fuente
                            <select
                                value={form.data.source}
                                onChange={(event) => {
                                    form.setData('source', event.target.value);
                                    resetPreview();
                                }}
                                className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                {sources.map((source) => <option key={source.value} value={source.value}>{source.label}</option>)}
                            </select>
                        </label>
                        <label className="text-xs font-semibold text-slate-700">
                            Archivo
                            <input
                                ref={fileInput}
                                type="file"
                                accept=".csv,.txt,text/csv,text/plain"
                                onChange={(event) => {
                                    form.setData('file', event.target.files?.[0] ?? null);
                                    resetPreview();
                                }}
                                className="mt-1 block w-full rounded-lg border border-slate-300 bg-white text-sm file:mr-3 file:border-0 file:bg-slate-100 file:px-3 file:py-2 file:font-semibold file:text-slate-700"
                            />
                        </label>
                        <button
                            type="button"
                            onClick={validateFile}
                            disabled={previewing || !form.data.file}
                            className="rounded-lg bg-slate-900 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {previewing ? 'Validando...' : 'Validar archivo'}
                        </button>
                    </div>

                    {(previewError || form.errors.file || form.errors.checksum) && (
                        <p className="mt-3 text-sm font-semibold text-rose-600">{previewError ?? form.errors.file ?? form.errors.checksum}</p>
                    )}
                </section>

                {preview && (
                    <section className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="border-b border-slate-100 pb-4">
                            <h2 className="font-bold text-slate-900">2. Resultado de la validación</h2>
                            <p className="mt-1 text-xs text-slate-500">Codificación {preview.encoding} · separador {preview.delimiter}.</p>
                        </div>
                        <div className="mt-5 grid gap-3 sm:grid-cols-3">
                            <div className="rounded-lg bg-slate-50 p-4"><p className="text-xs text-slate-500">Filas revisadas</p><p className="mt-1 text-2xl font-black">{preview.total_rows}</p></div>
                            <div className="rounded-lg bg-emerald-50 p-4"><p className="text-xs text-emerald-700">Aceptadas</p><p className="mt-1 text-2xl font-black text-emerald-800">{preview.accepted_rows}</p></div>
                            <div className="rounded-lg bg-rose-50 p-4"><p className="text-xs text-rose-700">Rechazadas</p><p className="mt-1 text-2xl font-black text-rose-800">{preview.rejected_rows}</p></div>
                        </div>

                        {preview.accepted_references.length > 0 && (
                            <div className="mt-4 rounded-lg bg-slate-50 p-4 text-xs text-slate-600">
                                <span className="font-semibold">Referencias aceptadas:</span> {preview.accepted_references.join(', ')}
                                {preview.accepted_rows > preview.accepted_references.length && '…'}
                            </div>
                        )}

                        {preview.errors.length > 0 && (
                            <div className="mt-5 space-y-2">
                                <h3 className="text-sm font-bold text-slate-900">Observaciones detectadas</h3>
                                <ErrorTable errors={preview.errors} preview />
                                {preview.errors_truncated > 0 && <p className="text-xs text-slate-500">Hay {preview.errors_truncated} observaciones adicionales.</p>}
                            </div>
                        )}

                        <form onSubmit={submit} className="mt-5 flex flex-wrap items-center justify-between gap-3 border-t border-slate-100 pt-5">
                            <p className="max-w-2xl text-xs leading-relaxed text-slate-500">
                                La confirmación registrará en una sola transacción el lote, las filas válidas, los rechazos y la trazabilidad de origen.
                            </p>
                            <button
                                type="submit"
                                disabled={!preview.can_import || form.processing}
                                className="rounded-lg bg-blue-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                                {form.processing ? 'Importando...' : preview.accepted_rows > 0 ? 'Confirmar importación' : 'Registrar resultado rechazado'}
                            </button>
                        </form>
                    </section>
                )}

                {selectedBatch && (
                    <section className="rounded-xl border border-blue-200 bg-white p-5 shadow-sm sm:p-6">
                        <div className="flex flex-wrap items-start justify-between gap-3 border-b border-slate-100 pb-4">
                            <div>
                                <p className="font-mono text-xs font-semibold text-blue-700">{selectedBatch.reference}</p>
                                <h2 className="mt-1 font-bold text-slate-900">Resultado de importación</h2>
                                <p className="mt-1 text-xs text-slate-500">{selectedBatch.file} · {formatDate(selectedBatch.completed_at)}</p>
                            </div>
                            <span className={`rounded-full px-3 py-1 text-xs font-semibold ${statusStyles[selectedBatch.status] ?? 'bg-slate-100 text-slate-700'}`}>
                                {statusLabels[selectedBatch.status] ?? selectedBatch.status}
                            </span>
                        </div>
                        <div className="my-5 grid gap-3 sm:grid-cols-3">
                            <div><p className="text-xs text-slate-500">Total</p><p className="text-xl font-black">{selectedBatch.total_rows}</p></div>
                            <div><p className="text-xs text-emerald-700">Aceptadas</p><p className="text-xl font-black text-emerald-800">{selectedBatch.accepted_rows}</p></div>
                            <div><p className="text-xs text-rose-700">Rechazadas</p><p className="text-xl font-black text-rose-800">{selectedBatch.rejected_rows}</p></div>
                        </div>
                        <ErrorTable errors={selectedBatch.errors} />
                        {selectedBatch.errors.length === 0 && <p className="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800">El lote no registró observaciones.</p>}
                        {selectedBatch.errors_truncated > 0 && <p className="mt-2 text-xs text-slate-500">Hay {selectedBatch.errors_truncated} observaciones adicionales.</p>}
                    </section>
                )}

                <section className="rounded-xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 p-5 sm:p-6">
                        <h2 className="font-bold text-slate-900">Importaciones recientes</h2>
                        <p className="mt-1 text-xs text-slate-500">Últimos diez lotes procesados.</p>
                    </div>
                    {recentBatches.length > 0 ? (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-left text-sm">
                                <thead className="bg-slate-50 text-[10px] font-bold uppercase tracking-wide text-slate-500">
                                    <tr><th className="px-5 py-3">Lote</th><th className="px-5 py-3">Archivo</th><th className="px-5 py-3">Resultado</th><th className="px-5 py-3">Filas</th><th className="px-5 py-3">Responsable</th><th className="px-5 py-3">Fecha</th></tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {recentBatches.map((batch) => (
                                        <tr key={batch.id}>
                                            <td className="px-5 py-3"><Link href={route('imports.index', { batch: batch.id })} className="font-mono font-semibold text-blue-700 hover:underline">{batch.reference}</Link></td>
                                            <td className="px-5 py-3"><p className="font-medium text-slate-800">{batch.file}</p><p className="text-xs text-slate-500">{batch.source}</p></td>
                                            <td className="px-5 py-3"><span className={`rounded-full px-2 py-1 text-xs font-semibold ${statusStyles[batch.status] ?? 'bg-slate-100 text-slate-700'}`}>{statusLabels[batch.status] ?? batch.status}</span></td>
                                            <td className="px-5 py-3 text-xs"><span className="text-emerald-700">{batch.accepted_rows} aceptadas</span><br /><span className="text-rose-700">{batch.rejected_rows} rechazadas</span></td>
                                            <td className="px-5 py-3 text-xs text-slate-600">{batch.user ?? 'Sin registro'}</td>
                                            <td className="px-5 py-3 text-xs text-slate-600">{formatDate(batch.completed_at)}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="p-6 text-sm text-slate-500">Aún no se han ejecutado importaciones controladas.</p>
                    )}
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
