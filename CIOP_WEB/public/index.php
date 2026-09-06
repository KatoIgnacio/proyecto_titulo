<?php ?><!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>CIOP Central</title>

  <link rel="stylesheet" href="assets/style.css">

  <!-- Leaflet (LOCAL) -->
  <link rel="stylesheet" href="assets/vendor/leaflet/leaflet.css">
</head>
<body>
  <header class="topbar">
    <a class="logo" href="#" data-view="general" aria-label="Ir a General">
      <img src="assets/images/logo.png" alt="CIOP Central">
    </a>

    <nav class="nav" aria-label="Navegación">
      <div class="nav-row nav-row--main">
        <button data-view="general" class="active">General</button>
        <button data-view="comunas">Comunas</button>
        <button data-view="criticos">Críticos</button>
        <button data-view="ed">Electrodependientes</button>
      </div>

      <div class="nav-row nav-row--secondary">
        <button data-view="mapa">Mapa</button>
        <button data-view="windy">Pronóstico Viento</button>
      </div>
    </nav>

    <div id="meta" class="meta"></div>
  </header>

  <main class="wrap">
    <div id="content"></div>
  </main>

  <div class="ticker" role="status" aria-live="polite">
    <div class="label">Estado</div>
    <div class="marquee" id="tickerMarquee"></div>
  </div>

  <!-- Leaflet + Omnivore (LOCAL) -->
  <script src="assets/vendor/leaflet/leaflet.js" defer></script>
  <script src="assets/vendor/leaflet-omnivore.min.js" defer></script>

  <!-- App -->
  <script src="assets/app.js" defer></script>
</body>
</html>