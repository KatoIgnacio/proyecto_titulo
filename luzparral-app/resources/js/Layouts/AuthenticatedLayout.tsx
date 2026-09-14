import Dropdown from '@/Components/Dropdown';
import { PageProps, UserRole } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { PropsWithChildren, ReactNode, useState } from 'react';

const roleLabels: Record<UserRole, string> = {
    admin: 'Administración',
    supervisor: 'Supervisión',
    operator: 'Operación',
    viewer: 'Consulta',
};

type IconName =
    | 'dashboard'
    | 'map'
    | 'list'
    | 'search'
    | 'report'
    | 'menu'
    | 'close';

const iconPaths: Record<IconName, ReactNode> = {
    dashboard: <path d="M4 19V9m6 10V5m6 14v-7m4 7H2" />,
    map: <path d="m3 6 5-2 8 3 5-2v13l-5 2-8-3-5 2V6Zm5-2v13m8-10v13" />,
    list: <path d="M9 6h11M9 12h11M9 18h11M4 6h.01M4 12h.01M4 18h.01" />,
    search: <path d="m21 21-4.35-4.35m2.35-5.65a8 8 0 1 1-16 0 8 8 0 0 1 16 0Z" />,
    report: <path d="M6 2h9l4 4v16H6V2Zm9 0v5h5M9 12h7m-7 4h7" />,
    menu: <path d="M4 6h16M4 12h16M4 18h16" />,
    close: <path d="m6 6 12 12M18 6 6 18" />,
};

function Icon({ name, className = 'h-5 w-5' }: { name: IconName; className?: string }) {
    return (
        <svg
            aria-hidden="true"
            className={className}
            fill="none"
            viewBox="0 0 24 24"
            stroke="currentColor"
            strokeWidth="1.8"
            strokeLinecap="round"
            strokeLinejoin="round"
        >
            {iconPaths[name]}
        </svg>
    );
}

