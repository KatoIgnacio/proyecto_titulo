import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, useForm } from '@inertiajs/react';
import { FormEvent, useState } from 'react';

type TemporaryViewerAccount = {
    id: number;
    name: string;
    email: string;
    active: boolean;
    expires_at: string | null;
    created_at: string | null;
};

type Credentials = {
    email: string;
    password: string;
    expires_at: string;
} | null;

type Props = {
    accounts: TemporaryViewerAccount[];
    credentials: Credentials;
    maxActiveUsers: number;
    activeUsers: number;
};

function formatDate(value: string | null): string {
    if (!value) return 'Sin vencimiento';

    return new Intl.DateTimeFormat('es-CL', {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

export default function Index({ accounts, credentials, maxActiveUsers, activeUsers }: Props) {
    const [copyStatus, setCopyStatus] = useState<'idle' | 'copied' | 'error'>('idle');
    const form = useForm({
        name: '',
        duration_hours: '24',
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('temporary-viewer-accounts.store'), {
            preserveScroll: true,
            onSuccess: () => form.reset('name'),
        });
    };

    const deactivate = (account: TemporaryViewerAccount) => {
        if (!window.confirm('¿Desactivar esta cuenta temporal de consulta y cerrar sus sesiones?')) return;

        form.delete(route('temporary-viewer-accounts.destroy', account.id), { preserveScroll: true });
    };

    const copyCredentials = async () => {
        if (!credentials) return;

        const text = 'Usuario: ' + credentials.email + '\nContraseña: ' + credentials.password;

        try {
            let copied = false;

            if (window.isSecureContext && navigator.clipboard?.writeText) {
                try {
                    await navigator.clipboard.writeText(text);
                    copied = true;
                } catch {
                    copied = false;
                }
            }

            if (!copied) {
                const textArea = document.createElement('textarea');
                textArea.value = text;
                textArea.setAttribute('readonly', '');
                textArea.style.position = 'fixed';
                textArea.style.opacity = '0';
                document.body.appendChild(textArea);
                textArea.select();

                try {
                    copied = document.execCommand('copy');
                } finally {
                    textArea.remove();
                }
            }

            if (!copied) throw new Error('copy-command-failed');

            setCopyStatus('copied');
        } catch {
            setCopyStatus('error');
        }
    };

    return (
        <AuthenticatedLayout
            header={(
                <div>
                    <h1 className="text-2xl font-black tracking-tight text-slate-950">CUENTAS TEMPORALES DE CONSULTA</h1>
                    <p className="mt-1 text-sm text-slate-500">El mismo perfil de consulta, con vencimiento automático para evaluaciones y visitas.</p>
                </div>
            )}
        >
            <Head title="Cuentas temporales de consulta" />

            <div className="space-y-6 p-4 sm:p-6 lg:p-8">
                {credentials && (
                    <section className="rounded-xl border-2 border-amber-300 bg-amber-50 p-5 shadow-sm">
                        <h2 className="font-black text-amber-950">Credenciales visibles una sola vez</h2>
                        <p className="mt-1 text-sm text-amber-800">Para ingresar, use el correo mostrado como Usuario. Cópielas y entréguelas por un canal privado; la contraseña no se volverá a mostrar.</p>
                        <dl className="mt-4 grid gap-3 sm:grid-cols-3">
                            <div><dt className="text-xs font-bold uppercase text-amber-700">Usuario</dt><dd className="mt-1 break-all font-mono text-sm">{credentials.email}</dd></div>
                            <div><dt className="text-xs font-bold uppercase text-amber-700">Contraseña temporal</dt><dd className="mt-1 break-all font-mono text-sm">{credentials.password}</dd></div>
                            <div><dt className="text-xs font-bold uppercase text-amber-700">Vence</dt><dd className="mt-1 text-sm">{formatDate(credentials.expires_at)}</dd></div>
                        </dl>
                        <button type="button" onClick={copyCredentials} className="mt-4 rounded-lg bg-amber-900 px-4 py-2 text-sm font-bold text-white hover:bg-amber-800">
                            {copyStatus === 'copied' ? 'Credenciales copiadas' : 'Copiar credenciales'}
                        </button>
                        <p className="mt-2 text-sm text-amber-900" role="status" aria-live="polite">
                            {copyStatus === 'copied' && 'Credenciales copiadas al portapapeles.'}
                            {copyStatus === 'error' && 'No fue posible copiar automáticamente. Seleccione el usuario y la contraseña de forma manual.'}
                        </p>
                    </section>
                )}

                <section className="grid gap-6 lg:grid-cols-[minmax(0,420px)_1fr]">
                    <form onSubmit={submit} className="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-lg font-black text-slate-900">Crear cuenta temporal</h2>
                        <p className="mt-1 text-sm text-slate-500">El correo y la contraseña se generan automáticamente.</p>

                        <label className="mt-5 block text-sm font-semibold text-slate-700">
                            Nombre visible
                            <input
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                                maxLength={80}
                                placeholder="Ej.: Evaluación comité 1"
                                className="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            />
                            <span className="mt-1 block text-xs font-normal text-slate-500">Sirve para reconocer la cuenta; no se utiliza para iniciar sesión.</span>
                            {form.errors.name && <span className="mt-1 block text-sm text-rose-600">{form.errors.name}</span>}
                        </label>

                        <label className="mt-4 block text-sm font-semibold text-slate-700">
                            Duración
                            <select
                                value={form.data.duration_hours}
                                onChange={(event) => form.setData('duration_hours', event.target.value)}
                                className="mt-1.5 block w-full rounded-lg border-slate-300 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                            >
                                <option value="4">4 horas</option>
                                <option value="8">8 horas</option>
                                <option value="24">24 horas</option>
                                <option value="72">3 días</option>
                                <option value="168">7 días</option>
                            </select>
                        </label>

                        <button
                            type="submit"
                            disabled={form.processing || activeUsers >= maxActiveUsers}
                            className="mt-5 w-full rounded-lg bg-blue-600 px-4 py-3 text-sm font-bold text-white hover:bg-blue-700 disabled:cursor-not-allowed disabled:bg-slate-300"
                        >
                            Crear cuenta temporal
                        </button>
                        <p className="mt-3 text-xs text-slate-500">Cuentas activas: {activeUsers} de {maxActiveUsers} permitidas.</p>
                    </form>

                    <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4">
                            <h2 className="font-black text-slate-900">Cuentas temporales</h2>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200 text-sm">
                                <thead className="bg-slate-50 text-left text-xs font-bold uppercase text-slate-500">
                                    <tr><th className="px-5 py-3">Nombre</th><th className="px-5 py-3">Usuario</th><th className="px-5 py-3">Vencimiento</th><th className="px-5 py-3">Estado</th><th className="px-5 py-3 text-right">Acción</th></tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {accounts.map((account) => (
                                        <tr key={account.id}>
                                            <td className="px-5 py-4 font-semibold">{account.name}</td>
                                            <td className="px-5 py-4 font-mono text-xs">{account.email}</td>
                                            <td className="px-5 py-4">{formatDate(account.expires_at)}</td>
                                            <td className="px-5 py-4"><span className={'rounded-full px-2.5 py-1 text-xs font-bold ' + (account.active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-200 text-slate-600')}>{account.active ? 'Activa' : 'Inactiva o vencida'}</span></td>
                                            <td className="px-5 py-4 text-right">
                                                {account.active && <button type="button" onClick={() => deactivate(account)} className="font-bold text-rose-700 hover:text-rose-900">Desactivar</button>}
                                            </td>
                                        </tr>
                                    ))}
                                    {accounts.length === 0 && <tr><td colSpan={5} className="px-5 py-12 text-center text-slate-500">No se han creado cuentas temporales.</td></tr>}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section className="rounded-xl border border-blue-200 bg-blue-50 p-4 text-sm text-blue-900">
                    <strong>Permisos:</strong> Consulta puede revisar panel general, buscador, detalle e informes. No puede abrir mapa, pronóstico, importaciones, identificadores de clientes o suministro, ni realizar cambios. Las cuentas permanentes y temporales comparten exactamente estos permisos.
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
