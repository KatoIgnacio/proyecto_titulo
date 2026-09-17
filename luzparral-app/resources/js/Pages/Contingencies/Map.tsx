import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PeriodFields, { type PeriodFilterValue } from '@/Components/PeriodFields';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import type { LatLngBoundsExpression } from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { FormEvent, useCallback, useEffect, useMemo, useRef, useState } from 'react';
import {
    CircleMarker,
    LayerGroup,
    LayersControl,
    MapContainer,
    TileLayer,
    Tooltip,
    useMap,
    useMapEvents,
} from 'react-leaflet';

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
    status: string;
};

type FilterForm = PeriodFilterValue & {
    commune: string;
    feeder: string;
    priority: string;
    status: string;
};

type CommuneOption = {
    id: number;
    name: string;
    center_lat: string;
    center_lon: string;
};

type FeederOption = {
    id: number;
    commune_id: number;
    code: string;
    name: string;
};

type MapContingency = {
    id: number;
    code: string;
    osf_code: string;
    commune: string | null;
    feeder: string | null;
    status: string;
    priority: string;
    cause: string;
    description: string;
    affected_total: number;
    critical_affected: number;
    electrodependent_affected: number;
    started_at: string | null;
    estimated_restore_at: string | null;
    restored_at: string | null;
};

type MapFeature = {
    key: string;
    latitude: number;
    longitude: number;
    event_count: number;
    affected_total: number;
    critical_affected: number;
    electrodependent_affected: number;
    priority: string;
    contingency: MapContingency | null;
};

type AffectedZone = {
    key: string;
    latitude: number;
    longitude: number;
    supply_points: number;
    contingencies: number;
};

type MapData = {
    summary: {
        events: number;
        affected: number;
        critical: number;
        electrodependent: number;
    };
    features: MapFeature[];
    layers: {
        critical_zones: AffectedZone[];
        electrodependent_zones: AffectedZone[];
    };
    meta: {
        zoom: number;
        bounds_applied: boolean;
        feature_limit: number;
        features_truncated: boolean;
        zones_truncated: boolean;
        can_view_sensitive_layers: boolean;
    };
};

type MapPageProps = {
    filters: Filters;
    referenceDate: string;
    filterOptions: {
        communes: CommuneOption[];
        feeders: FeederOption[];
    };
    mapData: MapData;
};