export default function Authenticated({
    header,
    children,
}: PropsWithChildren<{ header?: ReactNode }>) {
    const { user, permissions } = usePage<PageProps>().props.auth;
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const dashboardActive = route().current('dashboard');
    const mapActive = route().current('contingencies.map');
    const detailActive = route().current('contingencies.show');
    const searchActive = route().current('contingencies.search');
    const reportsActive = route().current('reports.*');

    const activeClass = 'bg-blue-600 text-white shadow-sm';
    const inactiveClass = 'text-slate-300 transition hover:bg-slate-900 hover:text-white';

    const navigation = (
        <nav aria-label="Navegación principal" className="space-y-1 px-3">
            <Link
                href={route('dashboard')}
                className={`flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold ${dashboardActive ? activeClass : inactiveClass}`}
            >
                <Icon name="dashboard" />
                Panel general
            </Link>

            <Link
                href={route('contingencies.map')}
                className={`flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold ${mapActive ? activeClass : inactiveClass}`}
            >
                <Icon name="map" />
                Mapa de contingencias
            </Link>

            <div
                className={`flex items-center justify-between rounded-lg px-3 py-3 text-sm font-semibold ${detailActive ? activeClass : 'text-slate-400'}`}
                title={detailActive ? 'Expediente seleccionado' : 'Selecciona una contingencia desde el panel o el mapa'}
            >
                <span className="flex items-center gap-3">
                    <Icon name="list" />
                    Detalle de contingencia
                </span>
                {!detailActive && (
                    <span className="rounded bg-slate-800 px-1.5 py-0.5 text-[9px] font-semibold uppercase tracking-wide text-slate-400">
                        Selecciona
                    </span>
                )}
            </div>

            <Link
                href={route('contingencies.search')}
                className={`flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold ${searchActive ? activeClass : inactiveClass}`}
            >
                <Icon name="search" />
                Buscador operacional
            </Link>

            {permissions.viewReports && (
                <Link
                    href={route('reports.index')}
                    className={`flex items-center gap-3 rounded-lg px-3 py-3 text-sm font-semibold ${reportsActive ? activeClass : inactiveClass}`}
                >
                    <Icon name="report" />
                    Informes
                </Link>
            )}
        </nav>
    );

    const sidebarContent = (
        <>
            <div className="border-b border-slate-800 px-6 py-7">
                <Link href={route('dashboard')} className="flex items-center gap-3">
                    <img src="/images/logo-sistema-transparente.png" alt="" className="h-12 w-12 shrink-0 object-contain" />
                    <span>
                        <span className="block text-xl font-black tracking-wide text-white">LUZPARRAL</span>
                        <span className="mt-1 block text-[10px] font-semibold uppercase tracking-[0.14em] text-blue-300">
                            Gestión de contingencias
                        </span>
                    </span>
                </Link>
            </div>

            <div className="flex-1 py-5">{navigation}</div>

            <div className="space-y-3 border-t border-slate-800 p-4">
                <div className="rounded-lg border border-slate-700 bg-slate-950/40 p-3 text-[11px] leading-relaxed text-slate-400">
                    <p className="font-semibold text-slate-200">Resguardo ético</p>
                    <p className="mt-1">Datos sintéticos y anonimizados para fines académicos.</p>
                </div>
                <Link
                    href={route('logout')}
                    method="post"
                    as="button"
                    className="w-full rounded-lg bg-slate-800 px-3 py-2.5 text-center text-xs font-semibold text-rose-300 transition hover:bg-slate-700"
                >
                    Cerrar sesión
                </Link>
            </div>
        </>
    );

    return (
        <div className="min-h-screen bg-slate-100 text-slate-900">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-64 flex-col bg-slate-950 lg:flex">
                {sidebarContent}
            </aside>

            {mobileMenuOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <button
                        aria-label="Cerrar navegación"
                        className="absolute inset-0 bg-slate-950/60"
                        onClick={() => setMobileMenuOpen(false)}
                    />
                    <aside className="relative flex h-full w-72 flex-col bg-slate-950 shadow-2xl">
                        <button
                            aria-label="Cerrar menú"
                            className="absolute right-3 top-3 rounded-lg p-2 text-slate-300 hover:bg-slate-800"
                            onClick={() => setMobileMenuOpen(false)}
                        >
                            <Icon name="close" />
                        </button>
                        {sidebarContent}
                    </aside>
                </div>
            )}

            <div className="lg:pl-64">
                <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div className="flex h-20 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div className="flex min-w-0 items-center gap-3">
                            <button
                                aria-label="Abrir navegación"
                                className="rounded-lg border border-slate-200 p-2 text-slate-600 lg:hidden"
                                onClick={() => setMobileMenuOpen(true)}
                            >
                                <Icon name="menu" />
                            </button>
                            <div className="min-w-0">
                                <p className="truncate text-sm font-bold uppercase tracking-wide text-slate-800 sm:text-base">
                                    Sistema interno de contingencias
                                </p>
                                <p className="hidden text-xs text-slate-500 sm:block">
                                    Información para apoyo a la gestión operativa
                                </p>
                            </div>
                        </div>

                        <Dropdown>
                            <Dropdown.Trigger>
                                <button className="flex items-center gap-3 rounded-xl px-2 py-2 text-left transition hover:bg-slate-50">
                                    <span className="hidden sm:block">
                                        <span className="block max-w-44 truncate text-sm font-semibold text-slate-800">
                                            {user.name}
                                        </span>
                                        <span className="block text-right text-[10px] font-bold uppercase tracking-wide text-blue-600">
                                            {roleLabels[user.role]}
                                        </span>
                                    </span>
                                    <span className="flex h-10 w-10 items-center justify-center rounded-full bg-slate-200 text-sm font-bold text-slate-600">
                                        {user.name
                                            .split(' ')
                                            .slice(0, 2)
                                            .map((part) => part[0])
                                            .join('')
                                            .toUpperCase()}
                                    </span>
                                </button>
                            </Dropdown.Trigger>
                            <Dropdown.Content>
                                <Dropdown.Link href={route('profile.edit')}>
                                    Mi perfil
                                </Dropdown.Link>
                                <Dropdown.Link href={route('logout')} method="post" as="button">
                                    Cerrar sesión
                                </Dropdown.Link>
                            </Dropdown.Content>
                        </Dropdown>
                    </div>
                </header>

                {header && (
                    <div className="border-b border-slate-200 bg-white px-4 py-5 sm:px-6 lg:px-8">
                        {header}
                    </div>
                )}

                <main>{children}</main>
            </div>
        </div>
    );
}
