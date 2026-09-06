<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/parse.php';

final class CiopRepo {
  /** @var array<string,mixed> */
  private array $cfg;

  /** @var array<string,array{mtime:int,size:int,data:mixed}> */
  private array $cache = [];

  /** @param array<string,mixed> $cfg */
  public function __construct(array $cfg){
    $this->cfg = $cfg;
  }

  private function dataDir(): string {
    return rtrim((string)($this->cfg['DATA_DIR'] ?? ''), "\\/\t \n\r\0\x0B");
  }

  private function fileName(string $key): string {
    $files = $this->cfg['FILES'] ?? [];
    return (string)($files[$key] ?? '');
  }

  private function path(string $key): string {
    $name = $this->fileName($key);
    if ($name === '') return '';
    return $this->dataDir() . DIRECTORY_SEPARATOR . $name;
  }

  /** @return array{exists:bool,size:int,mtime:string} */
  private function statInfo(string $path): array {
    if ($path === '' || !is_file($path)){
      return ['exists'=>false,'size'=>0,'mtime'=>''];
    }
    $size = (int)(@filesize($path) ?: 0);
    $mtime = (int)(@filemtime($path) ?: 0);
    $mtimeStr = $mtime ? date('Y-m-d H:i:s', $mtime) : '';
    return ['exists'=>true,'size'=>$size,'mtime'=>$mtimeStr];
  }

  /** @return array<string,array{exists:bool,size:int,mtime:string}> */
  public function meta(): array {
    $out = [];
    $files = $this->cfg['FILES'] ?? [];
    foreach ($files as $k => $fname){
      $out[(string)$k] = $this->statInfo($this->dataDir() . DIRECTORY_SEPARATOR . (string)$fname);
    }
    return $out;
  }

  /**
   * Lee un TXT/CSV con separador y encabezado.
   * - Minimiza lecturas a medio escribir: si size/mtime cambian mientras lee => devuelve el cache anterior o [].
   * @return array<int,array<string,string>>
   */
  private function readDelimitedTable(string $path, string $delim, string $enclosure='"'): array {
    if ($path === '' || !is_file($path)) return [];

    clearstatcache(true, $path);
    $s1 = (int)(@filesize($path) ?: 0);
    $m1 = (int)(@filemtime($path) ?: 0);

    $fh = @fopen($path, 'rb');
    if ($fh === false) return [];

    $rows = [];

    // header
    $header = fgetcsv($fh, 0, $delim, $enclosure);
    if (!is_array($header) || count($header) === 0){
      fclose($fh);
      return [];
    }

    // detecta si realmente es header
    $nonNum = 0;
    foreach ($header as $h){
      $h2 = trim((string)$h);
      if ($h2 === '') continue;
      if (!is_numeric(str_replace(',', '.', $h2))) $nonNum++;
    }
    $hasHeader = $nonNum >= 1;

    $keys = [];
    if ($hasHeader){
      foreach ($header as $i => $h){
        $k = trim((string)$h);
        if ($k === '') $k = 'COL' . ($i+1);
        $keys[] = $k;
      }
    } else {
      // header falso, la primera fila es data
      $keys = array_map(fn($i)=>'COL'.($i+1), range(0, count($header)-1));
      $row0 = [];
      foreach ($keys as $i => $k){ $row0[$k] = (string)($header[$i] ?? ''); }
      $rows[] = $row0;
    }

    while (($cols = fgetcsv($fh, 0, $delim, $enclosure)) !== false){
      if (!is_array($cols) || (count($cols) === 1 && ($cols[0] === null || $cols[0] === ''))) continue;
      $row = [];
      foreach ($keys as $i => $k){
        $row[$k] = (string)($cols[$i] ?? '');
      }
      $rows[] = $row;
    }

    fclose($fh);

    clearstatcache(true, $path);
    $s2 = (int)(@filesize($path) ?: 0);
    $m2 = (int)(@filemtime($path) ?: 0);
    if (($s1 && $s2 && $s1 !== $s2) || ($m1 && $m2 && $m1 !== $m2)){
      // se estaba escribiendo; el caller usa cache anterior
      return [];
    }

    return $rows;
  }

  private function detectCsvDelimiter(string $path): string {
    $fh = @fopen($path, 'rb');
    if ($fh === false) return ',';
    $line = fgets($fh);
    fclose($fh);
    if ($line === false) return ',';

    $cands = [',',';',"\t",'|'];
    $best = ','; $bestCount = -1;
    foreach ($cands as $d){
      $cnt = substr_count((string)$line, $d);
      if ($cnt > $bestCount){ $best = $d; $bestCount = $cnt; }
    }
    return $best;
  }

  /** @return array<int,array<string,string>> */
  private function cachedTable(string $key, callable $loader): array {
    $path = $this->path($key);
    if ($path === '' || !is_file($path)) {
      $this->cache[$key] = ['mtime'=>0,'size'=>0,'data'=>[]];
      return [];
    }

    clearstatcache(true, $path);
    $mtime = (int)(@filemtime($path) ?: 0);
    $size  = (int)(@filesize($path) ?: 0);

    if (isset($this->cache[$key]) && $this->cache[$key]['mtime'] === $mtime && $this->cache[$key]['size'] === $size){
      /** @var array<int,array<string,string>> */
      return $this->cache[$key]['data'];
    }

    $data = $loader($path);

    // Si devolvió vacío por posible escritura en curso, usa cache anterior si existe.
    if ($data === [] && isset($this->cache[$key]) && ($this->cache[$key]['mtime'] !== 0)){
      /** @var array<int,array<string,string>> */
      return $this->cache[$key]['data'];
    }

    $this->cache[$key] = ['mtime'=>$mtime,'size'=>$size,'data'=>$data];
    return $data;
  }

