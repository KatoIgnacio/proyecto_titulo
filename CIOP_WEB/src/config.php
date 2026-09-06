<?php
// C:\xampp\htdocs\CIOP_WEB\src\config.php

return [
  // Ruta de datos EN DISCO (no en el pendrive). Ajusta según el PC destino.
  // DEV actual (según lo que indicaste): C:\xampp\htdocs\CIOP_DATA
  // Servidor institucional (ejemplo): \\SERVIDOR\RUTA\CIOP-PO  o  C:\CIOP_DATA\CIOP-PO
  'DATA_DIR' => 'C:\\Users\\KATO\\Desktop\\TESIS\\xampp\\htdocs\\CIOP_DATA',

  'REFRESH_SEC'  => 20,
  'STALE_MIN'    => 10,

  // Nombres de archivos dentro de DATA_DIR
  'FILES' => [
    'PO'       => 'CIOP-PO.txt',
    'CLIENTES' => 'CIOP-PO_clientes.txt',
    //'CRITICOS' => 'CIOP-PO_criticos.txt',
    //'ED'       => 'CIOP-PO_ED.txt',
    'HIST24'   => 'Hist_24horas.csv',
    'KML'      => 'redes.kml',
    'CRITICOS' => 'CIOP-PO_criticos_TEST.txt',
    'ED'       => 'CIOP-PO_ED_TEST.txt',
  ],
];