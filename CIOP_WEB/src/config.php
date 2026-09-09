<?php
// src/config.php
//
// Funciona en los dos entornos sin tocar nada:
//   - XAMPP en Windows : usa la ruta local de más abajo.
//   - Docker           : toma CIOP_DATA_DIR del docker-compose.yml.

$dataDir = getenv('CIOP_DATA_DIR');
if ($dataDir === false || $dataDir === '') {
  // Entorno local (XAMPP). Ajusta según el PC.
  $dataDir = 'C:\\Users\\KATO\\Documents\\GitHub\\proyecto_titulo\\CIOP_DATA';
}

$refresh = getenv('CIOP_REFRESH_SEC');
$refresh = ($refresh !== false && $refresh !== '') ? (int)$refresh : 20;

return [
  'DATA_DIR'     => $dataDir,

  'REFRESH_SEC'  => $refresh,
  'STALE_MIN'    => 10,

  'FILES' => [
    'PO'       => 'CIOP-PO_TEST.txt',
    'CLIENTES' => 'CIOP-PO_clientes_TEST.txt',
    'HIST24'   => 'Hist_24horas.csv',
    'KML'      => 'redes.kml',

    // Se usan las versiones anonimizadas. Los archivos reales no deben
    // exhibirse: el cliente lo prohibió expresamente.
    'CRITICOS' => 'CIOP-PO_criticos_TEST.txt',
    'ED'       => 'CIOP-PO_ED_TEST.txt',
  ],
];