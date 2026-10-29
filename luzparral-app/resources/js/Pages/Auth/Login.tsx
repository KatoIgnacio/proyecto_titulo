import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset, transform } = useForm({
        email: '',
        password: '',
    });

    const submit: FormEventHandler<HTMLFormElement> = (event) => {
        event.preventDefault();

        const submittedValues = new FormData(event.currentTarget);
        const email = String(submittedValues.get('email') ?? data.email).trim();
        const password = String(
            submittedValues.get('password') ?? data.password,
        );

        transform(() => ({ email, password }));
        post(route('login'), {
            preserveScroll: true,
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Acceso" />

            <div className="mb-5">
                <h2 className="text-lg font-bold text-slate-900">Acceso al sistema</h2>
            </div>

            {status && (
                <div className="mb-4 text-sm font-medium text-green-600">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Correo electrónico" />
                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        required
                        isFocused
                        onChange={(event) => setData('email', event.target.value)}
                    />
                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Contraseña" />
                    <TextInput
                        id="password"
                        type="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        required
                        onChange={(event) =>
                            setData('password', event.target.value)
                        }
                    />
                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-5 space-y-3">
                    <PrimaryButton
                        type="submit"
                        className="w-full justify-center"
                        disabled={processing}
                    >
                        {processing ? 'Ingresando…' : 'Ingresar'}
                    </PrimaryButton>

                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="block text-center text-sm text-slate-600 underline decoration-slate-300 underline-offset-4 hover:text-slate-900"
                        >
                            Recuperar contraseña
                        </Link>
                    )}
                </div>
            </form>

            <div className="mt-6 border-t border-gray-200 pt-5 text-center">
                <Link
                    href={route('register')}
                    className="inline-flex w-full items-center justify-center rounded-md border border-blue-700 px-4 py-2.5 text-xs font-semibold uppercase tracking-widest text-blue-700 transition hover:bg-blue-50 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2"
                >
                    Crear cuenta de consulta
                </Link>
            </div>
        </GuestLayout>
    );
}
