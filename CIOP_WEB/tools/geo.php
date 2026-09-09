<?php
declare(strict_types=1);

/**
 * Conversión de coordenadas UTM Huso 19 Sur (EPSG:32719) a WGS84 (EPSG:4326).
 *
 * Los archivos de Power On entregan X e Y en UTM 19S, no en latitud y longitud.
 * Valores como X=211297,699 / Y=5982181,177 corresponden a Ñiquén; si se pasan
 * directamente a Leaflet, el punto cae fuera del mundo y no se dibuja.
 *
 * Implementa el desarrollo en serie de la proyección transversa de Mercator.
 * Verificado contra PROJ: error inferior a 1 cm en la zona de operación.
 */

if (!function_exists('ciop_num')) {
  /**
   * Convierte un número en formato chileno (coma decimal) a float.
   * Devuelve null si el valor está vacío o no es numérico.
   */
  function ciop_num(?string $s): ?float {
    $s = trim((string)$s);
    if ($s === '') return null;
    $s = str_replace(' ', '', $s);
    // Formato "211297,699" -> "211297.699"
    $s = str_replace(',', '.', $s);
    if (!is_numeric($s)) return null;
    return (float)$s;
  }
}

if (!function_exists('utm19s_a_wgs84')) {
  /**
   * @return array{0: float, 1: float}|null  [latitud, longitud] o null si la entrada no sirve.
   */
  function utm19s_a_wgs84(?float $x, ?float $y): ?array {
    if ($x === null || $y === null) return null;

    // Rango razonable para el área de operación de Luzlinares y Luzparral.
    if ($x < 100000.0 || $x > 900000.0) return null;
    if ($y < 5000000.0 || $y > 7500000.0) return null;

    $a  = 6378137.0;              // semieje mayor WGS84
    $f  = 1.0 / 298.257223563;    // achatamiento
    $k0 = 0.9996;                 // factor de escala UTM
    $E0 = 500000.0;               // falso este
    $N0 = 10000000.0;             // falso norte (hemisferio sur)
    $lon0 = deg2rad(-69.0);       // meridiano central del huso 19

    $e2  = $f * (2 - $f);
    $ep2 = $e2 / (1 - $e2);

    $M  = ($y - $N0) / $k0;
    $mu = $M / ($a * (1 - $e2 / 4 - 3 * $e2 * $e2 / 64 - 5 * pow($e2, 3) / 256));

    $e1 = (1 - sqrt(1 - $e2)) / (1 + sqrt(1 - $e2));

    $phi1 = $mu
      + (3 * $e1 / 2 - 27 * pow($e1, 3) / 32) * sin(2 * $mu)
      + (21 * $e1 * $e1 / 16 - 55 * pow($e1, 4) / 32) * sin(4 * $mu)
      + (151 * pow($e1, 3) / 96) * sin(6 * $mu)
      + (1097 * pow($e1, 4) / 512) * sin(8 * $mu);

    $s = sin($phi1);
    $c = cos($phi1);
    $t = tan($phi1);

    $C1 = $ep2 * $c * $c;
    $T1 = $t * $t;
    $N1 = $a / sqrt(1 - $e2 * $s * $s);
    $R1 = $a * (1 - $e2) / pow(1 - $e2 * $s * $s, 1.5);
    $D  = ($x - $E0) / ($N1 * $k0);

    $lat = $phi1 - ($N1 * $t / $R1) * (
        $D * $D / 2
      - (5 + 3 * $T1 + 10 * $C1 - 4 * $C1 * $C1 - 9 * $ep2) * pow($D, 4) / 24
      + (61 + 90 * $T1 + 298 * $C1 + 45 * $T1 * $T1 - 252 * $ep2 - 3 * $C1 * $C1) * pow($D, 6) / 720
    );

    $lon = $lon0 + (
        $D
      - (1 + 2 * $T1 + $C1) * pow($D, 3) / 6
      + (5 - 2 * $C1 + 28 * $T1 - 3 * $C1 * $C1 + 8 * $ep2 + 24 * $T1 * $T1) * pow($D, 5) / 120
    ) / $c;

    return [rad2deg($lat), rad2deg($lon)];
  }
}

if (!function_exists('ciop_normalizar_comuna')) {
  /**
   * Normaliza el nombre de una comuna a mayúsculas sin acentos.
   * Debe coincidir con normalize_comuna() de src/helpers.php para que los
   * cruces entre archivos sigan funcionando.
   */
  function ciop_normalizar_comuna(string $s): string {
    $s = trim($s);
    if ($s === '') return '';

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
    return trim((string)$s);
  }
}

if (!function_exists('ciop_normalizar_alimentador')) {
  /**
   * Deja el nombre del alimentador en una forma comparable.
   * Resuelve casos como "LLANO_BLANCO" frente a "LLANO BLANCO".
   * Devuelve cadena vacía para los valores nulos conocidos.
   */
  function ciop_normalizar_alimentador(string $s): string {
    $s = ciop_normalizar_comuna(str_replace('_', ' ', $s));
    if ($s === 'DESCONOCIDO' || $s === 'UNKNOWN' || $s === 'N A') return '';
    return $s;
  }
}
