const $ = (sel) => document.querySelector(sel);

const state = {
  view: 'general',
  general: null,
  ticker: null,
  timer: null,
  meta: null,
  alerts: [],
  _headerBound: false,
  map: null, // { map, kmlLayer, pointsLayer, lastKmlMtime }
};

function qs(params){
  const u = new URLSearchParams();
  Object.entries(params||{}).forEach(([k,v])=>{
    if (v === undefined || v === null) return;
    u.set(k, String(v));
  });
  return u.toString();
}

async function api(action, params={}, timeoutMs=12000){
  const query = qs({action, ...params});
  const url = `api/data.php?${query}`;

  const ctrl = new AbortController();
  const t = setTimeout(()=>ctrl.abort(), timeoutMs);

  let r, txt;
  try{
    r = await fetch(url, { cache:'no-store', signal: ctrl.signal });
    txt = await r.text();
  } catch (e){
    if (e.name === 'AbortError') {
      throw new Error(`Timeout ${timeoutMs}ms en action=${action}`);
    }
    throw e;
  } finally {
    clearTimeout(t);
  }

  const raw = (txt ?? '').trim();
  if (!raw) {
    throw new Error(`Respuesta vacía en action=${action} (HTTP ${r.status}). Revisa /api/data.php?action=${action} en el navegador y el log de PHP.`);
  }

  let json;
  try { json = JSON.parse(raw); }
  catch {
    const preview = raw.slice(0,220).replace(/\s+/g,' ').trim();
    throw new Error(`Respuesta no-JSON en ${action}. HTTP ${r.status}. Preview: ${preview}`);
  }
  if (!r.ok) throw new Error(`HTTP ${r.status} en ${action}: ${json?.error || 'error desconocido'}`);
  if (json && json.error) throw new Error(`API ${action}: ${json.error}`);

  console.log('[API]', action, 'status', r.status, 'keys', json && typeof json === 'object' ? Object.keys(json) : typeof json);

  return json;
}

function setActive(view){
  document.querySelectorAll('.nav button[data-view]').forEach(b=>{
    b.classList.toggle('active', b.dataset.view === view);
  });
}

function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"]|'/g, (c)=>({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'
  }[c]));
}

function donutSpecFromRows(rows){
  // rows: [{COMUNA, CLIENTES}] o similares
  const norm = (rows || []).map(r => ({
    label: (r.COMUNA ?? r.comuna ?? r.CITY ?? r.city ?? 'SIN COMUNA').toString(),
    value: Number(r.CLIENTES ?? r.clientes ?? r.total ?? 0) || 0
  })).filter(x => x.value > 0);

  norm.sort((a,b)=>b.value-a.value);
  const total = norm.reduce((s,x)=>s+x.value,0);
  if (!total) return null;

  const top3 = norm.slice(0,3);
  const othersVal = total - top3.reduce((s,x)=>s+x.value,0);
  const data = [...top3];
  if (othersVal > 0) data.push({ label:'Otros', value: othersVal });

  // colores control-room (local, sin depender de nada)
  const colors = ['#FFB81C', '#00A9E0', '#004B8D', '#999999'];

  // calcula stops para conic-gradient
  let acc = 0;
  const segs = data.map((d,i)=>{
    const pct = (d.value/total)*100;
    const start = acc;
    acc += pct;
    return { ...d, pct, start, end: acc, color: colors[i] || '#777' };
  });

  return { total, segs };
}

function donutHTML(spec){
  if (!spec) return `<div class="muted">Sin datos para gráfico.</div>`;

  const bg = spec.segs.map(s => `${s.color} ${s.start.toFixed(2)}% ${s.end.toFixed(2)}%`).join(', ');
  const legend = spec.segs.map(s => `
    <div class="donut-legend-item">
      <span class="donut-dot" style="background:${s.color}"></span>
      <span class="donut-name">${escapeHtml(s.label)}</span>
      <span class="donut-val">${s.value} (${s.pct.toFixed(0)}%)</span>
    </div>
  `).join('');

  return `
    <div class="donut-wrap">
      <div class="donut" style="background:conic-gradient(${bg})"></div>
      <div class="donut-legend">${legend}</div>
    </div>
  `;
}

function showViewError(title, err){
  const host = document.getElementById('content');
  if (!host) return;
  host.innerHTML = `
    <div class="card">
      <div style="font-weight:1000; color:#8b0000; margin-bottom:6px">${escapeHtml(title)}</div>
      <div class="muted" style="white-space:pre-wrap">${escapeHtml(err?.message || String(err))}</div>
      <div class="muted" style="margin-top:10px">Tip: abre DevTools → Console/Network para ver el request que falló.</div>
    </div>
  `;
}

function fmtBytes(n){
  if(!n) return '0B';
  const u=['B','KB','MB','GB'];
  let i=0; let x=n;
  while(x>1024 && i<u.length-1){ x/=1024; i++; }
  return `${x.toFixed(i?1:0)}${u[i]}`;
}

function renderMeta(meta){
  // Compacto: no mostrar nombres/fechas/tamaños como “chips” grandes arriba.
  // En su lugar: botón "Datos" + panel desplegable.
  state.meta = meta || {};

  const labels = {
    PO: 'PO (órdenes)',
    CLIENTES: 'Clientes (sin suministro)',
    CRITICOS: 'Críticos',
    ED: 'ED (electrodependientes)',
    HIST24: 'Histórico 24h',
    KML: 'Redes (KML)'
  };

  const entries = Object.entries(state.meta);
  const total = entries.length || 0;
  const okCount = entries.filter(([,v])=>v && v.exists).length;

  const itemHtml = entries.map(([k,v])=>{
    const exists = !!(v && v.exists);
    const name = labels[k] || k;
    const size = exists ? fmtBytes(v.size) : '';
    const mtime = exists ? (v.mtime || '') : '';
    const stale = exists ? isStale(mtime) : false;
    const status = !exists ? 'FALTA' : (stale ? 'ANTIGUO' : 'OK');
    const cls = !exists ? 'bad' : (stale ? 'warn' : 'ok');

    return `
      <div class="panel-item">
        <span class="dot ${cls}"></span>
        <div class="panel-main">
          <div class="panel-name">${escapeHtml(name)}</div>
          <div class="panel-sub">${exists ? `Actualizado: ${escapeHtml(shortMtime(mtime))}${size?` · ${escapeHtml(size)}`:''}` : 'No existe en DATA_DIR'}</div>
        </div>
        <span class="panel-status ${cls}">${status}</span>
      </div>
    `;
  }).join('');

  $('#meta').innerHTML = `
    <div class="meta-actions">
      <button id="metaBtn" class="meta-btn" type="button" aria-expanded="false">
        Datos <span class="pill">${okCount}/${total} OK</span>
      </button>
      <button id="alertBtn" class="meta-btn meta-btn--alert" type="button" aria-expanded="false">
        Alertas <span id="alertCount" class="pill pill--alert">0</span>
      </button>
    </div>

    <div id="metaPanel" class="panel hidden" role="dialog" aria-label="Fuentes de datos">
      <div class="panel-title">Fuentes de datos</div>
      <div class="panel-body">
        ${itemHtml || `<div class="muted">Sin información de fuentes.</div>`}
      </div>
    </div>

    <div id="alertPanel" class="panel hidden" role="dialog" aria-label="Alertas">
      <div class="panel-title">Alertas</div>
      <div id="alertPanelBody" class="panel-body">
        <div class="muted">Sin alertas.</div>
      </div>
    </div>
  `;

  bindHeaderPanels();
  // si ya existen alertas calculadas, refléjalas
  updateAlertsUI(state.alerts || []);
}