  /** @return array<int,array<string,string>> */
  public function po(): array {
    return $this->cachedTable('PO', fn(string $p)=>$this->readDelimitedTable($p, ';'));
  }

  /** @return array<int,array<string,string>> */
  public function clientes(): array {
    return $this->cachedTable('CLIENTES', fn(string $p)=>$this->readDelimitedTable($p, ';'));
  }

  /** @return array<int,array<string,string>> */
  public function clientes_afectados(): array {
    $rows = $this->clientes();
    $out = [];
    foreach ($rows as $r){
      if (is_outage_cliente($r)) $out[] = $r;
    }
    return $out;
  }

  /** @return array<int,array<string,string>> */
  public function criticos(): array {
    return $this->cachedTable('CRITICOS', fn(string $p)=>$this->readDelimitedTable($p, ';'));
  }

  /** @return array<int,array<string,string>> */
  public function ed(): array {
    return $this->cachedTable('ED', fn(string $p)=>$this->readDelimitedTable($p, ';'));
  }

  /** @return array<int,array<string,string>> */
  public function hist24(): array {
    $path = $this->path('HIST24');
    if ($path === '' || !is_file($path)) return [];
    $delim = $this->detectCsvDelimiter($path);
    return $this->cachedTable('HIST24', fn(string $p)=>$this->readDelimitedTable($p, $delim));
  }

  public function hist24_exists(): bool {
    $path = $this->path('HIST24');
    return ($path !== '' && is_file($path));
  }

  /** @return array<string,bool> */
  public function comunas_afectadas_set(): array {
    $set = [];
    foreach ($this->clientes_afectados() as $r){
      $c = normalize_comuna((string)($r['CITY'] ?? ''));
      if ($c === '') $c = 'SIN COMUNA';
      $set[$c] = true;
    }
    return $set;
  }

  /** @return array<int,array{COMUNA:string,CLIENTES:int}> */
  public function comunas_resumen(): array {
    $by = [];
    foreach ($this->clientes_afectados() as $r){
      $c = normalize_comuna((string)($r['CITY'] ?? ''));
      if ($c === '') $c = 'SIN COMUNA';
      $by[$c] = ($by[$c] ?? 0) + 1;
    }
    arsort($by);

    $out = [];
    foreach ($by as $comuna => $n){
      $out[] = ['COMUNA'=>$comuna, 'CLIENTES'=>$n];
    }
    return $out;
  }

  /** @return array<int,array<string,string>> */
  public function criticos_afectados(): array {
    $set = [];
    foreach ($this->clientes_afectados() as $r){
      $nis = get_nis_from_cliente($r);
      $d = nis_digits($nis);
      if ($d !== '') $set[$d] = true;
    }

    $out = [];
    foreach ($this->criticos() as $r){
      $d = nis_digits((string)($r['NIS'] ?? ''));
      if ($d !== '' && isset($set[$d])) $out[] = $r;
    }
    return $out;
  }

  /** @return array<int,array<string,string>> */
  public function ed_afectados(): array {
    $set = [];
    foreach ($this->clientes_afectados() as $r){
      $nis = get_nis_from_cliente($r);
      $d = nis_digits($nis);
      if ($d !== '') $set[$d] = true;
    }

    $out = [];
    foreach ($this->ed() as $r){
      $d = nis_digits((string)($r['NIS'] ?? ''));
      if ($d !== '' && isset($set[$d])) $out[] = $r;
    }
    return $out;
  }

  private function toFloat(?string $s): ?float {
    $s = trim((string)$s);
    if ($s === '') return null;
    $s = str_replace(',', '.', $s);
    if (!is_numeric($s)) return null;
    return (float)$s;
  }

  /** @return array<int,array{nis:string,comuna:string,status:string,lat:float,lon:float}> */
  public function puntos_clientes_afectados(): array {
    $out = [];
    foreach ($this->clientes_afectados() as $r){
      $x = $this->toFloat($r['X'] ?? null);
      $y = $this->toFloat($r['Y'] ?? null);
      if ($x === null || $y === null) continue;

      $out[] = [
        'nis'    => get_nis_from_cliente($r),
        'comuna' => normalize_comuna((string)($r['CITY'] ?? '')),
        'status' => (string)($r['STATUS'] ?? ($r['POWER_STATUS'] ?? '')),
        'lat'    => $y,
        'lon'    => $x,
      ];
    }
    return $out;
  }

  /** @return array<int,array{comuna:string,clientes:int}> */
  public function last3_comunas_en_falla(): array {
    $clientes = $this->clientes_afectados();
    if (!$clientes) return [];

    $count = [];
    foreach ($clientes as $r){
      $c = normalize_comuna((string)($r['CITY'] ?? ''));
      if ($c === '') $c = 'SIN COMUNA';
      $count[$c] = ($count[$c] ?? 0) + 1;
    }

    usort($clientes, function($a,$b){
      $ta = parse_dt((string)($a['DATE_OF_ACTION'] ?? '')) ?? 0;
      $tb = parse_dt((string)($b['DATE_OF_ACTION'] ?? '')) ?? 0;
      return $tb <=> $ta;
    });

    $seen = [];
    $out = [];
    foreach ($clientes as $r){
      $c = normalize_comuna((string)($r['CITY'] ?? ''));
      if ($c === '') $c = 'SIN COMUNA';
      if (isset($seen[$c])) continue;
      $seen[$c] = true;
      $out[] = ['comuna'=>$c, 'clientes'=>(int)($count[$c] ?? 0)];
      if (count($out) >= 3) break;
    }

    return $out;
  }
}