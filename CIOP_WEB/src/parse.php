<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';

if (!function_exists('ciop_to_utf8')) {
  /**
   * Convierte a UTF-8 de forma defensiva (Windows-1252/ISO-8859-1 comunes).
   */
  function ciop_to_utf8(string $s): string {
    // Quita BOM UTF-8
    $s = preg_replace('/^\xEF\xBB\xBF/', '', $s);

    if (!function_exists('mb_detect_encoding')) return $s;
    $enc = mb_detect_encoding($s, ['UTF-8','Windows-1252','ISO-8859-1'], true);
    if ($enc && $enc !== 'UTF-8') {
      return mb_convert_encoding($s, 'UTF-8', $enc);
    }
    return $s;
  }
}

if (!function_exists('ciop_norm_header')) {
  function ciop_norm_header(string $h): string {
    $h = ciop_to_utf8($h);
    $h = trim($h);
    $h = preg_replace('/\s+/', ' ', $h);
    $h = str_replace(['\t', '\r', '\n'], ' ', $h);

    // Normaliza separadores -> underscore
    $h = preg_replace('/[^A-Za-z0-9]+/u', '_', $h);
    $h = trim($h, '_');
    $h = strtoupper($h);
    return $h;
  }
}

if (!function_exists('parse_semicolon_txt')) {
  /**
   * Parseo genérico TXT con separador ";" y comillas dobles.
   * Retorna array<array<string,mixed>>.
   */
  function parse_semicolon_txt(string $path): array {
    if (!is_file($path)) return [];

    $rows = [];
    $fh = new SplFileObject($path, 'r');
    $fh->setFlags(SplFileObject::DROP_NEW_LINE);

    $headers = null;
    foreach ($fh as $line) {
      if ($line === null) continue;
      $line = ciop_to_utf8((string)$line);
      $line = trim($line);
      if ($line === '') continue;

      $parts = str_getcsv($line, ';', '"');
      if ($headers === null) {
        $headers = array_map(fn($h)=>ciop_norm_header((string)$h), $parts);
        continue;
      }

      $row = [];
      foreach ($headers as $i => $h) {
        $row[$h] = $parts[$i] ?? '';
      }

      $rows[] = $row;
    }

    return $rows;
  }
}

if (!function_exists('detect_csv_delim')) {
  function detect_csv_delim(string $line): string {
    $line = ciop_to_utf8($line);
    $cands = [',',';','\t','|'];
    $best = ','; $bestScore = -1;
    foreach ($cands as $d) {
      $score = substr_count($line, $d);
      if ($score > $bestScore) {
        $bestScore = $score;
        $best = $d;
      }
    }
    return $best;
  }
}

if (!function_exists('parse_csv_file')) {
  /**
   * Parseo genérico CSV (delimitador autodetectado), encabezados normalizados.
   */
  function parse_csv_file(string $path): array {
    if (!is_file($path)) return [];

    $fh = new SplFileObject($path, 'r');
    $fh->setFlags(SplFileObject::DROP_NEW_LINE);

    // Busca primera línea no vacía para detectar delimitador
    $first = '';
    foreach ($fh as $line) {
      if ($line === null) continue;
      $line = trim(ciop_to_utf8((string)$line));
      if ($line !== '') { $first = $line; break; }
    }
    if ($first === '') return [];

    $delim = detect_csv_delim($first);

    // Reinicia archivo
    $fh = new SplFileObject($path, 'r');
    $fh->setFlags(SplFileObject::DROP_NEW_LINE);

    $headers = null;
    $rows = [];

    foreach ($fh as $line) {
      if ($line === null) continue;
      $line = trim(ciop_to_utf8((string)$line));
      if ($line === '') continue;

      $parts = str_getcsv($line, $delim, '"');
      if ($headers === null) {
        $headers = array_map(fn($h)=>ciop_norm_header((string)$h), $parts);
        continue;
      }

      $row = [];
      foreach ($headers as $i => $h) {
        $row[$h] = $parts[$i] ?? '';
      }
      $rows[] = $row;
    }

    return $rows;
  }
}