function bindHeaderPanels(){
  if (state._headerBound) return;
  state._headerBound = true;

  document.addEventListener('click', (e)=>{
    const metaBtn = document.getElementById('metaBtn');
    const alertBtn = document.getElementById('alertBtn');
    const metaPanel = document.getElementById('metaPanel');
    const alertPanel = document.getElementById('alertPanel');

    const t = e.target;
    const inMeta = metaPanel && metaPanel.contains(t);
    const inAlert = alertPanel && alertPanel.contains(t);
    const inMetaBtn = metaBtn && metaBtn.contains(t);
    const inAlertBtn = alertBtn && alertBtn.contains(t);

    if (inMetaBtn){
      togglePanel('meta');
      return;
    }
    if (inAlertBtn){
      togglePanel('alert');
      return;
    }

    // click fuera: cerrar
    if (!inMeta && !inMetaBtn) closePanel('meta');
    if (!inAlert && !inAlertBtn) closePanel('alert');
  });

  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape'){
      closePanel('meta');
      closePanel('alert');
    }
  });
}

function togglePanel(which){
  const metaPanel = document.getElementById('metaPanel');
  const alertPanel = document.getElementById('alertPanel');
  const metaBtn = document.getElementById('metaBtn');
  const alertBtn = document.getElementById('alertBtn');

  if (which === 'meta'){
    const isOpen = metaPanel && !metaPanel.classList.contains('hidden');
    if (isOpen) closePanel('meta'); else { openPanel('meta'); closePanel('alert'); }
    metaBtn && metaBtn.setAttribute('aria-expanded', String(!isOpen));
  }
  if (which === 'alert'){
    const isOpen = alertPanel && !alertPanel.classList.contains('hidden');
    if (isOpen) closePanel('alert'); else { openPanel('alert'); closePanel('meta'); }
    alertBtn && alertBtn.setAttribute('aria-expanded', String(!isOpen));
  }
}

function openPanel(which){
  const el = document.getElementById(which === 'meta' ? 'metaPanel' : 'alertPanel');
  if (el) el.classList.remove('hidden');
}
function closePanel(which){
  const el = document.getElementById(which === 'meta' ? 'metaPanel' : 'alertPanel');
  if (el) el.classList.add('hidden');
  const btn = document.getElementById(which === 'meta' ? 'metaBtn' : 'alertBtn');
  if (btn) btn.setAttribute('aria-expanded', 'false');
}

function parseMtimeToMs(s){
  // Espera "YYYY-MM-DD HH:MM:SS"
  const m = /^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?$/.exec(String(s||'').trim());
  if (!m) return null;
  const Y=Number(m[1]), Mo=Number(m[2])-1, D=Number(m[3]), H=Number(m[4]), Mi=Number(m[5]), S=Number(m[6]||0);
  return new Date(Y, Mo, D, H, Mi, S).getTime();
}

