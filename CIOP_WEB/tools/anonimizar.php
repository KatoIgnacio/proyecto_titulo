<?php
declare(strict_types=1);

/**
 * =============================================================================
 *  Normalización y anonimización de los archivos operacionales de CIOP
 *  Proyecto de Título - Sistema de apoyo a la gestión de contingencias
 * =============================================================================
 *
 *  Toma los archivos reales entregados por el cliente y genera versiones
 *  aptas para desarrollo, pruebas, capturas del informe y la defensa.
 *
 *  USO
 *      php tools/anonimizar.php --entrada=RUTA_ORIGEN --salida=RUTA_DESTINO
 *
 *  EJEMPLO
 *      php tools/anonimizar.php ^
 *          --entrada="C:\Users\KATO\Documents\CIOP_DATA_REAL" ^
 *          --salida="C:\Users\KATO\Documents\GitHub\proyecto_titulo\CIOP_DATA"
 *
 *  QUÉ HACE
 *    Normaliza
 *      - Convierte de Windows-1252 a UTF-8 (elimina los "Sin Energ?a").
 *      - Uniforma nombres de comuna a mayúsculas sin acentos.
 *      - Descarta la fila de pie "BDVR" del archivo de órdenes, que no es dato.
 *      - Agrega columnas LAT y LON convertidas desde UTM Huso 19 Sur.
 *      - Separa alimentadores múltiples y marca los valores nulos conocidos.
 *
 *    Anonimiza
 *      - Reemplaza el número de cliente por un seudónimo estable.
 *      - Reemplaza nombres, direcciones, teléfonos y comentarios de llamada.
 *      - Desplaza las coordenadas dentro de un radio configurable.
 *      - Elimina los apellidos de las cuadrillas.
 *
 *  GARANTÍA DE CONSISTENCIA
 *    El seudónimo de cada cliente y el desplazamiento de sus coordenadas se
 *    derivan del número original mediante una función determinista. El mismo
 *    cliente recibe el mismo seudónimo en los cuatro archivos, de modo que los
 *    cruces entre clientes afectados, críticos y electrodependientes siguen
 *    funcionando igual que con los datos reales.
 *
 *  ADVERTENCIA
 *    Los archivos de entrada contienen información real de clientes y de
 *    clientes electrodependientes. Deben mantenerse fuera del repositorio y
 *    fuera de cualquier respaldo compartido. Solo la salida de este script
 *    puede usarse en el informe, en capturas y en demostraciones.
 * =============================================================================
 */

require_once __DIR__ . '/geo.php';

// ---------------------------------------------------------------- parámetros

const SAL          = 'ciop-anonimizacion-2026';  // cambia todos los seudónimos si se modifica
const RADIO_METROS = 400;                        // desplazamiento máximo de coordenadas
const ESTILO       = 'realista';                 // 'realista' | 'generico'

$archivos = [
  'CIOP-PO.txt'              => 'ordenes',
  'CIOP-PO_clientes.txt'     => 'clientes',
  'CIOP-PO_criticos.txt'     => 'criticos',
  'CIOP-PO_ED.txt'           => 'ed',
];

// ---------------------------------------------------------------- argumentos

$opts    = getopt('', ['entrada:', 'salida:', 'radio::', 'estilo::']);
$entrada = rtrim((string)($opts['entrada'] ?? ''), "\\/ \t\n\r");
$salida  = rtrim((string)($opts['salida']  ?? ''), "\\/ \t\n\r");
$radio   = isset($opts['radio'])  ? max(0, (int)$opts['radio']) : RADIO_METROS;
$estilo  = isset($opts['estilo']) ? (string)$opts['estilo']     : ESTILO;

