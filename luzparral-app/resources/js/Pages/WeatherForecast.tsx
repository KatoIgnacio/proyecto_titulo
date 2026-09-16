import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';

type ForecastProps = {
    embedUrl: string;
};

export default function WeatherForecast({ embedUrl }: ForecastProps) {
    return (
        <AuthenticatedLayout
            header={(
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-xl font-black uppercase tracking-tight text-slate-900 sm:text-2xl">
                            Pronóstico meteorológico
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Viento y variables meteorológicas referenciales para la zona de Parral.
                        </p>
                    </div>
                    <a
                        href="https://www.windy.com/-36.275/-71.782"
                        target="_blank"
                        rel="noreferrer"
                        className="self-start rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 transition hover:border-blue-400 hover:text-blue-700 sm:self-auto"
                    >
                        Abrir en Windy
                    </a>
                </div>
            )}
        >
            <Head title="Pronóstico meteorológico" />

            <div className="p-4 sm:p-6 lg:p-8">
                <section className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <iframe
                        title="Pronóstico meteorológico de Windy para Parral"
                        src={embedUrl}
                        className="h-[calc(100vh-12rem)] min-h-[620px] w-full border-0"
                        loading="eager"
                        referrerPolicy="strict-origin-when-cross-origin"
                        allowFullScreen
                    />
                </section>
            </div>
        </AuthenticatedLayout>
    );
}