function shortMtime(s){
  const ms = parseMtimeToMs(s);
  if (!ms) return String(s||'');
  const d = new Date(ms);
  const pad = (n)=>String(n).padStart(2,'0');
  return `${pad(d.getDate())}-${pad(d.getMonth()+1)} ${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

function isStale(mtimeStr){
  const ms = parseMtimeToMs(mtimeStr);
  if (!ms) return false;
  const ageMin = (Date.now() - ms) / 60000;
  return ageMin > 10; // umbral: 10 min sin actualización => "antiguo"
}

function computeAlerts(meta, kpis){
  const alerts = [];
  meta = meta || {};

  const req = [
    ['CLIENTES','Clientes'],
    ['PO','PO'],
    ['CRITICOS','Críticos'],
    ['ED','ED'],
    ['KML','Redes (KML)'],
  ];
  const opt = [
    ['HIST24','Histórico 24h'],
  ];

  for (const [key,label] of req){
    const v = meta[key];
    if (!v || !v.exists){
      alerts.push({level:'danger', title:`Falta archivo: ${label}`, detail:`No existe en DATA_DIR (${key}).`});
    } else if (isStale(v.mtime)){
      alerts.push({level:'warn', title:`Datos antiguos: ${label}`, detail:`Última actualización: ${shortMtime(v.mtime)}.`});
    }
  }
  for (const [key,label] of opt){
    const v = meta[key];
    if (!v || !v.exists){
      alerts.push({level:'info', title:`Opcional no disponible: ${label}`, detail:`No se encontró ${key}; el resto funciona igual.`});
    }
  }

  if (kpis){
    const cs = Number(kpis.clientes_sin_suministro||0);
    const ca = Number(kpis.criticos_afectados||0);
    const ea = Number(kpis.ed_afectados||0);

    if (ca > 0) alerts.unshift({level:'danger', title:`Críticos afectados: ${ca}`, detail:`Hay clientes críticos sin suministro.`});
    if (ea > 0) alerts.unshift({level:'danger', title:`ED afectados: ${ea}`, detail:`Hay electrodependientes sin suministro.`});
    if (cs >= 500) alerts.push({level: cs>=1000 ? 'danger' : 'warn', title:`Clientes sin suministro: ${cs}`, detail:`Contingencia con alto volumen de clientes afectados.`});
  }

  // deduplicación simple por título
  const seen = new Set();
  return alerts.filter(a=>{
    if (seen.has(a.title)) return false;
    seen.add(a.title);
    return true;
  });
}

function updateAlertsUI(alerts){
  state.alerts = alerts || [];
  const count = state.alerts.length;

  const elCount = document.getElementById('alertCount');
  if (elCount) elCount.textContent = String(count);

  const body = document.getElementById('alertPanelBody');
  if (body){
    body.innerHTML = count ? `
      <div class="alert-list">
        ${state.alerts.map(a=>`
          <div class="alert alert--${a.level}">
            <div class="alert-title">${escapeHtml(a.title)}</div>
            <div class="alert-detail">${escapeHtml(a.detail||'')}</div>
          </div>
        `).join('')}
      </div>
    ` : `<div class="muted">Sin alertas.</div>`;
  }
}

function renderAlertsInline(alerts){
  const host = document.getElementById('content');
  if (!host) return;

  // limpia versión anterior
  const old = document.getElementById('alertsInline');
  if (old) old.remove();

  // Si no hay alertas, nada
  const list = Array.isArray(alerts) ? alerts : [];
  if (!list.length) return;

  // Solo mostrar si hay "danger" realmente grande (>=5)
  // (Para 1-4 ya tienes KPI amarillo, no hace falta banner)
  const big = list.filter(a => a && a.level === 'danger' && Number(a.count || 0) >= 5);

  if (!big.length) return;

  const top = big.slice(0,2); // máximo 2

  const wrap = document.createElement('div');
  wrap.id = 'alertsInline';
  wrap.innerHTML = `
    <div class="alert-mini-wrap">
      ${top.map(a=>`
        <div class="alert-mini">
          <b>${escapeHtml(a.title)}</b>
          <span class="muted">${escapeHtml(a.detail||'')}</span>
        </div>
      `).join('')}
    </div>
  `;
  host.prepend(wrap);
}

function table(rows, columns){
  if(!rows || rows.length===0) return `<div class="muted">Sin registros.</div>`;

  // Mapeo “humano” para encabezados
  const LABEL = {
    CUSTOMER_ACCOUNT: 'NIS',
    NIS: 'NIS',
    NAME: 'Nombre',
    NOMBRE: 'Nombre',
    CITY: 'Comuna',
    COMUNA: 'Comuna',
    DIRECCION: 'Dirección',
    LOCATION_DESC: 'Dirección',
    POWER_STATUS: 'Estado suministro',
    STATUS: 'Estado',
    ORDER_ID: 'Orden',
    DATE_OF_ACTION: 'Fecha acción',
    PRIORIDAD: 'Prioridad',
    TIPO: 'Tipo',
    EMPRESA: 'Empresa',
    OBSERVACION: 'Observación'
  };

  const humanize = (c) => {
    if (LABEL[c]) return LABEL[c];
    // fallback: CUSTOMER_ACCOUNT -> Customer Account, date_of_action -> Date Of Action
    return String(c)
      .replace(/_/g,' ')
      .toLowerCase()
      .replace(/\b\w/g, m => m.toUpperCase());
  };

  const cols = columns || Object.keys(rows[0]);
  const thead = cols.map(c=>`<th>${escapeHtml(humanize(c))}</th>`).join('');

  const body = rows.map(r=>{
    const tds = cols.map(c=>{
      const val = (r[c] ?? '');
      if ((c === 'STATUS' || c === 'POWER_STATUS') && String(val).trim() !== '') {
        return `<td><span class="badge">${escapeHtml(val)}</span></td>`;
      }
      return `<td>${escapeHtml(val)}</td>`;
    }).join('');
    return `<tr>${tds}</tr>`;
  }).join('');

  return `
    <div class="table-wrap">
      <table>
        <thead><tr>${thead}</tr></thead>
        <tbody>${body}</tbody>
      </table>
    </div>
  `;
}


function openModal({title, bodyHtml}){
  // Crea un modal simple (overlay) para mostrar tablas sin romper la página.
  closeModal();

  const overlay = document.createElement('div');
  overlay.id = 'ciopModal';
  overlay.className = 'modal-overlay';
  overlay.innerHTML = `
    <div class="modal" role="dialog" aria-modal="true">
      <div class="modal-head">
        <div class="modal-title">${escapeHtml(title||'Detalle')}</div>
        <button class="modal-close" type="button" aria-label="Cerrar">Cerrar</button>
      </div>
      <div class="modal-body">${bodyHtml||''}</div>
    </div>
  `;

  overlay.addEventListener('click', (e)=>{
    if (e.target === overlay) closeModal();
  });

  overlay.querySelector('.modal-close').addEventListener('click', closeModal);

  document.body.appendChild(overlay);
  document.body.classList.add('modal-open');

  function onEsc(ev){ if (ev.key === 'Escape') closeModal(); }
  window.addEventListener('keydown', onEsc, {once:true});
}

function closeModal(){
  const el = document.getElementById('ciopModal');
  if (el) el.remove();
  document.body.classList.remove('modal-open');
}

function top10Table(top10, total){
  const rows = (top10||[]).map(x=>{
    const pct = total ? ((x.CLIENTES*100)/total).toFixed(1) : '0.0';
    const comuna = x.COMUNA;
    return `
      <tr>
        <td><a class="link" href="#" data-comuna="${escapeHtml(comuna)}">${escapeHtml(comuna)}</a></td>
        <td style="text-align:right"><b>${escapeHtml(x.CLIENTES)}</b></td>
        <td style="text-align:right">${escapeHtml(pct)}%</td>
      </tr>
    `;
  }).join('');
  return `
    <div class="table-wrap">
      <table style="min-width:auto">
        <thead><tr><th>Comuna</th><th style="text-align:right">Clientes</th><th style="text-align:right">%</th></tr></thead>
        <tbody>${rows}</tbody>
      </table>
    </div>
  `;
}

async function loadGeneral(){
  // Loader inmediato (evita pantalla vacía)
  $('#content').innerHTML = `
    <div class="card">
      <div style="font-weight:1000">Cargando General…</div>
      <div class="muted">Leyendo archivos y calculando KPIs.</div>
    </div>
  `;

  const meta = await api('meta');
  renderMeta(meta.meta);

  const data = await api('general');
  state.general = data;

  // Tu backend actual devuelve: { kpis, clientes, top10_comunas, hist24, hist_missing }
  const k = data.kpis || data.KPIS || null;
  if (!k){
    showViewError('General: payload inválido', new Error('No viene "kpis" en la respuesta de action=general.'));
    return;
  }

  const clientes = data.clientes || data.CLIENTES || [];
  const top10 = data.top10_comunas || data.top_comunas || data.TOP10 || [];
  const hist24 = data.hist24 ?? null;
  const histMissing = !!data.hist_missing;

  // Alertas
  const alerts = computeAlerts(meta.meta, k);
  updateAlertsUI(alerts);
  renderAlertsInline(alerts);

  // ===== KPIs con umbral de color =====
  const kpiLevel = (n) => {
    const v = Number(n) || 0;
    if (v === 0) return 'normal';
    if (v >= 1 && v <= 4) return 'warn';
    return 'danger'; // >=5
  };

  const kpiCard = (title, val, sub = '', level = 'normal') => `
    <div class="card kpi ${level !== 'normal' ? `kpi--${level}` : ''}">
      <div class="kpi-title">${escapeHtml(title)}</div>
      <div class="kpi-val">${escapeHtml(val)}</div>
      ${sub ? `<div class="muted">${escapeHtml(sub)}</div>` : ``}
    </div>
  `;

  // Valores desde backend
  const clientesSin = Number(k?.clientes_sin_suministro ?? 0) || 0;
  const ordenesPO   = Number(k?.ordenes_po ?? 0) || 0;
  const critAfect   = Number(k?.criticos_afectados ?? 0) || 0;
  const critTotal   = Number(k?.criticos_total ?? 0) || 0;
  const edAfect     = Number(k?.ed_afectados ?? 0) || 0;
  const edTotal     = Number(k?.ed_total ?? 0) || 0;

  const kpiHtml = `
    <div class="grid4">
      ${kpiCard('Clientes sin suministro', clientesSin, '', 'normal')}
      ${kpiCard('Órdenes PO', ordenesPO, '', 'normal')}
      ${kpiCard('Críticos afectados', critAfect, `Total críticos: ${critTotal}`, kpiLevel(critAfect))}
      ${kpiCard('Electrodependientes afectados', edAfect, `Total Electrodependientes: ${edTotal}`, kpiLevel(edAfect))}
    </div>
  `;

  // Top 10 comunas
  const topRows = (top10 || []).map(r => ({
    COMUNA: r.COMUNA ?? r.comuna ?? '',
    CLIENTES: r.CLIENTES ?? r.clientes ?? r.total ?? 0
  }));

  // Tabla clientes: columnas clave
  const colsClientes = (() => {
    if (!clientes.length) return [];
    const preferred = ['CUSTOMER_ACCOUNT','NIS','NAME','NOMBRE','CITY','COMUNA','LOCATION_DESC','DIRECCION','POWER_STATUS','STATUS','ORDER_ID','DATE_OF_ACTION'];
    const have = new Set(Object.keys(clientes[0]));
    const cols = preferred.filter(c => have.has(c));
    return cols.length ? cols : Object.keys(clientes[0]).slice(0, 10);
  })();

  // ===== Histórico 24h (con gráfico) =====
  const histHtml = `
    <div class="card">
      <div style="font-weight:1000; margin-bottom:8px">Histórico 24h</div>
      ${
        histMissing
          ? `<div class="muted">Hist_24horas.csv no disponible (opcional).</div>`
          : `<div class="chart-box" style="height:280px">
               <canvas id="histChart"></canvas>
               <div id="histChartFallback" style="margin-top:8px"></div>
             </div>
             <div class="muted" style="margin-top:8px">Cargado: ${Array.isArray(hist24) ? hist24.length : 0} filas.</div>`
      }
    </div>
  `;

  $('#content').innerHTML = `
    ${kpiHtml}

    <div class="grid2" style="margin-top:12px">
      <div class="card">
        <div style="font-weight:1000; margin-bottom:8px">Top 10 comunas</div>
        ${topRows.length ? table(topRows, ['COMUNA','CLIENTES']) : `<div class="muted">Sin datos de comunas.</div>`}
      </div>

      ${histHtml}
    </div>

    <div class="card" style="margin-top:12px">
      <div style="font-weight:1000; margin-bottom:8px">Clientes sin suministro (${clientes.length})</div>
      ${
        clientes.length
          ? table(clientes.slice(0, 200), colsClientes)
          : `<div class="muted">Sin clientes afectados.</div>`
      }
      <div class="muted" style="margin-top:10px">Mostrando máx 200 por rendimiento.</div>
    </div>
  `;

  // Re-inyecta alertas inline arriba (porque #content se reescribió)
  renderAlertsInline(alerts);

  // Dibujar gráfico 24h (si existe)
  if (!histMissing && Array.isArray(hist24) && hist24.length){
    await ensureChartJs();
    drawHist24(hist24);
  }
}

function ensureChartJs(){
  return new Promise((resolve)=>{
    if (window.Chart) return resolve();
    const s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/chart.js';
    s.onload = resolve;
    document.head.appendChild(s);
  });
}

function drawComunasBar(rows){
  // rows: [{COMUNA, CLIENTES}]
  if (!rows || !rows.length) return;

  if (!window.Chart){
    // Fallback: si no está Chart, no rompemos la vista
    const el = document.getElementById('comChartFallback');
    if (el) el.innerHTML = `<div class="muted">Chart.js no está disponible. (Si estás offline, hay que servirlo local.)</div>`;
    return;
  }

  const labels = rows.map(r => r.COMUNA);
  const data = rows.map(r => Number(r.CLIENTES)||0);

  const canvas = document.getElementById('comChart');
  if (!canvas) return;

  // destruir si ya existía
  if (state._comChartInstance){
    try{ state._comChartInstance.destroy(); }catch(_){}
    state._comChartInstance = null;
  }

  state._comChartInstance = new Chart(canvas, {
    type: 'bar',
    data: {
      labels,
      datasets: [{ label: 'Clientes', data }]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { display:false } },
      scales: {
        x: { ticks: { maxRotation: 0, autoSkip: true } },
        y: { beginAtZero: true }
      }
    }
  });
}

function drawHist24(hist){
  if (!Array.isArray(hist) || !hist.length) return;

  const canvas = document.getElementById('histChart');
  if (!canvas) return;

  if (!window.Chart) {
    const fb = document.getElementById('histChartFallback');
    if (fb) fb.innerHTML = `<div class="muted">Chart.js no está disponible (en institucional debe ir local).</div>`;
    return;
  }

  // destruir si ya existe
  if (state._histChartInstance){
    try{ state._histChartInstance.destroy(); }catch(_){}
    state._histChartInstance = null;
  }

  // Detecta nombres de columnas típicos
  const pick = (row, keys) => {
    for (const k of keys) if (row && row[k] != null && row[k] !== '') return row[k];
    return null;
  };

  const labels = hist.map(r => String(pick(r, ['timestamp','TS','fecha','FECHA','date','DATE']) ?? ''));

  const series = (name, keys) => {
    const arr = hist.map(r => Number(pick(r, keys) ?? 0) || 0);
    // no incluir si está todo en 0
    const sum = arr.reduce((a,b)=>a+b,0);
    return sum ? { label:name, data:arr } : null;
  };

  const ds = [
    series('Clientes sin suministro', ['clientes_sin_suministro','clientes','CLIENTES','sin_suministro','SIN_SUMINISTRO']),
    series('Órdenes PO', ['ordenes_po','po','PO','ordenes','ORDENES_PO']),
    series('Críticos afectados', ['criticos_afectados','criticos','CRITICOS']),
    series('ED afectados', ['ed_afectados','ed','ED'])
  ].filter(Boolean);

  state._histChartInstance = new Chart(canvas, {
    type: 'line',
    data: { labels, datasets: ds },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: { legend: { position:'bottom' } },
      scales: {
        x: { ticks: { autoSkip: true, maxRotation: 0 } },
        y: { beginAtZero: true }
      },
      elements: { point: { radius: 2 } }
    }
  });
}

function drawChart(rows){
  if (!rows || !rows.length) return;
  const cols = Object.keys(rows[0]||{});
  const timeCandidates = ['timestamp','time','fecha','datetime','hora','FECHA','HORA','DATE','DATETIME'];
  const valueCandidates = ['clientes','sin_suministro','cantidad','total','value','VALOR','CLIENTES','TOTAL'];

  const findCol = (cands) => {
    const lower = cols.map(c=>c.toLowerCase());
    for (const cand of cands){
      const i = lower.indexOf(String(cand).toLowerCase());
      if (i>=0) return cols[i];
    }
    return null;
  };

  const tcol = findCol(timeCandidates) || cols[0];
  let vcol = findCol(valueCandidates);
  if (!vcol){
    for (const c of cols){
      const v = rows[0][c];
      if (v !== null && v !== '' && !isNaN(Number(String(v).replace(',','.')))){ vcol = c; break; }
    }
  }
  if (!tcol || !vcol) return;

  const labels = rows.map(r=>r[tcol]);
  const data = rows.map(r=>Number(String(r[vcol]??'').replace(',','.'))||0);

  const ctx = document.getElementById('chart24');
  new Chart(ctx, {
    type:'line',
    data:{labels, datasets:[{label:vcol, data, tension:0.25, fill:true}]},
    options:{responsive:true, maintainAspectRatio:false}
  });
}

async function loadComunas(){
  $('#content').innerHTML = `<div class="card"><b>Cargando Comunas…</b><div class="muted">Consultando resumen por comuna.</div></div>`;

  const data = await api('comunas');
  const rows = data?.rows || data?.comunas || (Array.isArray(data) ? data : []);

  const tableRows = (rows || []).map(r => ({
    COMUNA: (r.COMUNA ?? r.comuna ?? r.CITY ?? r.city ?? '').toString().toUpperCase(),
    CLIENTES: Number(r.CLIENTES ?? r.clientes ?? r.total ?? 0) || 0
  })).filter(r => r.COMUNA);

  tableRows.sort((a,b)=>b.CLIENTES - a.CLIENTES);

  const top10 = tableRows.slice(0, 10);
  const donutSpec = donutSpecFromRows(tableRows);

  const totalClientes = tableRows.reduce((s,r)=>s + (Number(r.CLIENTES)||0), 0);
  const top1 = tableRows[0] || null;
  const top2 = tableRows[1] || null;
  const top3 = tableRows[2] || null;

  // Donut + leyenda + resumen ABAJO (centrado)
  const donutBlock = (() => {
    if (!donutSpec) return `<div class="muted">Sin datos para gráfico.</div>`;

    const bg = donutSpec.segs
      .map(s => `${s.color} ${s.start.toFixed(2)}% ${s.end.toFixed(2)}%`)
      .join(', ');

    const legend = donutSpec.segs.map(s => `
      <div class="donut-legend-item">
        <span class="donut-dot" style="background:${s.color}"></span>
        <span class="donut-name">${escapeHtml(s.label)}</span>
        <span class="donut-val">${s.value} (${s.pct.toFixed(0)}%)</span>
      </div>
    `).join('');

    return `
      <div style="display:flex; flex-direction:column; align-items:center; gap:12px">
        <div class="donut" style="
          background:conic-gradient(${bg});
          width:240px; height:240px;
          position:relative; border-radius:50%;
          box-shadow: var(--shadow-sm);
          margin:0 auto;
        ">
          <div style="position:absolute; inset:28px; border-radius:50%; background:#fff; box-shadow: inset 0 0 0 1px rgba(0,0,0,.06);"></div>
        </div>

        <div style="display:grid; gap:8px; width:100%; max-width:520px">
          <div class="donut-legend" style="min-width:auto">${legend}</div>

          <div class="muted" style="line-height:1.35">
            Total clientes sin suministro: <b>${totalClientes}</b><br>
            #1 <b>${escapeHtml(top1?.COMUNA || '—')}</b> (${top1?.CLIENTES ?? 0}) ·
            #2 <b>${escapeHtml(top2?.COMUNA || '—')}</b> (${top2?.CLIENTES ?? 0}) ·
            #3 <b>${escapeHtml(top3?.COMUNA || '—')}</b> (${top3?.CLIENTES ?? 0})
          </div>
        </div>
      </div>
    `;
  })();

  $('#content').innerHTML = `
    <div class="grid2" style="margin-top:0; grid-template-columns: 1fr 1fr; align-items:start">
      <!-- IZQUIERDA: DONUT centrado -->
      <div class="card">
        <div style="font-weight:1000; margin-bottom:8px">Distribución (Top 3 + Otros)</div>
        ${donutBlock}
      </div>

      <!-- DERECHA: BARRAS + BOTÓN DETALLE -->
      <div class="card">
        <div style="display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:8px">
          <div style="font-weight:1000">Top 10 comunas (barras)</div>
          <button class="btn small" id="btnToggleComTable" title="Ver resumen por comuna" style="padding:6px 10px">
            Detalle
          </button>
        </div>

        <div class="chart-box" style="height:320px">
          <canvas id="comChart"></canvas>
          <div id="comChartFallback" style="margin-top:6px"></div>
        </div>

        <div class="muted small" style="margin-top:8px">
          Tip: barras sirven para comparar; donut para “peso relativo”.
        </div>
      </div>
    </div>

    <!-- TABLA (colapsable) -->
    <div class="card" id="comTableCard" style="margin-top:12px; display:none">
      <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
        <div style="font-weight:1000">Resumen por comuna</div>
        <button class="btn small" id="btnHideComTable">Ocultar</button>
      </div>
      <div style="margin-top:10px">
        ${table(tableRows, ['COMUNA','CLIENTES'])}
      </div>
    </div>
  `;

  // Toggle tabla
  const btn = document.getElementById('btnToggleComTable');
  const card = document.getElementById('comTableCard');
  const btnHide = document.getElementById('btnHideComTable');

  if (btn && card) {
    btn.addEventListener('click', () => {
      card.style.display = 'block';
      card.scrollIntoView({ behavior:'smooth', block:'start' });
    });
  }
  if (btnHide && card) {
    btnHide.addEventListener('click', () => {
      card.style.display = 'none';
    });
  }

  // Barras
  await ensureChartJs();
  drawComunasBar(top10);
}

async function loadCriticos(){
  $('#content').innerHTML = `<div class="card"><b>Cargando Críticos…</b><div class="muted">Consultando cruce por NIS.</div></div>`;

  const data = await api('criticos');
  // tu data.php actual: { total, afectados }
  const rows = data?.afectados || data?.rows || (Array.isArray(data) ? data : []);
  const total = Number(data?.total ?? 0);

  if (!rows.length){
    $('#content').innerHTML = `
      <div class="card">
        <div style="font-weight:1000">Críticos</div>
        <div class="muted">Sin críticos afectados. Total en base: ${total}</div>
      </div>
    `;
    return;
  }

  const cols = (() => {
    const have = new Set(Object.keys(rows[0] || {}));
    const preferred = [
      'CUSTOMER_ACCOUNT','NIS',
      'NAME','NOMBRE',
      'CITY','COMUNA',
      'LOCATION_DESC','DIRECCION',
      'POWER_STATUS','STATUS',
      'ORDER_ID','DATE_OF_ACTION'
    ];
    const picked = preferred.filter(c => have.has(c));
    return picked.length ? picked : Object.keys(rows[0]).slice(0, 10);
  })();

  $('#content').innerHTML = `
    <div class="card">
      <div style="font-weight:1000; margin-bottom:6px">Críticos afectados (${rows.length})</div>
      <div class="muted" style="margin-bottom:10px">Total críticos en base: ${total}</div>
      ${table(rows.slice(0, 300), cols)}
      <div class="muted" style="margin-top:10px">Mostrando máx 300 por rendimiento.</div>
    </div>
  `;
}

async function loadED(){
  $('#content').innerHTML = `<div class="card"><b>Cargando ED…</b><div class="muted">Consultando cruce por NIS.</div></div>`;

  const data = await api('ed');
  // tu data.php actual: { total, afectados }
  const rows = data?.afectados || data?.rows || (Array.isArray(data) ? data : []);
  const total = Number(data?.total ?? 0);

  if (!rows.length){
    $('#content').innerHTML = `
      <div class="card">
        <div style="font-weight:1000">Electrodependientes</div>
        <div class="muted">Sin ED afectados. Total en base: ${total}</div>
      </div>
    `;
    return;
  }

  const cols = (() => {
    const have = new Set(Object.keys(rows[0] || {}));
    const preferred = [
      'CUSTOMER_ACCOUNT','NIS',
      'NAME','NOMBRE',
      'CITY','COMUNA',
      'LOCATION_DESC','DIRECCION',
      'POWER_STATUS','STATUS',
      'ORDER_ID','DATE_OF_ACTION'
    ];
    const picked = preferred.filter(c => have.has(c));
    return picked.length ? picked : Object.keys(rows[0]).slice(0, 10);
  })();

  $('#content').innerHTML = `
    <div class="card">
      <div style="font-weight:1000; margin-bottom:6px">ED afectados (${rows.length})</div>
      <div class="muted" style="margin-bottom:10px">Total ED en base: ${total}</div>
      ${table(rows.slice(0, 300), cols)}
      <div class="muted" style="margin-top:10px">Mostrando máx 300 por rendimiento.</div>
    </div>
  `;
}

async function loadMapa(){
  setActive('mapa');

  // Si ya existe mapa, solo asegurar tamaño y salir
  if (state.map && state.map._leaflet_id) {
    setTimeout(() => state.map.invalidateSize(true), 80);
    return;
  }

  $('#content').innerHTML = `
    <div class="card">
      <div style="font-weight:1000; margin-bottom:6px">Mapa</div>
      <div style="display:flex;gap:10px;align-items:center;margin-bottom:6px">
        <select id="kmlSelector">
          <option value="redes.kml">Red completa</option>
          <option value="puntos.kml">Puntos prueba</option>
        </select>

        <span class="muted" id="mapStatus">Cargando librerías…</span>
      </div>
      <div style="position:relative">
  <div id="map" style="height:70vh; border-radius:16px; margin-top:10px; overflow:hidden;"></div>
    <div id="mapLegend" class="mapLegend">
      <div class="legendTitle">Estado red</div>

      <div class="legendItem">
        <span class="dot green"></span>
        Energizado
      </div>

      <div class="legendItem">
        <span class="dot yellow"></span>
        Alerta
      </div>

      <div class="legendItem">
        <span class="dot red"></span>
        Crítico
      </div>

    </div>

  </div>
    </div>
  `;

  const statusEl = document.getElementById('mapStatus');

  // 0) librerías
  try{
    await ensureLeaflet();
  }catch(e){
    statusEl.textContent = `No se pudieron cargar librerías del mapa: ${e.message}`;
    return;
  }

  // 1) datos livianos
  statusEl.textContent = `Cargando datos del mapa…`;

  let mapInfo = null;
  try{
    mapInfo = await api('map');
  }catch(e){
    statusEl.textContent = `Error: ${e.message}`;
    return;
  }

  // 2) init Leaflet
  const el = document.getElementById('map');
 const map = L.map(el, {
    preferCanvas:true,
    zoomControl:true,
    attributionControl:false
  }).setView([-36.6, -71.9], 8);
  state.map = map;

  // 3) mejor mapa base disponible
  statusEl.textContent = `Cargando mapa base…`;
  const base = await addBestEffortBaseLayer(map, statusEl);
  state.baseLayer = base?.layer || null;
  state.baseLayerMode = base?.mode || 'none';

  // 4) grupo de puntos desde ya
  const pointsLayer = L.featureGroup().addTo(map);
  state.pointsLayer = pointsLayer;

  // 5) cargar KML
  statusEl.textContent = `Cargando redes (KML)…`;

  try{
    const kmlUrl = mapInfo?.kml_url || 'api/kml.php';
    const layer = omnivore.kml(kmlUrl);
    const t0 = performance.now();

    layer.on('ready', async () => {
      const ms = Math.round(performance.now() - t0);

      try{
        applyOperationalStyle(layer, mapInfo?.afectadas || {});
      } catch (e){
        console.warn('No pude aplicar estilos KML manuales:', e);
      }

      try{
        const bounds = layer.getBounds();
        if (bounds && bounds.isValid()) {
          map.fitBounds(bounds, { padding:[20,20] });
        }
      }catch(_){}

      statusEl.textContent =
        state.baseLayerMode === 'none'
          ? `KML cargado (${ms} ms) · sin mapa base`
          : `KML cargado (${ms} ms)`;
    });

    layer.on('error', () => {
      statusEl.textContent = `No se pudo cargar el KML`;
    });

    layer.addTo(map);
    state.kmlLayer = layer;

  } catch (e){
    statusEl.textContent = `Error KML: ${e.message}`;
  }

  // 6) puntos dinámicos del backend
  const puntos = mapInfo?.puntos || [];
  if (Array.isArray(puntos) && puntos.length){
    renderPoints(pointsLayer, puntos);
  }

  setTimeout(() => map.invalidateSize(true), 80);

  const selector = document.getElementById("kmlSelector");

  selector.addEventListener("change", ()=>{

    if(state.kmlLayer){
      state.map.removeLayer(state.kmlLayer);
    }

    const file = selector.value;

    const layer = omnivore.kml(`api/kml.php?file=${file}`);

    layer.on('ready', ()=>{
        applyOperationalStyle(layer, mapInfo?.afectadas || {});
    });

    layer.addTo(state.map);
    state.kmlLayer = layer;

  });
}

function applyOperationalStyle(layer, estados){

  if (!estados) estados = {};

  const estadoColor = {
    energizado: "#00A651",
    alerta: "#FFC107",
    critico: "#E53935"
  };

  layer.eachLayer(l=>{

    if (!l.feature) return;
    if (!l.setStyle) return;

    const name =
      (l.feature.properties?.name ||
       l.feature.properties?.Name ||
       "").toUpperCase();

    let estado = "energizado";

    for (const circuito in estados){
      if (name.includes(circuito.toUpperCase())){
        estado = estados[circuito];
        break;
      }
    }

    const color = estadoColor[estado] || "#00A651";

    l.setStyle({
      color: color,
      weight: 3,
      opacity: 1
    });

  });

}

function styleKmlLayer(){
  return;
}
function fileExists(url){
  return fetch(url, { method:'HEAD', cache:'no-store' })
    .then(r => r.ok)
    .catch(() => false);
}

async function addBestEffortBaseLayer(map, statusEl){
  // Orden:
  // 1) tiles locales (institucional/offline)
  // 2) satélite online (solo dev si hay internet)
  // 3) fondo neutro si todo falla

  const localTileUrl = 'assets/tiles/{z}/{x}/{y}.png';
  const esriSatUrl = 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}';

  // 1) intentar tiles locales
  const hasLocalTiles = await fileExists('assets/tiles/0/0/0.png');

  if (hasLocalTiles){
    const local = L.tileLayer(localTileUrl, {
      maxZoom: 18,
      attribution: 'Tiles locales'
    });

    local.addTo(map);
    if (statusEl) statusEl.textContent = 'Mapa base local cargado';
    return { mode:'local', layer: local };
  }

  // 2) intentar satélite online (solo si hay internet)
  try{
    const online = L.tileLayer(esriSatUrl, {
      maxZoom: 18,
      attribution: 'Esri World Imagery'
    });

    await new Promise((resolve, reject) => {
      let done = false;

      const ok = () => {
        if (done) return;
        done = true;
        cleanup();
        resolve();
      };

      const fail = () => {
        if (done) return;
        done = true;
        cleanup();
        reject(new Error('No se pudo cargar mapa base online'));
      };

      const cleanup = () => {
        online.off('load', ok);
        online.off('tileerror', fail);
      };

      online.once('load', ok);
      online.once('tileerror', fail);
      online.addTo(map);

      // timeout defensivo
      setTimeout(() => {
        if (!done) fail();
      }, 5000);
    });

    if (statusEl) statusEl.textContent = 'Mapa base satelital cargado';
    return { mode:'online', layer: online };
  } catch (_) {
    // 3) fallback neutro
    const bg = L.rectangle([[-90,-180],[90,180]], {
      stroke:false,
      fill:true,
      fillOpacity:0.04
    });
    bg.addTo(map);

    if (statusEl) {
      statusEl.textContent = 'Sin mapa base (offline o bloqueado). Mostrando fondo neutro.';
    }

    return { mode:'none', layer: bg };
  }
}

function renderPoints(pointsLayer, puntos){
  if (!pointsLayer) return;

  pointsLayer.clearLayers();

  (puntos || []).slice(0, 1500).forEach(pt=>{
    if (!isFinite(pt.lat) || !isFinite(pt.lon)) return;
    if (Math.abs(pt.lat) > 90 || Math.abs(pt.lon) > 180) return;

    L.circleMarker([pt.lat, pt.lon], {
      radius: 5,
      weight: 2,
      color: '#004B8D',
      fillColor: '#FFB81C',
      fillOpacity: 0.85
    })
    .bindPopup(`
      <b>${escapeHtml(pt.nis || 'SIN NIS')}</b><br>
      ${escapeHtml(pt.comuna || 'SIN COMUNA')}<br>
      ${escapeHtml(pt.status || 'SIN ESTADO')}
    `)
    .addTo(pointsLayer);
  });
}

async function refreshMapaDynamic(){
  if (!state.map) return;

  const meta = await api('meta');
  const kmlMtime = meta?.meta?.KML?.mtime || null;

  // Si el KML cambió en disco, recargar mapa completo
  if (state.map.lastKmlMtime && kmlMtime && state.map.lastKmlMtime !== kmlMtime){
    if (state.map && state.map.remove) {
      state.map.remove();
    }
    state.map = null;
    state.kmlLayer = null;
    state.pointsLayer = null;
    state.baseLayer = null;
    return loadMapa();
  }

  const data = await api('map');
  const alerts = computeAlerts(meta.meta, state.general ? state.general.kpis : null);
  updateAlertsUI(alerts);
  renderAlertsInline(alerts);

  // NO tocar estilos del KML
  // styleKmlLayer(state.map.kmlLayer, data.afectadas);

  renderPoints(state.pointsLayer, data.puntos);
  state.map.lastKmlMtime = kmlMtime;
}

function ensureLeaflet(){
  return new Promise((resolve, reject) => {
    // si ya está, listo
    if (window.L && window.omnivore) return resolve();

    const loadCSS = (href) => new Promise((res, rej) => {
      // evita duplicados
      if ([...document.styleSheets].some(s => (s.href||'').includes(href))) return res();
      const link = document.createElement('link');
      link.rel = 'stylesheet';
      link.href = href;
      link.onload = () => res();
      link.onerror = () => rej(new Error(`No pude cargar CSS: ${href}`));
      document.head.appendChild(link);
    });

    const loadJS = (src) => new Promise((res, rej) => {
      // evita duplicados
      if ([...document.scripts].some(s => (s.src||'').includes(src))) return res();
      const s = document.createElement('script');
      s.src = src;
      s.defer = true;
      s.onload = () => res();
      s.onerror = () => rej(new Error(`No pude cargar JS: ${src}`));
      document.head.appendChild(s);
    });

    // 1) LOCAL FIRST (lo que corresponde al ambiente institucional)
    const localCSS = 'assets/vendor/leaflet/leaflet.css';
    const localLeaflet = 'assets/vendor/leaflet/leaflet.js';
    const localOmnivore = 'assets/vendor/leaflet-omnivore.min.js';

    // 2) CDN fallback (solo si lo local no existe; útil en DEV)
    const cdnCSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
    const cdnLeaflet = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
    const cdnOmnivore = 'https://unpkg.com/leaflet-omnivore@0.3.4/leaflet-omnivore.min.js';

    (async () => {
      try{
        // intenta local
        await loadCSS(localCSS);
        await loadJS(localLeaflet);
        await loadJS(localOmnivore);

        if (window.L && window.omnivore) return resolve();

        // si por alguna razón no quedó, forzamos fallback
        throw new Error('Leaflet local no dejó window.L / window.omnivore');
      } catch (eLocal){
        // fallback CDN (DEV)
        try{
          await loadCSS(cdnCSS);
          await loadJS(cdnLeaflet);
          await loadJS(cdnOmnivore);

          if (window.L && window.omnivore) return resolve();
          throw new Error('Leaflet CDN no dejó window.L / window.omnivore');
        } catch (eCdn){
          reject(new Error(
            `Leaflet/Omnivore no disponible. ` +
            `Local: ${eLocal.message}. ` +
            `CDN: ${eCdn.message}.`
          ));
        }
      }
    })();
  });
}

function kmlColorToHex(kmlColor){
  // KML usa AABBGGRR
  if (!kmlColor || typeof kmlColor !== 'string') return null;
  const s = kmlColor.trim().replace('#','').toLowerCase();
  if (s.length !== 8) return null;

  const aa = s.slice(0,2);
  const bb = s.slice(2,4);
  const gg = s.slice(4,6);
  const rr = s.slice(6,8);

  return {
    color: `#${rr}${gg}${bb}`,
    opacity: parseInt(aa, 16) / 255
  };
}