if ($entrada === '' || $salida === '') {
  fwrite(STDERR, "Faltan parámetros.\n\n");
  fwrite(STDERR, "  php tools/anonimizar.php --entrada=RUTA_ORIGEN --salida=RUTA_DESTINO\n");
  fwrite(STDERR, "  Opcionales: --radio=400  --estilo=realista|generico\n\n");
  exit(1);
}
if (!is_dir($entrada)) { fwrite(STDERR, "No existe la carpeta de entrada: {$entrada}\n"); exit(1); }
if (!is_dir($salida) && !@mkdir($salida, 0777, true)) {
  fwrite(STDERR, "No se pudo crear la carpeta de salida: {$salida}\n"); exit(1);
}
if (realpath($entrada) === realpath($salida)) {
  fwrite(STDERR, "La entrada y la salida no pueden ser la misma carpeta.\n"); exit(1);
}

// ------------------------------------------------------------ seudonimizador

/** Entero estable y no reversible a partir de un texto. */
function semilla(string $valor, string $ambito = ''): int {
  return (int)hexdec(substr(hash('sha256', SAL . '|' . $ambito . '|' . $valor), 0, 12));
}

/** Número de cliente ficticio que conserva el prefijo de empresa (031- / 032-). */
function seudo_nis(string $nis): string {
  $nis = trim($nis);
  if ($nis === '') return '';

  $prefijo = '';
  if (preg_match('/^(\d{3})-/', $nis, $m)) $prefijo = $m[1] . '-';

  $n = semilla($nis, 'nis') % 900000 + 100000;
  return $prefijo . $n;
}

/** Desplazamiento fijo en metros para un cliente, dentro de un radio dado. */
function desplazar(?float $x, ?float $y, string $clave, int $radio): array {
  if ($x === null || $y === null || $radio <= 0) return [$x, $y];

  $s   = semilla($clave, 'geo');
  $ang = ($s % 36000) / 36000 * 2 * M_PI;
  $r   = (($s >> 16) % 1000) / 1000 * $radio;

  return [$x + cos($ang) * $r, $y + sin($ang) * $r];
}

// ---------------------------------------------------------------- catálogos

const NOMBRES = ['ANDREA','BERNARDO','CAMILA','DANIEL','ELENA','FELIPE','GABRIELA',
  'HERNAN','ISIDORA','JAVIER','KARINA','LUCAS','MARCELA','NICOLAS','OLIVIA','PABLO',
  'ROSA','SEBASTIAN','TAMARA','VICENTE','XIMENA','ALONSO','BEATRIZ','CRISTOBAL',
  'DANIELA','EMILIO','FLORENCIA','GONZALO','IGNACIA','JOAQUIN'];

const APELLIDOS = ['ARENAS','BRIONES','CARRASCO','DONOSO','ESPINOZA','FUENZALIDA',
  'GALLARDO','HERRERA','IBACACHE','JARA','LAGOS','MALDONADO','NAVARRETE','OYARZUN',
  'PALMA','QUEZADA','RIQUELME','SALGADO','TAPIA','URRUTIA','VALDIVIA','ZAMORA',
  'ACEVEDO','BUSTAMANTE','CIFUENTES','DIAZ','ELGUETA','FIGUEROA'];

const VIAS = ['CAMINO LOS ALMENDROS','CALLEJON EL BOLDO','PASAJE LAS ACACIAS',
  'RUTA INTERIOR EL MAITEN','CAMINO LA VEGA','CALLE LOS AROMOS','SECTOR EL PEUMO',
  'CAMINO EL QUILLAY','PASAJE LOS CANELOS','CALLEJON SANTA ELENA','CAMINO EL ROBLE',
  'SECTOR LOS LITRES','CALLE LAS PATAGUAS','CAMINO EL MOLLE'];

const COMENTARIOS = ['CLIENTE REPORTA SECTOR SIN SUMINISTRO',
  'AVISO POR CORTE DE ENERGIA EN EL SECTOR','SE INFORMA FALTA DE SUMINISTRO',
  'REPORTE DE INTERRUPCION EN DOMICILIO','CLIENTE INDICA SECTOR A OSCURAS',
  'AVISO DE FALLA EN LINEA DEL SECTOR','SE REPORTA RUIDO EN TRANSFORMADOR',
  'CLIENTE CONSULTA POR REPOSICION DEL SERVICIO'];

