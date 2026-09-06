<?php
declare(strict_types=1);

$cfg = require __DIR__ . '/../../src/config.php';
$filename = $_GET['file'] ?? ($cfg['FILES']['KML'] ?? 'redes.kml');
$path = rtrim($cfg['DATA_DIR'], "\\/") . DIRECTORY_SEPARATOR . basename($filename);$path = rtrim($cfg['DATA_DIR'], "\\/") . DIRECTORY_SEPARATOR . ($cfg['FILES']['KML'] ?? 'redes.kml');

if (!is_file($path)) {
  http_response_code(404);
  header('Content-Type: text/plain; charset=utf-8');
  echo "KML no encontrado";
  exit;
}

$mtime = filemtime($path) ?: time();
$size  = filesize($path) ?: 0;

// ETag estable por mtime+size 
$etag = '"' . sha1($mtime . ':' . $size) . '"';

header('Content-Type: application/vnd.google-earth.kml+xml; charset=utf-8');
header('Cache-Control: public, max-age=300'); // 5 min 
header('ETag: ' . $etag);
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $mtime) . ' GMT');

// 304 Not Modified
$ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
$ifModified  = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';

if ($ifNoneMatch === $etag) {
  http_response_code(304);
  exit;
}

if ($ifModified) {
  $since = strtotime($ifModified);
  if ($since !== false && $since >= $mtime) {
    http_response_code(304);
    exit;
  }
}

// Output file
readfile($path);