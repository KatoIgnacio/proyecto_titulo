<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

require_once __DIR__ . '/../../src/repo.php';

$cfg  = require __DIR__ . '/../../src/config.php';
$repo = new CiopRepo($cfg);

$action = (string)($_GET['action'] ?? 'meta');

// Flags JSON robustos (clave para tu caso)
const CIOP_JSON_FLAGS =
  JSON_UNESCAPED_UNICODE
  | JSON_INVALID_UTF8_SUBSTITUTE; // 👈 evita que json_encode falle por strings “ANSI/Win1252”

/**
 * Echo JSON y corta. Si json_encode falla, responde error JSON (no vacío).
 * @param mixed $payload
 */
function json_out($payload, int $code = 200): void {
  http_response_code($code);

  $json = json_encode($payload, CIOP_JSON_FLAGS);
  if ($json === false) {
    // Nunca dejar respuesta vacía
    $err = json_last_error_msg();
    http_response_code(500);
    echo json_encode(['error' => "json_encode falló: {$err}"], CIOP_JSON_FLAGS);
    return;
  }
  echo $json;
}

try {
  switch ($action) {

    case 'meta':
      json_out([
        'refresh' => (int)($cfg['REFRESH_SEC'] ?? 30),
        'meta'    => $repo->meta(),
      ]);
      break;

    case 'general':
      $clientes = $repo->clientes_afectados();
      $po       = $repo->po();
      $hist24   = $repo->hist24();

      $critTotal = count($repo->criticos());
      $edTotal   = count($repo->ed());

      $kpis = [
        'clientes_sin_suministro' => count($clientes),
        'ordenes_po'              => count($po),
        'criticos_afectados'      => count($repo->criticos_afectados()),
        'criticos_total'          => $critTotal,
        'ed_afectados'            => count($repo->ed_afectados()),
        'ed_total'                => $edTotal,
      ];

      // top 10 comunas por afectados
      $by = [];
      foreach ($clientes as $r) {
        $c = normalize_comuna((string)($r['CITY'] ?? ''));
        if ($c === '') $c = 'SIN COMUNA';
        $by[$c] = ($by[$c] ?? 0) + 1;
      }
      arsort($by);
      $top10 = [];
      foreach (array_slice($by, 0, 10, true) as $comuna => $n) {
        $top10[] = ['COMUNA' => $comuna, 'CLIENTES' => $n];
      }

      json_out([
        'kpis'          => $kpis,
        'clientes'      => $clientes,
        'top10_comunas' => $top10,
        'hist24'        => $hist24,
        'hist_missing'  => !$repo->hist24_exists(),
      ]);
      break;

    case 'comunas':
      json_out(['rows' => $repo->comunas_resumen()]);
      break;

    case 'criticos':
      json_out([
        'total'     => count($repo->criticos()),
        'afectados' => $repo->criticos_afectados(),
      ]);
      break;

    case 'ed':
      json_out([
        'total'     => count($repo->ed()),
        'afectados' => $repo->ed_afectados(),
      ]);
      break;

    case 'map':
      json_out([
        'kml_url'   => 'api/kml.php',
        'afectadas' => $repo->comunas_afectadas_set(),
        'puntos'    => $repo->puntos_clientes_afectados(),
      ]);
      break;

    case 'ticker':
      json_out([
        'ts'           => date('Y-m-d H:i:s'),
        'ed_afectados' => count($repo->ed_afectados()),
        'last3'        => $repo->last3_comunas_en_falla(),
      ]);
      break;

    default:
      json_out(['error' => 'Acción inválida'], 400);
  }

} catch (Throwable $e) {
  json_out(['error' => $e->getMessage()], 500);
}