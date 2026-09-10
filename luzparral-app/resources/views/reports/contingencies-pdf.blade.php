<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $reportTitle }}</title>
    <style>
        @page { margin: 12mm 10mm 14mm; }
        * { box-sizing: border-box; }
        body { margin: 0; color: #0f172a; font-family: "DejaVu Sans", sans-serif; font-size: 8px; }
        h1, h2, h3, p { margin-top: 0; }
        h1 { font-size: 21px; line-height: 1.15; margin-bottom: 4px; }
        h2 { color: #102a56; font-size: 17px; margin-bottom: 3px; }
        h3 { color: #1e3a5f; font-size: 11px; margin-bottom: 7px; }
        .muted { color: #64748b; }
        .small { font-size: 7px; }
        .mono { font-family: "DejaVu Sans Mono", monospace; }
        .nowrap { white-space: nowrap; }
        .page-break { page-break-before: always; }
        .avoid-break { page-break-inside: avoid; }
        .brand-table, .two-column, .insights, .summary { border-collapse: separate; width: 100%; }
        .brand-table td { vertical-align: middle; }
        .company-logo { max-height: 53px; max-width: 215px; }
        .system-logo { height: 52px; width: 52px; }
        .title-block { border-bottom: 3px solid #1646a3; margin-bottom: 10px; padding-bottom: 9px; }
        .eyebrow { color: #1646a3; font-size: 8px; font-weight: bold; letter-spacing: 1.25px; text-transform: uppercase; }
        .meta { color: #64748b; font-size: 7.5px; }
        .filters { background: #eff5ff; border: 1px solid #cbdcf7; border-left: 4px solid #1646a3; border-radius: 4px; margin-bottom: 10px; padding: 7px 9px; }
        .filter { display: inline-block; margin: 2px 18px 2px 0; }
        .filter strong { color: #334155; }
        .summary { border-spacing: 6px 0; margin: 0 -6px 10px; width: 101.2%; }
        .summary td { border: 1px solid #d8e2ef; border-bottom: 3px solid #1646a3; border-radius: 4px; padding: 7px 8px; width: 16.66%; }
        .summary .label { color: #64748b; font-size: 6.2px; font-weight: bold; text-transform: uppercase; }
        .summary .value { font-size: 14px; font-weight: bold; margin-top: 2px; }
        .executive { background: #f8fafc; border: 1px solid #dbe3ee; border-radius: 5px; line-height: 1.55; margin-bottom: 9px; padding: 9px 11px; }
        .executive strong { color: #1646a3; }
        .insights { border-spacing: 6px 0; margin: 0 -6px 9px; width: 101.2%; }
        .insights td { background: #fff; border: 1px solid #dbe3ee; border-radius: 4px; padding: 7px 8px; vertical-align: top; width: 25%; }
        .insight-label { color: #64748b; font-size: 6.2px; font-weight: bold; text-transform: uppercase; }
        .insight-value { color: #102a56; font-size: 11px; font-weight: bold; margin: 2px 0; }
        .chart-card { border: 1px solid #dbe3ee; border-radius: 5px; padding: 8px 10px; }
        .chart-card img { display: block; height: 205px; width: 100%; }
        .section-head { border-bottom: 2px solid #1646a3; margin-bottom: 11px; padding-bottom: 7px; }
        .section-kicker { color: #1646a3; font-size: 7px; font-weight: bold; letter-spacing: 1px; text-transform: uppercase; }
        .two-column { border-spacing: 10px 0; margin: 0 -10px; width: 102.2%; }
        .two-column > tbody > tr > td { vertical-align: top; width: 50%; }
        .panel { border: 1px solid #dbe3ee; border-radius: 5px; padding: 10px; }
        .bar-row { margin-bottom: 9px; }
        .bar-label { color: #334155; font-size: 7.5px; margin-bottom: 3px; }
        .bar-label strong { color: #0f172a; }
        .bar-track { background: #e8eef6; border-radius: 5px; height: 7px; overflow: hidden; }
        .bar-fill { background: #2563eb; border-radius: 5px; height: 7px; }
        .bar-fill.green { background: #10b981; }
        .bar-fill.orange { background: #f59e0b; }
        .analysis-note { background: #fff8e6; border: 1px solid #fde3a7; border-left: 4px solid #f59e0b; border-radius: 4px; line-height: 1.5; margin-top: 10px; padding: 8px 10px; }
        table.data { border-collapse: collapse; table-layout: fixed; width: 100%; }
        table.data thead { display: table-header-group; }
        table.data tr { page-break-inside: avoid; }
        table.data th { background: #102a56; color: #fff; font-size: 6.2px; padding: 6px 4px; text-align: left; text-transform: uppercase; }
        table.data td { border-bottom: 1px solid #e2e8f0; line-height: 1.35; padding: 5px 4px; vertical-align: top; }
        table.data tbody tr:nth-child(even) { background: #f8fafc; }
        table.data .num { text-align: right; }
        .trend-table { margin-top: 8px; }
        .trend-table td, .trend-table th { padding-bottom: 4px !important; padding-top: 4px !important; }
        .contingency-card { border: 1px solid #cbd5e1; border-radius: 4px; margin-bottom: 7px; }
        .card-summary { border-collapse: collapse; table-layout: fixed; width: 100%; }
        .card-summary td { border-right: 1px solid #e2e8f0; padding: 5px 6px; vertical-align: top; }
        .card-summary td:last-child { border-right: 0; }
        .field-label { color: #64748b; font-size: 5.7px; font-weight: bold; letter-spacing: .25px; text-transform: uppercase; }
        .field-value { color: #0f172a; font-size: 7px; font-weight: bold; margin-top: 1px; }
        .card-description { background: #fff; border-top: 1px solid #e2e8f0; color: #475569; font-size: 6.2px; padding: 4px 6px; }
        .card-description strong { color: #0f172a; }
        .timeline-cell { background: #f4f7fb; border-top: 1px solid #dbe3ee; color: #334155; padding: 5px 7px; }
        .timeline-title { color: #1646a3; font-size: 6.4px; font-weight: bold; letter-spacing: .4px; text-transform: uppercase; }
        .timeline-event { border-left: 2px solid #93c5fd; display: inline-block; margin: 3px 12px 2px 0; padding-left: 5px; vertical-align: top; width: 30%; }
        .timeline-event strong { color: #0f172a; }
        .timeline-note { color: #64748b; font-size: 6px; margin-top: 1px; }
        .tag { background: #e8eef6; border-radius: 7px; display: inline-block; font-size: 6px; margin-top: 2px; padding: 1px 5px; }
        .empty { color: #64748b; padding: 24px !important; text-align: center; }
        .ethics { background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 4px; color: #166534; font-size: 6.8px; margin-top: 9px; padding: 6px 8px; }
        footer { bottom: -9mm; color: #64748b; font-size: 6.7px; left: 0; position: fixed; right: 0; text-align: center; }
        .page:after { content: counter(page); }
    </style>
</head>
<body>
    <footer>Luzparral - {{ $reportTitle }} - Página <span class="page"></span></footer>

    <section>
        <table class="brand-table">
            <tr>
                <td>
                    @if ($companyLogo !== '')
                        <img class="company-logo" src="{{ $companyLogo }}" alt="Luzparral">
                    @else
                        <strong>LUZPARRAL</strong>
                    @endif
                </td>
                <td style="text-align: right">
                    @if ($systemLogo !== '')
                        <img class="system-logo" src="{{ $systemLogo }}" alt="Sistema de contingencias">
                    @endif
                </td>
            </tr>
        </table>

        <div class="title-block">
            <div class="eyebrow">Sistema de información para apoyo a la gestión</div>
            <h1>{{ $reportTitle }}</h1>
            <div class="meta">Generado: {{ $generatedAt->format('d-m-Y H:i') }} · Corte de datos: {{ $referenceDate->format('d-m-Y H:i') }} · Información sintética para fines académicos</div>
        </div>

        <div class="filters">
            @foreach ($filters as $filter)
                <span class="filter"><strong>{{ $filter['label'] }}:</strong> {{ $filter['value'] }}</span>
            @endforeach
        </div>

        <table class="summary">
            <tr>
                <td><div class="label">Contingencias</div><div class="value">{{ number_format($summary['total'], 0, ',', '.') }}</div></td>
                <td><div class="label">Activas</div><div class="value">{{ number_format($summary['active'], 0, ',', '.') }}</div></td>
                <td><div class="label">Afectados</div><div class="value">{{ number_format($summary['affected'], 0, ',', '.') }}</div></td>
                <td><div class="label">Críticos</div><div class="value">{{ number_format($summary['critical'], 0, ',', '.') }}</div></td>
                <td><div class="label">Electrodependientes</div><div class="value">{{ number_format($summary['electrodependent'], 0, ',', '.') }}</div></td>
                <td><div class="label">Duración promedio</div><div class="value">{{ $summary['averageMinutes'] === null ? 'S/R' : number_format($summary['averageMinutes'], 0, ',', '.').' min' }}</div></td>
            </tr>
        </table>

        @if ($includesAnalytics)
            <div class="executive">
                <strong>Lectura ejecutiva.</strong>
                En el período seleccionado se registraron <strong>{{ number_format($summary['total'], 0, ',', '.') }} contingencias</strong> y
                {{ number_format($summary['affected'], 0, ',', '.') }} afectaciones acumuladas.
                @if ($analytics['topCommune'])
                    La comuna con mayor impacto fue <strong>{{ $analytics['topCommune']['name'] }}</strong>, con
                    {{ number_format($analytics['topCommune']['affected'], 0, ',', '.') }} afectados en
                    {{ number_format($analytics['topCommune']['incidents'], 0, ',', '.') }} eventos.
                @endif
                La proporción de afectaciones asociadas a contingencias con reposición registrada alcanza un
                <strong>{{ $analytics['restorationRate'] }}%</strong>.
            </div>
        @else
            <div class="executive">
                <strong>Propósito del informe.</strong>
                Este documento presenta el desarrollo cronológico de las contingencias incluidas por los filtros aplicados. Cada ficha integra su identificación operativa, impacto, causa, duración y todos los cambios de estado registrados.
            </div>
        @endif

        <table class="insights">
            <tr>
                <td>
                    <div class="insight-label">{{ $includesAnalytics ? 'Comuna más afectada' : 'Contingencias seleccionadas' }}</div>
                    <div class="insight-value">{{ $includesAnalytics ? ($analytics['topCommune']['name'] ?? 'Sin datos') : number_format($summary['total'], 0, ',', '.') }}</div>
                    <div class="muted small">{{ $includesAnalytics ? number_format($analytics['topCommune']['affected'] ?? 0, 0, ',', '.').' afectados acumulados' : 'fichas operacionales' }}</div>
                </td>
                <td>
                    <div class="insight-label">{{ $includesAnalytics ? 'Mayor período de afectación' : 'Hitos históricos' }}</div>
                    <div class="insight-value">{{ $includesAnalytics ? ($analytics['peak']['label'] ?? 'Sin datos') : number_format($analytics['historyCount'], 0, ',', '.') }}</div>
                    <div class="muted small">{{ $includesAnalytics ? number_format($analytics['peak']['affected'] ?? 0, 0, ',', '.').' afectados reportados' : 'eventos de trazabilidad' }}</div>
                </td>
                <td>
                    <div class="insight-label">Promedio de hitos</div>
                    <div class="insight-value">{{ number_format($analytics['averageHistoryEvents'], 1, ',', '.') }}</div>
                    <div class="muted small">por contingencia seleccionada</div>
                </td>
                <td>
                    <div class="insight-label">{{ $includesAnalytics ? 'Reposición registrada' : 'Páginas de detalle estimadas' }}</div>
                    <div class="insight-value">{{ $includesAnalytics ? $analytics['restorationRate'].'%' : max(1, (int) ceil($summary['total'] / 4)) }}</div>
                    <div class="muted small">{{ $includesAnalytics ? 'Sobre afectaciones del período' : 'cuatro fichas por página' }}</div>
                </td>
            </tr>
        </table>

        @if ($includesAnalytics)
            <div class="chart-card avoid-break">
                <h3 style="margin-bottom: 1px">Evolución de clientes afectados y repuestos</h3>
                <p class="muted small" style="margin-bottom: 3px">Comparación temporal de nuevas afectaciones y reposiciones registradas.</p>
                <img src="{{ $analytics['trendChart'] }}" alt="Evolución temporal">
            </div>
        @else
            <div class="analysis-note">
                <strong>Criterio de lectura:</strong> las fichas se presentan desde la contingencia más reciente a la más antigua. Dentro de cada ficha, los hitos se ordenan cronológicamente.
            </div>
            <div class="ethics">Los valores presentados son sintéticos y agregados. El informe no incorpora identificación de clientes, números de suministro ni datos personales.</div>
        @endif
    </section>

    @if ($includesAnalytics)
    <section class="page-break">
        <div class="section-head">
            <div class="section-kicker">Análisis temporal</div>
            <h2>Evolución de las contingencias</h2>
            <p class="muted">Los períodos se adaptan al filtro seleccionado y permiten comparar la aparición de eventos con su reposición efectiva.</p>
        </div>

        <div class="chart-card avoid-break">
            <img src="{{ $analytics['trendChart'] }}" alt="Gráfico de evolución temporal">
        </div>

        <table class="data trend-table">
            <thead>
                <tr>
                    <th style="width: 24%">Período</th>
                    <th style="width: 19%" class="num">Contingencias iniciadas</th>
                    <th style="width: 19%" class="num">Contingencias repuestas</th>
                    <th style="width: 19%" class="num">Clientes afectados</th>
                    <th style="width: 19%" class="num">Clientes repuestos</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($analytics['trend'] as $point)
                    <tr>
                        <td><strong>{{ $point['label'] }}</strong></td>
                        <td class="num">{{ number_format($point['started'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($point['completed'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($point['affected'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($point['restored'], 0, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No existen datos temporales para los filtros seleccionados.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="analysis-note">
            <strong>Criterio de lectura:</strong> “Afectados” agrupa clientes asociados a contingencias iniciadas en cada período; “Repuestos” agrupa clientes asociados a contingencias cuya reposición efectiva quedó registrada en ese período.
        </div>
    </section>

    <section class="page-break">
        <div class="section-head">
            <div class="section-kicker">Análisis territorial y operacional</div>
            <h2>Comunas más afectadas y distribución de eventos</h2>
            <p class="muted">Priorización territorial según afectaciones acumuladas dentro de la selección.</p>
        </div>

        @php
            $maxCommuneAffected = max(1, (int) ($analytics['communes']->max('affected') ?? 1));
        @endphp
        <table class="two-column">
            <tr>
                <td>
                    <div class="panel avoid-break">
                        <h3>Comunas con mayor afectación</h3>
                        @forelse ($analytics['communes']->take(5) as $commune)
                            <div class="bar-row">
                                <div class="bar-label">
                                    <strong>{{ $commune['name'] }}</strong>
                                    <span style="float: right">{{ number_format($commune['affected'], 0, ',', '.') }} afectados · {{ $commune['incidents'] }} eventos</span>
                                </div>
                                <div class="bar-track"><div class="bar-fill" style="width: {{ max(2, round(($commune['affected'] / $maxCommuneAffected) * 100)) }}%"></div></div>
                            </div>
                        @empty
                            <p class="muted">Sin información territorial disponible.</p>
                        @endforelse
                    </div>
                </td>
                <td>
                    <div class="panel avoid-break" style="margin-bottom: 10px">
                        <h3>Distribución por estado</h3>
                        @foreach ($analytics['statuses'] as $status)
                            <div class="bar-row">
                                <div class="bar-label"><strong>{{ $status['label'] }}</strong><span style="float: right">{{ $status['total'] }} · {{ $status['percentage'] }}%</span></div>
                                <div class="bar-track"><div class="bar-fill green" style="width: {{ max(2, $status['percentage']) }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                    <div class="panel avoid-break">
                        <h3>Distribución por criticidad</h3>
                        @foreach ($analytics['priorities'] as $priority)
                            <div class="bar-row">
                                <div class="bar-label"><strong>{{ $priority['label'] }}</strong><span style="float: right">{{ $priority['total'] }} · {{ $priority['percentage'] }}%</span></div>
                                <div class="bar-track"><div class="bar-fill orange" style="width: {{ max(2, $priority['percentage']) }}%"></div></div>
                            </div>
                        @endforeach
                    </div>
                </td>
            </tr>
        </table>

        <h3 style="margin-top: 13px">Resumen territorial completo</h3>
        <table class="data">
            <thead>
                <tr>
                    <th>Comuna</th>
                    <th class="num">Contingencias</th>
                    <th class="num">Activas</th>
                    <th class="num">Afectados acumulados</th>
                    <th class="num">Participación</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($analytics['communes'] as $commune)
                    <tr>
                        <td><strong>{{ $commune['name'] }}</strong></td>
                        <td class="num">{{ number_format($commune['incidents'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($commune['active'], 0, ',', '.') }}</td>
                        <td class="num">{{ number_format($commune['affected'], 0, ',', '.') }}</td>
                        <td class="num">{{ $summary['affected'] === 0 ? 0 : round(($commune['affected'] / $summary['affected']) * 100, 1) }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="empty">No existen comunas para los filtros seleccionados.</td></tr>
                @endforelse
            </tbody>
        </table>

        <div class="ethics">Los valores presentados son sintéticos y agregados. El informe no incorpora identificación de clientes, números de suministro ni datos personales.</div>
    </section>
    @endif

    @if ($includesDetail)
    @forelse ($rows->chunk(4) as $chunkIndex => $rowChunk)
        <section class="page-break">
            <div class="section-head">
                <div class="section-kicker">Detalle operacional · Página de fichas {{ $chunkIndex + 1 }} de {{ $rows->chunk(4)->count() }}</div>
                <h2>{{ $chunkIndex === 0 ? 'Desarrollo y evolución por contingencia' : 'Desarrollo y evolución por contingencia (continuación)' }}</h2>
                <p class="muted">Cada ficha integra ubicación, estado, criticidad, causa, impacto, fechas, duración y la secuencia completa de hitos históricos asociados.</p>
            </div>

            @foreach ($rowChunk as $row)
                <div class="contingency-card">
                    <table class="card-summary">
                        <tr>
                            <td style="width: 13%"><div class="field-label">Contingencia</div><div class="field-value mono">{{ $row['code'] }}</div><div class="muted mono small">{{ $row['osf_code'] }}</div></td>
                            <td style="width: 12%"><div class="field-label">Ubicación</div><div class="field-value">{{ $row['commune'] }}</div><div class="muted mono small">{{ $row['feeder'] }}</div></td>
                            <td style="width: 10%"><div class="field-label">Estado</div><div class="field-value">{{ $row['status'] }}</div><div class="tag">{{ $row['priority'] }}</div></td>
                            <td style="width: 11%"><div class="field-label">Impacto</div><div class="field-value">{{ number_format($row['affected_total'], 0, ',', '.') }} afectados</div><div class="muted small">{{ number_format($row['critical_affected'], 0, ',', '.') }} crít. · {{ number_format($row['electrodependent_affected'], 0, ',', '.') }} elect.</div></td>
                            <td style="width: 13%"><div class="field-label">Inicio</div><div class="field-value">{{ $row['started_at'] }}</div></td>
                            <td style="width: 13%"><div class="field-label">Reposición estimada</div><div class="field-value">{{ $row['estimated_restore_at'] !== '' ? $row['estimated_restore_at'] : 'Sin registro' }}</div></td>
                            <td style="width: 13%"><div class="field-label">Reposición efectiva</div><div class="field-value">{{ $row['restored_at'] !== '' ? $row['restored_at'] : 'Pendiente' }}</div></td>
                            <td style="width: 15%"><div class="field-label">Duración y trazabilidad</div><div class="field-value">{{ $row['duration_minutes'] === null ? 'S/R' : number_format($row['duration_minutes'], 0, ',', '.').' min' }}</div><div class="muted small">{{ number_format($row['history_count'] ?? 0, 0, ',', '.') }} hitos</div></td>
                        </tr>
                    </table>
                    <div class="card-description"><strong>{{ $row['cause'] }}.</strong> {{ $row['description'] }}</div>
                    <div class="timeline-cell">
                        <div class="timeline-title">Evolución cronológica</div>
                        @forelse ($row['history'] as $event)
                            <div class="timeline-event">
                                <strong>{{ $event['event_at'] }} · {{ $event['status'] }}</strong>
                                <div class="timeline-note">{{ $event['note'] }} · {{ $event['responsible'] }} ({{ $event['source'] }})</div>
                            </div>
                        @empty
                            <span class="muted">Sin hitos históricos registrados.</span>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </section>
    @empty
        <section class="page-break">
            <div class="section-head"><h2>Desarrollo y evolución por contingencia</h2></div>
            <div class="empty">No existen contingencias para los filtros seleccionados.</div>
        </section>
    @endforelse
    @endif
</body>
</html>