const statusLabels: Record<string, string> = {
    active: 'Solo activas',
    reported: 'Reportada',
    assigned: 'Asignada',
    in_progress: 'En atención',
    restored: 'Repuesta',
    closed: 'Cerrada',
    all: 'Todos los estados',
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

const priorityColors: Record<string, string> = {
    critical: '#e11d48',
    high: '#f59e0b',
    medium: '#2563eb',
    low: '#64748b',
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

const numberFormatter = new Intl.NumberFormat('es-CL');

function markerRadius(events: number) {
    return Math.min(24, 6 + Math.sqrt(Math.max(0, events - 1)) * 3.25);
}

function zoneRadius(points: number) {
    return Math.min(30, 8 + Math.sqrt(Math.max(1, points)) * 2.4);
}

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

function FitMapToEvents({ features }: { features: MapFeature[] }) {
    const map = useMap();

    useEffect(() => {
        if (features.length === 0) return;

        if (features.length === 1) {
            map.setView([features[0].latitude, features[0].longitude], 13);
            return;
        }

        const bounds: LatLngBoundsExpression = features.map((feature) => [
            feature.latitude,
            feature.longitude,
        ]);

        map.fitBounds(bounds, { padding: [42, 42], maxZoom: 13 });
    }, [features, map]);

    return null;
}

function ViewportDataLoader({
    filters,
    onData,
    onLoading,
    onError,
}: {
    filters: Filters;
    onData: (data: MapData) => void;
    onLoading: (loading: boolean) => void;
    onError: (message: string | null) => void;
}) {
    const sequence = useRef(0);
    const map = useMap();

    const load = useCallback(async () => {
        const requestSequence = ++sequence.current;
        const bounds = map.getBounds();
        onLoading(true);
        onError(null);

        try {
            const response = await axios.get<MapData>(route('contingencies.map.data'), {
                params: {
                    ...filters,
                    north: bounds.getNorth().toFixed(6),
                    south: bounds.getSouth().toFixed(6),
                    east: bounds.getEast().toFixed(6),
                    west: bounds.getWest().toFixed(6),
                    zoom: map.getZoom(),
                },
                headers: { Accept: 'application/json' },
            });

            if (requestSequence === sequence.current) onData(response.data);
        } catch {
            if (requestSequence === sequence.current) onError('No fue posible actualizar el área visible del mapa.');
        } finally {
            if (requestSequence === sequence.current) onLoading(false);
        }
    }, [filters, map, onData, onError, onLoading]);

    useMapEvents({ moveend: () => void load() });
    useEffect(() => { void load(); }, [load]);

    return null;
}

function ContingencyFeatures({
    features,
    selectedId,
    onSelect,
}: {
    features: MapFeature[];
    selectedId: number | null;
    onSelect: (id: number) => void;
}) {
    const map = useMap();

    return features.map((feature) => {
        const contingency = feature.contingency;
        const color = priorityColors[feature.priority] ?? priorityColors.low;
        const isSelected = contingency?.id === selectedId;

        return (
            <CircleMarker
                key={feature.key}
                center={[feature.latitude, feature.longitude]}
                radius={markerRadius(feature.event_count)}
                pathOptions={{
                    color: isSelected ? '#0f172a' : color,
                    fillColor: color,
                    fillOpacity: 0.88,
                    opacity: 1,
                    weight: isSelected ? 4 : 2,
                }}
                eventHandlers={{
                    click: () => contingency
                        ? onSelect(contingency.id)
                        : map.setView([feature.latitude, feature.longitude], Math.min(16, map.getZoom() + 2)),
                }}
            >
                <Tooltip direction="top" offset={[0, -6]} opacity={0.96}>
                    <div className="min-w-40">
                        {contingency ? <strong>{contingency.code}</strong> : <strong>{feature.event_count} contingencias agrupadas</strong>}<br />
                        {numberFormatter.format(feature.affected_total)} afectados
                        {!contingency && <><br />Acerca el mapa para ver más detalle.</>}
                    </div>
                </Tooltip>
            </CircleMarker>
        );
    });
}

function AffectedZones({ zones, color, label }: { zones: AffectedZone[]; color: string; label: string }) {
    return zones.map((zone) => (
        <CircleMarker
            key={zone.key}
            center={[zone.latitude, zone.longitude]}
            radius={zoneRadius(zone.supply_points)}
            pathOptions={{ color, fillColor: color, fillOpacity: 0.18, opacity: 0.8, weight: 2 }}
        >
            <Tooltip direction="top" opacity={0.96}>
                <strong>Zona referencial: {label}</strong><br />
                {numberFormatter.format(zone.supply_points)} puntos agregados · {numberFormatter.format(zone.contingencies)} contingencias
            </Tooltip>
        </CircleMarker>
    ));
}

export default function MapPage({
    filters,
    referenceDate,
    filterOptions,
    mapData,
}: MapPageProps) {
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
        status: source.status,
    });

    const initialSelection = mapData.features.find((feature) => feature.contingency)?.contingency?.id ?? null;
    const [form, setForm] = useState<FilterForm>(() => toForm(filters));
    const [data, setData] = useState<MapData>(mapData);
    const [selectedId, setSelectedId] = useState<number | null>(initialSelection);
    const [loadingViewport, setLoadingViewport] = useState(false);
    const [mapError, setMapError] = useState<string | null>(null);

    useEffect(() => setForm(toForm(filters)), [filters]);

    useEffect(() => {
        setData(mapData);
        setMapError(null);
    }, [mapData]);

    useEffect(() => {
        const selectableIds = data.features
            .map((feature) => feature.contingency?.id)
            .filter((id): id is number => id !== undefined);

        if (selectableIds.length === 0) {
            setSelectedId(null);
        } else if (selectedId === null || !selectableIds.includes(selectedId)) {
            setSelectedId(selectableIds[0]);
        }
    }, [data.features, selectedId]);

    const selected = data.features
        .map((feature) => feature.contingency)
        .find((contingency) => contingency?.id === selectedId) ?? null;
    const availableFeeders = useMemo(
        () => filterOptions.feeders.filter((feeder) => !form.commune || feeder.commune_id === Number(form.commune)),
        [filterOptions.feeders, form.commune],
    );
    const initialCenter: [number, number] = mapData.features.length
        ? [mapData.features[0].latitude, mapData.features[0].longitude]
        : [-36.14, -71.83];
    const selectableContingencies = data.features
        .map((feature) => feature.contingency)
        .filter((contingency): contingency is MapContingency => contingency !== null);
    const handleMapData = useCallback((nextData: MapData) => setData(nextData), []);
    const handleMapLoading = useCallback((loading: boolean) => setLoadingViewport(loading), []);
    const handleMapError = useCallback((message: string | null) => setMapError(message), []);

    const submit = (event: FormEvent) => {
        event.preventDefault();
        router.get(route('contingencies.map'), form, { preserveScroll: true, replace: true });
    };

    const reset = () => {
        setForm({ range: '12m', date_day: '', date_month: '', date_year: '', date_from: '', date_to: '', commune: '', feeder: '', priority: '', status: 'active' });
        router.get(route('contingencies.map'), {}, { replace: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <h1 className="text-xl font-black uppercase tracking-tight text-slate-900 sm:text-2xl">
                            Mapa de contingencias
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Ubicación referencial de eventos eléctricos generados sintéticamente.
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2 text-xs font-semibold">
                        <span className="rounded-full bg-slate-100 px-3 py-1.5 text-slate-700">
                            {numberFormatter.format(data.summary.events)} eventos visibles
                        </span>
                        <span className="rounded-full bg-rose-50 px-3 py-1.5 text-rose-700">
                            {numberFormatter.format(data.summary.affected)} afectados
                        </span>
                        <span className="rounded-full bg-emerald-50 px-3 py-1.5 text-emerald-700">
                            Corte {formatDate(referenceDate)}
                        </span>
                    </div>
                </div>
            }
        >
            <Head title="Mapa de contingencias" />

            <div className="space-y-5 p-4 sm:p-6 lg:p-8">
                <section className="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="mb-4 flex items-center justify-between gap-3">
                        <div>
                            <h2 className="font-bold text-slate-900">Filtros del mapa</h2>
                            <p className="mt-1 text-xs text-slate-500">La vista inicial muestra solamente contingencias activas.</p>
                        </div>
                        <button type="button" onClick={reset} className="text-xs font-semibold text-blue-700 hover:text-blue-900">
                            Reiniciar mapa
                        </button>
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
                            Estado
                            <select value={form.status} onChange={(event) => setForm({ ...form, status: event.target.value })} className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                {Object.entries(statusLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </label>
                        <label className="text-xs font-semibold text-slate-600">
                            Criticidad
                            <select value={form.priority} onChange={(event) => setForm({ ...form, priority: event.target.value })} className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                                <option value="">Todas</option>
                                {Object.entries(priorityLabels).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                            </select>
                        </label>
                        <div className="flex items-end">
                            <button type="submit" className="h-[42px] w-full rounded-lg bg-slate-900 px-4 text-sm font-bold text-white transition hover:bg-blue-700">
                                Aplicar filtros
                            </button>
                        </div>
                    </form>
                </section>

                <section className="grid gap-5 xl:grid-cols-[minmax(0,2fr)_minmax(320px,0.8fr)]">
                    <div className="relative min-h-[520px] overflow-hidden rounded-xl border border-slate-300 bg-slate-200 shadow-sm">
                        <MapContainer center={initialCenter} zoom={10} className="h-[620px] w-full" scrollWheelZoom>
                            <TileLayer
                                attribution='&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                                url="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
                            />
                            <FitMapToEvents features={mapData.features} />
                            <ViewportDataLoader filters={filters} onData={handleMapData} onLoading={handleMapLoading} onError={handleMapError} />
                            <LayersControl position="topright">
                                <LayersControl.Overlay checked name="Contingencias">
                                    <LayerGroup>
                                        <ContingencyFeatures features={data.features} selectedId={selectedId} onSelect={setSelectedId} />
                                    </LayerGroup>
                                </LayersControl.Overlay>
                                {data.meta.can_view_sensitive_layers && (
                                    <LayersControl.Overlay name="Zonas críticas">
                                        <LayerGroup>
                                            <AffectedZones zones={data.layers.critical_zones} color="#f97316" label="puntos críticos" />
                                        </LayerGroup>
                                    </LayersControl.Overlay>
                                )}
                                {data.meta.can_view_sensitive_layers && (
                                    <LayersControl.Overlay name="Zonas electrodependientes">
                                        <LayerGroup>
                                            <AffectedZones zones={data.layers.electrodependent_zones} color="#7c3aed" label="electrodependencia" />
                                        </LayerGroup>
                                    </LayersControl.Overlay>
                                )}
                            </LayersControl>
                        </MapContainer>

                        {data.features.length === 0 && !loadingViewport && (
                            <div className="absolute inset-0 z-[500] flex items-center justify-center bg-slate-900/25 p-6">
                                <div className="rounded-lg bg-white px-5 py-4 text-center shadow-lg">
                                    <p className="font-semibold text-slate-900">No hay eventos para esta selección.</p>
                                    <p className="mt-1 text-sm text-slate-500">Modifica los filtros o reinicia el mapa.</p>
                                </div>
                            </div>
                        )}

                        {(loadingViewport || mapError || data.meta.features_truncated || data.meta.zones_truncated) && (
                            <div className="absolute right-4 top-4 z-[500] max-w-xs space-y-2 text-xs">
                                {loadingViewport && <p className="rounded-lg bg-white/95 px-3 py-2 font-semibold text-blue-700 shadow">Actualizando área visible…</p>}
                                {mapError && <p className="rounded-lg bg-rose-50 px-3 py-2 font-semibold text-rose-700 shadow">{mapError}</p>}
                                {(data.meta.features_truncated || data.meta.zones_truncated) && (
                                    <p className="rounded-lg bg-amber-50 px-3 py-2 text-amber-800 shadow">Hay más elementos en esta vista. Acerca el mapa para obtener mayor detalle.</p>
                                )}
                            </div>
                        )}

                        <div className="absolute bottom-6 left-4 z-[500] rounded-lg bg-white/95 p-3 text-xs shadow-lg backdrop-blur">
                            <p className="mb-2 font-bold text-slate-800">Criticidad</p>
                            <div className="grid grid-cols-2 gap-x-4 gap-y-2">
                                {Object.entries(priorityLabels).map(([priority, label]) => (
                                    <span key={priority} className="flex items-center gap-2 text-slate-600">
                                        <i className="h-2.5 w-2.5 rounded-full" style={{ backgroundColor: priorityColors[priority] }} />
                                        {label}
                                    </span>
                                ))}
                            </div>
                            <div className="mt-3 border-t border-slate-200 pt-3">
                                <p className="font-bold text-slate-800">Agrupación geográfica</p>
                                <div className="mt-2 flex items-end gap-4 text-slate-600">
                                    {[
                                        { label: '1', size: 10 },
                                        { label: '2–4', size: 16 },
                                        { label: '5+', size: 22 },
                                    ].map((item) => (
                                        <span key={item.label} className="flex items-center gap-1.5">
                                            <i
                                                className="inline-block rounded-full border-2 border-slate-600 bg-slate-300"
                                                style={{ height: item.size, width: item.size }}
                                            />
                                            {item.label}
                                        </span>
                                    ))}
                                </div>
                                <p className="mt-1.5 max-w-48 text-[10px] leading-4 text-slate-500">
                                    El tamaño crece según los eventos agrupados al nivel de acercamiento actual.
                                </p>
                            </div>
                            {data.meta.can_view_sensitive_layers && (
                                <p className="mt-3 border-t border-slate-200 pt-3 text-[10px] leading-4 text-slate-500">
                                    Las capas críticas y electrodependientes muestran zonas agregadas, nunca ubicaciones ni identificadores individuales.
                                </p>
                            )}
                        </div>
                    </div>

                    <aside aria-live="polite" className="rounded-xl border border-slate-200 bg-white shadow-sm">
                        {selected ? (
                            <div>
                                <div className="border-b border-slate-200 p-5">
                                    <div className="flex items-start justify-between gap-3">
                                        <div>
                                            <p className="text-[10px] font-bold uppercase tracking-[0.12em] text-slate-400">Expediente sintético</p>
                                            <h2 className="mt-1 font-mono text-lg font-black text-slate-900">{selected.code}</h2>
                                            <p className="mt-1 font-mono text-xs text-slate-500">{selected.osf_code}</p>
                                        </div>
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${statusStyles[selected.status] ?? 'bg-slate-100 text-slate-600'}`}>
                                            {statusLabels[selected.status] ?? selected.status}
                                        </span>
                                    </div>

                                    <label className="mt-4 block text-xs font-semibold text-slate-600">
                                        Evento seleccionado
                                        <select
                                            value={selected.id}
                                            onChange={(event) => setSelectedId(Number(event.target.value))}
                                            className="mt-1 block w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500"
                                        >
                                            {selectableContingencies.map((contingency) => (
                                                <option key={contingency.id} value={contingency.id}>{contingency.code} · {contingency.commune}</option>
                                            ))}
                                        </select>
                                    </label>
                                </div>

                                <div className="space-y-5 p-5">
                                    <dl className="grid grid-cols-2 gap-4 text-sm">
                                        <div>
                                            <dt className="text-xs text-slate-500">Comuna</dt>
                                            <dd className="mt-1 font-semibold text-slate-900">{selected.commune}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">Alimentador</dt>
                                            <dd className="mt-1 font-mono font-semibold text-slate-900">{selected.feeder}</dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">Criticidad</dt>
                                            <dd className="mt-1"><span className={`rounded-full px-2 py-1 text-xs font-semibold ${priorityStyles[selected.priority]}`}>{priorityLabels[selected.priority]}</span></dd>
                                        </div>
                                        <div>
                                            <dt className="text-xs text-slate-500">Inicio</dt>
                                            <dd className="mt-1 font-semibold text-slate-900">{formatDate(selected.started_at)}</dd>
                                        </div>
                                    </dl>

                                    <div className="grid grid-cols-3 gap-2 rounded-lg bg-slate-50 p-3 text-center">
                                        <div>
                                            <p className="text-xl font-black text-slate-900">{numberFormatter.format(selected.affected_total)}</p>
                                            <p className="mt-1 text-[10px] uppercase text-slate-500">Afectados</p>
                                        </div>
                                        <div className="border-x border-slate-200">
                                            <p className="text-xl font-black text-orange-600">{numberFormatter.format(selected.critical_affected)}</p>
                                            <p className="mt-1 text-[10px] uppercase text-slate-500">Críticos</p>
                                        </div>
                                        <div>
                                            <p className="text-xl font-black text-blue-700">{numberFormatter.format(selected.electrodependent_affected)}</p>
                                            <p className="mt-1 text-[10px] uppercase text-slate-500">Electrodep.</p>
                                        </div>
                                    </div>

                                    <div>
                                        <p className="text-xs font-semibold text-slate-500">Causa registrada</p>
                                        <p className="mt-1 font-semibold text-slate-900">{causeLabels[selected.cause] ?? selected.cause}</p>
                                        <p className="mt-2 rounded-lg bg-slate-50 p-3 text-sm leading-relaxed text-slate-600">{selected.description}</p>
                                    </div>

                                    <dl className="space-y-3 border-t border-slate-100 pt-4 text-sm">
                                        <div className="flex justify-between gap-4">
                                            <dt className="text-slate-500">Reposición estimada</dt>
                                            <dd className="text-right font-medium text-slate-800">{formatDate(selected.estimated_restore_at)}</dd>
                                        </div>
                                        <div className="flex justify-between gap-4">
                                            <dt className="text-slate-500">Reposición efectiva</dt>
                                            <dd className="text-right font-medium text-slate-800">{formatDate(selected.restored_at)}</dd>
                                        </div>
                                    </dl>

                                    <Link
                                        href={route('contingencies.show', selected.id)}
                                        className="block rounded-lg bg-slate-900 px-4 py-3 text-center text-sm font-bold text-white transition hover:bg-blue-700"
                                    >
                                        Ver detalle completo
                                    </Link>
                                </div>
                            </div>
                        ) : (
                            <div className="flex min-h-72 items-center justify-center p-6 text-center text-sm text-slate-500">
                                Acerca el mapa o selecciona un evento individual para consultar el detalle.
                            </div>
                        )}
                    </aside>
                </section>

                <div className="flex flex-wrap justify-between gap-3 rounded-lg border border-slate-200 bg-white px-4 py-3 text-xs text-slate-500">
                    <span>Mapa base © OpenStreetMap contributors.</span>
                    <span>Área visible: {numberFormatter.format(data.summary.critical)} críticos · {numberFormatter.format(data.summary.electrodependent)} electrodependientes.</span>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