const TIPOS_CRITICO = ['HOSPITAL'=>'HOSPITAL','POSTA'=>'POSTA','COLEGIO'=>'LICEO',
  'APR'=>'APR','ANTENA'=>'ANTENA'];

function nombre_persona(string $clave): string {
  if (ESTILO === 'generico') return 'CLIENTE TEST ' . str_pad((string)(semilla($clave,'g') % 9999), 4, '0', STR_PAD_LEFT);
  $s = semilla($clave, 'nom');
  return NOMBRES[$s % count(NOMBRES)] . ' ' . APELLIDOS[($s >> 8) % count(APELLIDOS)];
}

function direccion(string $clave): string {
  $s = semilla($clave, 'dir');
  return VIAS[$s % count(VIAS)] . ' ' . (($s >> 7) % 900 + 100);
}

function telefono(string $clave): string {
  return '9' . str_pad((string)(semilla($clave, 'tel') % 100000000), 8, '0', STR_PAD_LEFT);
}

function comentario(string $clave): string {
  return COMENTARIOS[semilla($clave, 'com') % count(COMENTARIOS)];
}

/** Quita el apellido del identificador de cuadrilla: LP_HCZK88-E_Villalobos -> LP_HCZK88-CUADRILLA_07 */
function cuadrilla(string $v): string {
  $v = trim($v);
  if ($v === '') return '';
  $codigo = preg_split('/-/', $v)[0] ?? $v;
  return $codigo . '-CUADRILLA_' . str_pad((string)(semilla($v, 'crew') % 40 + 1), 2, '0', STR_PAD_LEFT);
}

function nombre_critico(string $clave, string $tipo, string $comuna): string {
  $t = TIPOS_CRITICO[strtoupper(trim($tipo))] ?? 'INSTALACION';
  return $t . ' TEST ' . ($comuna !== '' ? $comuna : 'SECTOR')
       . ' ' . str_pad((string)(semilla($clave, 'cc') % 99 + 1), 2, '0', STR_PAD_LEFT);
}

// ------------------------------------------------------------- lectura CSV

function a_utf8(string $s): string {
  $s = preg_replace('/^\xEF\xBB\xBF/', '', $s) ?? $s;
  $enc = function_exists('mb_detect_encoding')
    ? mb_detect_encoding($s, ['UTF-8', 'Windows-1252', 'ISO-8859-1'], true)
    : null;
  if ($enc && $enc !== 'UTF-8' && function_exists('mb_convert_encoding')) {
    return (string)mb_convert_encoding($s, 'UTF-8', $enc);
  }
  return $s;
}

/** @return array{0: array<int,string>, 1: array<int,array<string,string>>} */
function leer(string $path): array {
  $crudo = @file_get_contents($path);
  if ($crudo === false) return [[], []];

  $lineas = preg_split('/\r\n|\r|\n/', a_utf8($crudo)) ?: [];
  $cab = null;
  $filas = [];

  foreach ($lineas as $linea) {
    if (trim($linea) === '') continue;
    $p = str_getcsv($linea, ';', '"', '\\');
    if ($cab === null) { $cab = array_map(fn($h) => trim((string)$h), $p); continue; }
    $fila = [];
    foreach ($cab as $i => $h) $fila[$h] = (string)($p[$i] ?? '');
    $filas[] = $fila;
  }

  return [$cab ?? [], $filas];
}

function escribir(string $path, array $cab, array $filas): void {
  $fh = fopen($path, 'wb');
  if ($fh === false) throw new RuntimeException("No se pudo escribir {$path}");

  $linea = fn(array $v) => '"' . implode('";"', array_map(
      fn($x) => str_replace('"', "'", (string)$x), $v)) . '"' . "\r\n";

  fwrite($fh, $linea($cab));
  foreach ($filas as $f) {
    $ordenada = [];
    foreach ($cab as $h) $ordenada[] = $f[$h] ?? '';
    fwrite($fh, $linea($ordenada));
  }
  fclose($fh);
}

// ------------------------------------------------------------ coordenadas