async function fetchKmlStyleSequence(kmlUrl){
  const res = await fetch(kmlUrl, { cache: 'no-store' });
  if (!res.ok) throw new Error(`No pude leer KML crudo (${res.status})`);

  const xmlText = await res.text();
  const xml = new DOMParser().parseFromString(xmlText, 'text/xml');

  // 1) Style id -> { color, opacity, width }
  const styleById = {};
  xml.querySelectorAll('Style[id]').forEach(styleEl => {
    const id = styleEl.getAttribute('id');
    if (!id) return;

    const lineStyle = styleEl.querySelector('LineStyle');
    if (!lineStyle) return;

    const colorText = lineStyle.querySelector('color')?.textContent?.trim() || '';
    const widthText = lineStyle.querySelector('width')?.textContent?.trim() || '';

    const parsed = kmlColorToHex(colorText);
    styleById[id] = {
      color: parsed?.color || '#3388ff',
      opacity: parsed?.opacity ?? 1,
      weight: Number(widthText) || 2
    };
  });

  // 2) StyleMap id -> style normal
  const styleMapNormalById = {};
  xml.querySelectorAll('StyleMap[id]').forEach(smEl => {
    const id = smEl.getAttribute('id');
    if (!id) return;

    let normalStyleUrl = null;
    smEl.querySelectorAll('Pair').forEach(pair => {
      const key = pair.querySelector('key')?.textContent?.trim();
      const styleUrl = pair.querySelector('styleUrl')?.textContent?.trim();
      if (key === 'normal' && styleUrl) {
        normalStyleUrl = styleUrl.replace(/^#/, '');
      }
    });

    if (normalStyleUrl) {
      styleMapNormalById[id] = normalStyleUrl;
    }
  });

  // 3) Secuencia de estilos de placemarks en orden de documento
  const seq = [];
  xml.querySelectorAll('Placemark').forEach(pm => {
    const styleUrlRaw = pm.querySelector('styleUrl')?.textContent?.trim() || '';
    const styleRef = styleUrlRaw.replace(/^#/, '');

    let finalStyleId = styleRef;

    // si apunta a StyleMap, resolver al style "normal"
    if (styleMapNormalById[styleRef]) {
      finalStyleId = styleMapNormalById[styleRef];
    }

    seq.push(styleById[finalStyleId] || null);
  });

  return seq;
}

function applyStyleSequenceToKmlLayer(kmlLayer, styleSeq){
  if (!kmlLayer || !Array.isArray(styleSeq)) return;

  let idx = 0;

  kmlLayer.eachLayer(layer => {
    const style = styleSeq[idx++] || null;
    if (!style || !layer.setStyle) return;

    layer.setStyle({
      color: style.color || '#3388ff',
      opacity: style.opacity ?? 1,
      weight: style.weight || 2
    });
  });
}

async function loadWindy(){
  const meta = await api('meta');
  renderMeta(meta.meta);
  const alerts = computeAlerts(meta.meta, state.general ? state.general.kpis : null);
  updateAlertsUI(alerts);
  $('#content').innerHTML = `
    <div class="card" style="padding:0; overflow:hidden">
      <iframe
        style="width:100%; height:74vh; border:0"
        src="https://embed.windy.com/embed.html?type=map&location=coordinates&metricRain=mm&metricTemp=%C2%B0C&metricWind=km/h&zoom=9&overlay=wind&product=ecmwf&level=surface&lat=-36.275&lon=-71.782&detailLat=-36.076&detailLon=-71.782&detail=true&pressure=true&message=true">
      </iframe>
    </div>
  `;
  renderAlertsInline(alerts);
}

async function loadView(view){
  try{
    state.view = view;
    setActive(view);

    // Al salir del mapa, liberamos estado (evita leaks)
    if (view !== 'mapa') state.map = null;

    if (view === 'general') return await loadGeneral();
    if (view === 'comunas') return await loadComunas();
    if (view === 'mapa')    return await loadMapa();
    if (view === 'criticos')return await loadCriticos();
    if (view === 'ed')      return await loadED();
    if (view === 'windy')   return await loadWindy();
  } catch (e){
    showViewError(`Error cargando vista: ${view}`, e);
  }
}

async function refreshTicker(){
  const t = await api('ticker');
  state.ticker = t;

  const items = (t.last3||[]).map(x=>`<span>⚠ ${escapeHtml(x.comuna)}: <b>${escapeHtml(x.clientes)}</b> clientes</span>`).join(' <span style="opacity:.25">|</span> ');
  const ed = `<span>ED afectados: <b>${escapeHtml(t.ed_afectados)}</b></span>`;
  // duplicamos contenido para que “marquee” se vea continuo
  const html = `${items} <span style="opacity:.25">|</span> ${ed} <span style="opacity:.25">|</span> ${escapeHtml(t.ts)}`;
  $('#tickerMarquee').innerHTML = html + ' &nbsp;&nbsp;&nbsp;&nbsp; ' + html;
}

function startAutoRefresh(sec){
  if (state.timer) clearInterval(state.timer);
  state.timer = setInterval(async ()=>{
    await refreshTicker();

    // Evita recargar el KML pesado en cada refresh
    if (state.view === 'mapa') {
      await refreshMapaDynamic();
      return;
    }

    // refresca la vista actual
    await loadView(state.view);
  }, sec*1000);
}

(async function init(){
  // menú
  document.querySelectorAll('.nav button[data-view]').forEach(b=>{
    b.addEventListener('click', ()=>loadView(b.dataset.view));
  });

  await refreshTicker();

  const meta = await api('meta');
  startAutoRefresh(meta.refresh || 30);

  await loadView('general');
})();
