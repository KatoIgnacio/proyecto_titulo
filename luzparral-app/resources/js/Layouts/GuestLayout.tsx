import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';
import { PropsWithChildren } from 'react';

export default function Guest({ children }: PropsWithChildren) {
    return (
        <div className="min-h-screen bg-slate-100 px-4 py-8 text-slate-900 sm:px-6">
            <div className="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-lg flex-col justify-center">
                <header className="mb-6 text-center">
                    <Link href="/" aria-label="Ir al acceso de SIGCEL" className="inline-flex">
                        <span className="inline-flex rounded-2xl border border-slate-200 bg-white p-3 shadow-sm">
                            <ApplicationLogo
                                withBackground
                                className="h-24 w-24 rounded-xl object-contain"
                            />
                        </span>
                    </Link>

                    <p className="mt-4 text-3xl font-black tracking-[0.08em] text-slate-950">
                        SIGCEL
                    </p>
                    <h1 className="mx-auto mt-2 max-w-md text-base font-semibold leading-snug text-slate-700">
                        Sistema de Información para la Gestión de Contingencias Eléctricas
                    </h1>
                    <div className="mx-auto mt-3 h-1 w-12 rounded-full bg-amber-400" />
                </header>

                <main className="w-full overflow-hidden rounded-2xl border border-slate-200 bg-white px-6 py-6 shadow-sm sm:px-8">
                    {children}
                </main>
            </div>
        </div>
    );
}