/** Desplaza X/Y y agrega LAT/LON. Devuelve true si la conversión fue válida. */
function procesar_coords(array &$f, string $clave, int $radio, array &$stats): void {
  $x = ciop_num($f['X'] ?? null);
  $y = ciop_num($f['Y'] ?? null);

  [$x, $y] = desplazar($x, $y, $clave, $radio);

  if ($x !== null && $y !== null) {
    $f['X'] = number_format($x, 3, ',', '');
    $f['Y'] = number_format($y, 3, ',', '');
  }

  $ll = utm19s_a_wgs84($x, $y);
  if ($ll === null) {
    $f['LAT'] = '';
    $f['LON'] = '';
    $stats['coords_invalidas']++;
  } else {
    $f['LAT'] = number_format($ll[0], 7, '.', '');
    $f['LON'] = number_format($ll[1], 7, '.', '');
    $stats['coords_ok']++;
  }
}

// ------------------------------------------------------------ procesadores

function proc_ordenes(array $f, int $radio, array &$stats): ?array {
  // La última línea del archivo es un pie de control, no una orden.
  if (($f['BD'] ?? '') !== 'PO43EO') { $stats['pie_descartado']++; return null; }

  $nis = trim($f['ACCOUNT_NUMBER'] ?? '');

  $f['ACCOUNT_NUMBER']     = seudo_nis($nis);
  $f['ORIGINATING_NUMBER'] = $nis !== '' ? substr($nis, 0, 4) . telefono($nis) : '';
  $f['LOCATION']           = direccion($nis !== '' ? $nis : ($f['ORDER_ID'] ?? ''));
  $f['COMENTARIO_LLAMADA'] = comentario($f['ORDER_ID'] ?? '');
  $f['CREW']               = cuadrilla($f['CREW'] ?? '');
  $f['NAME']               = ciop_normalizar_comuna($f['NAME'] ?? '');

  // Alimentador: se normaliza y se separan los casos con más de uno.
  $alim = trim($f['ALIMENTADOR'] ?? '');
  $partes = array_values(array_filter(array_map(
      fn($p) => ciop_normalizar_alimentador($p), explode(',', $alim))));

  $f['ALIMENTADOR']     = $partes[0] ?? '';
  $f['ALIMENTADOR_ALT'] = implode('|', array_slice($partes, 1));

  if ($f['ALIMENTADOR'] === '')      $stats['alim_sin_valor']++;
  if ($f['ALIMENTADOR_ALT'] !== '')  $stats['alim_multiple']++;

  procesar_coords($f, $nis !== '' ? $nis : ($f['ORDER_ID'] ?? ''), $radio, $stats);
  return $f;
}

function proc_clientes(array $f, int $radio, array &$stats): ?array {
  $nis = trim($f['CUSTOMER_ACCOUNT'] ?? '');

  $f['CUSTOMER_ACCOUNT'] = seudo_nis($nis);
  $f['CUSTOMER_ID']      = $nis !== '' ? (string)(semilla($nis, 'cid') % 9000000 + 1000000) : '';
  $f['NOMBRE']           = nombre_persona($nis);
  $f['LOCATION_DESC']    = direccion($nis);
  $f['PHONE_NUMBER']     = telefono($nis);
  $f['DESCRIPTION']      = comentario($nis);
  $f['CREW']             = cuadrilla($f['CREW'] ?? '');
  $f['CITY']             = ciop_normalizar_comuna($f['CITY'] ?? '');
  $f['NAME']             = ciop_normalizar_comuna($f['NAME'] ?? '');

  procesar_coords($f, $nis, $radio, $stats);
  return $f;
}

function proc_criticos(array $f, int $radio, array &$stats): ?array {
  $nis = trim($f['NIS'] ?? '');
  $comuna = ciop_normalizar_comuna($f['CITY'] ?? '');

  $f['NIS']    = seudo_nis($nis);
  $f['NOMBRE'] = nombre_critico($nis, $f['TIPO'] ?? '', $comuna);
  $f['CITY']   = $comuna;

  foreach (['OBS'] as $c) if (isset($f[$c])) $f[$c] = '';

  procesar_coords($f, $nis, $radio, $stats);
  return $f;
}

