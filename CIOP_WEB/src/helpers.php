<?php
declare(strict_types=1);

// Evita "Cannot redeclare".
if (!function_exists('h')) {
  function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
  }
}

function normalize_comuna(string $s): string {
  $s = trim($s);
  if ($s === '') return '';

  // Normaliza a ASCII sin depender de mbstring.
  $map = [
    'á'=>'a','à'=>'a','ä'=>'a','â'=>'a','Á'=>'A','À'=>'A','Ä'=>'A','Â'=>'A',
    'é'=>'e','è'=>'e','ë'=>'e','ê'=>'e','É'=>'E','È'=>'E','Ë'=>'E','Ê'=>'E',
    'í'=>'i','ì'=>'i','ï'=>'i','î'=>'i','Í'=>'I','Ì'=>'I','Ï'=>'I','Î'=>'I',
    'ó'=>'o','ò'=>'o','ö'=>'o','ô'=>'o','Ó'=>'O','Ò'=>'O','Ö'=>'O','Ô'=>'O',
    'ú'=>'u','ù'=>'u','ü'=>'u','û'=>'u','Ú'=>'U','Ù'=>'U','Ü'=>'U','Û'=>'U',
    'ñ'=>'N','Ñ'=>'N',
  ];
  $s = strtr($s, $map);
  $s = strtoupper($s);
  $s = preg_replace('/\s+/', ' ', $s);
  return $s;
}

function nis_digits(string $nis): string {
  $d = preg_replace('/\D+/', '', $nis);
  return $d ?? '';
}

function get_nis_from_cliente(array $row): string {
  // En CIOP-PO_clientes.txt el identificador que cruza con CC/ED suele ser CUSTOMER_ACCOUNT (ej: 032-123456)
  // pero dejamos fallback por compatibilidad.
  $cand = $row['CUSTOMER_ACCOUNT']
    ?? ($row['NIS'] ?? ($row['nis'] ?? ''));
  return trim((string)$cand);
}

function is_outage_cliente(array $row): bool {
  // Heurística robusta: si viene con ORDER_ID o cualquier status/power_status.
  $order  = trim((string)($row['ORDER_ID'] ?? ''));
  $status = strtolower(trim((string)($row['STATUS'] ?? '')));
  $pwr    = strtolower(trim((string)($row['POWER_STATUS'] ?? '')));

  if ($order !== '') return true;
  if ($status !== '' || $pwr !== '') return true;
  return false;
}

function safe_read_file(string $path): string {
  // Minimiza lecturas "a medio escribir": si el archivo cambia durante la lectura, devuelve "".
  clearstatcache(true, $path);
  $s1 = @filesize($path);
  $m1 = @filemtime($path);

  $data = @file_get_contents($path);
  if ($data === false) return '';

  clearstatcache(true, $path);
  $s2 = @filesize($path);
  $m2 = @filemtime($path);

  if ($s1 !== false && $s2 !== false && ($s1 !== $s2 || $m1 !== $m2)) return '';
  return $data;
}

function parse_dt(?string $s): ?int {
  $s = trim((string)$s);
  if ($s === '') return null;
  $t = strtotime($s);
  return $t === false ? null : $t;
}