function proc_ed(array $f, int $radio, array &$stats): ?array {
  $nis = trim($f['NIS'] ?? '');

  $f['NIS']         = seudo_nis($nis);
  $f['NOMBRE']      = 'PACIENTE TEST ' . str_pad((string)(semilla($nis, 'ed') % 999 + 1), 3, '0', STR_PAD_LEFT);
  $f['DIRECCION']   = direccion($nis);
  $f['OBSERVACION'] = 'FONO: ' . telefono($nis) . '|';
  $f['COMUNA']      = ciop_normalizar_comuna($f['COMUNA'] ?? '');

  foreach (['OBS'] as $c) if (isset($f[$c])) $f[$c] = '';

  procesar_coords($f, $nis, $radio, $stats);
  return $f;
}

// ---------------------------------------------------------------- ejecución

$PROC = [
  'ordenes'  => 'proc_ordenes',
  'clientes' => 'proc_clientes',
  'criticos' => 'proc_criticos',
  'ed'       => 'proc_ed',
];

echo "Anonimización de datos CIOP\n";
echo str_repeat('-', 66) . "\n";
echo "  Entrada : {$entrada}\n";
echo "  Salida  : {$salida}\n";
echo "  Radio   : {$radio} m   Estilo: {$estilo}\n";
echo str_repeat('-', 66) . "\n\n";

$totales = ['filas' => 0, 'archivos' => 0];

foreach ($archivos as $nombre => $tipo) {
  $origen = $entrada . DIRECTORY_SEPARATOR . $nombre;

  if (!is_file($origen)) {
    echo "  [omitido]  {$nombre}  (no existe en la entrada)\n";
    continue;
  }

  [$cab, $filas] = leer($origen);
  if ($cab === []) { echo "  [error]    {$nombre}  (no se pudo leer)\n"; continue; }

  $stats = ['coords_ok' => 0, 'coords_invalidas' => 0, 'pie_descartado' => 0,
            'alim_sin_valor' => 0, 'alim_multiple' => 0];

  $fn = $PROC[$tipo];
  $salidaFilas = [];
  foreach ($filas as $f) {
    $r = $fn($f, $radio, $stats);
    if ($r !== null) $salidaFilas[] = $r;
  }

  // Columnas nuevas al final, sin alterar el orden de las originales.
  foreach (['LAT', 'LON'] as $c) if (!in_array($c, $cab, true)) $cab[] = $c;
  if ($tipo === 'ordenes' && !in_array('ALIMENTADOR_ALT', $cab, true)) $cab[] = 'ALIMENTADOR_ALT';

  $destino = $salida . DIRECTORY_SEPARATOR
           . preg_replace('/\.txt$/i', '_TEST.txt', $nombre);
  escribir($destino, $cab, $salidaFilas);

  echo "  [ok]       {$nombre}\n";
  echo "             " . count($salidaFilas) . " filas -> " . basename($destino) . "\n";
  echo "             coordenadas: {$stats['coords_ok']} convertidas";
  if ($stats['coords_invalidas'] > 0) echo ", {$stats['coords_invalidas']} inválidas";
  echo "\n";
  if ($stats['pie_descartado'] > 0) echo "             pie de control descartado: {$stats['pie_descartado']} fila\n";
  if ($stats['alim_sin_valor'] > 0) echo "             alimentador sin valor: {$stats['alim_sin_valor']}\n";
  if ($stats['alim_multiple'] > 0)  echo "             alimentador múltiple: {$stats['alim_multiple']}\n";
  echo "\n";

  $totales['filas'] += count($salidaFilas);
  $totales['archivos']++;
}

echo str_repeat('-', 66) . "\n";
echo "  {$totales['archivos']} archivos, {$totales['filas']} filas procesadas.\n";
echo "  Los cruces por número de cliente se mantienen entre los cuatro archivos.\n";
echo str_repeat('-', 66) . "\n";
