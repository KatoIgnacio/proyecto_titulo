<?php

declare(strict_types=1);

const DATASET_KEY = 'luzparral-synthetic-v1';
const GENERATOR_VERSION = '1.0.0';
const DEFAULT_SEED = 20260909;
const DEFAULT_SUPPLY_POINTS = 5000;
const DEFAULT_CONTINGENCIES = 360;

function envValue(string $name, ?string $default = null): ?string
{
    $value = getenv($name);

    return $value === false || $value === '' ? $default : $value;
}

function randomFloat(float $min, float $max): float
{
    return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
}

function randomNormal(): float
{
    $u1 = max(mt_rand() / mt_getrandmax(), 0.000001);
    $u2 = mt_rand() / mt_getrandmax();

    return sqrt(-2.0 * log($u1)) * cos(2.0 * M_PI * $u2);
}

function chooseWeighted(array $weightedValues): string
{
    $total = array_sum($weightedValues);
    $draw = mt_rand(1, $total);
    foreach ($weightedValues as $value => $weight) {
        $draw -= $weight;
        if ($draw <= 0) {
            return (string) $value;
        }
    }

    return (string) array_key_first($weightedValues);
}

function dt(DateTimeImmutable $value): string
{
    return $value->format('Y-m-d H:i:s');
}

function executeSchema(PDO $pdo, string $schemaPath): void
{
    $schema = file_get_contents($schemaPath);
    if ($schema === false) {
        throw new RuntimeException("No se pudo leer el esquema: {$schemaPath}");
    }
    $pdo->exec($schema);
}

function assertSafeDatabase(PDO $pdo, string $database, string $allowedDatabase): void
{
    if ($database !== $allowedDatabase) {
        throw new RuntimeException("Por seguridad, la base {$database} no coincide con LUZPARRAL_DB_ALLOWED_DATABASE.");
    }

    $current = (string) $pdo->query('SELECT DATABASE()')->fetchColumn();
    if ($current !== $database) {
        throw new RuntimeException("La conexion activa apunta a {$current}, no a {$database}.");
    }
}

function clearSyntheticTables(PDO $pdo): void
{
    $existing = (int) $pdo->query('SELECT COUNT(*) FROM dataset_metadata')->fetchColumn();
    if ($existing > 0) {
        $keys = $pdo->query('SELECT dataset_key FROM dataset_metadata')->fetchAll(PDO::FETCH_COLUMN);
        if ($keys !== [DATASET_KEY]) {
            throw new RuntimeException('La base contiene un conjunto no reconocido; no se eliminara ningun dato.');
        }
    } else {
        $tables = ['users', 'communes', 'feeders', 'supply_points', 'import_batches', 'contingencies'];
        foreach ($tables as $table) {
            $count = (int) $pdo->query("SELECT COUNT(*) FROM {$table}")->fetchColumn();
            if ($count > 0) {
                throw new RuntimeException("La tabla {$table} contiene datos sin marcador sintetico; operacion cancelada.");
            }
        }
    }

    $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
    foreach (['field_report_attachments', 'field_reports', 'contingency_history', 'contingency_impacts', 'contingencies', 'import_errors', 'import_batches', 'supply_points', 'feeders', 'communes', 'users', 'dataset_metadata'] as $table) {
        $pdo->exec("TRUNCATE TABLE {$table}");
    }
    $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
}

$options = getopt('', ['seed::', 'supply-points::', 'contingencies::', 'reset']);
$seed = max(1, (int) ($options['seed'] ?? DEFAULT_SEED));
$supplyPointCount = max(500, (int) ($options['supply-points'] ?? DEFAULT_SUPPLY_POINTS));
$contingencyCount = max(50, (int) ($options['contingencies'] ?? DEFAULT_CONTINGENCIES));
$reset = array_key_exists('reset', $options);

$host = envValue('LUZPARRAL_DB_HOST', '127.0.0.1');
$port = envValue('LUZPARRAL_DB_PORT', '3306');
$database = envValue('LUZPARRAL_DB_DATABASE', 'luzparral');
$allowedDatabase = envValue('LUZPARRAL_DB_ALLOWED_DATABASE', 'luzparral');
$username = envValue('LUZPARRAL_DB_USERNAME', 'luzparral_app');
$password = envValue('LUZPARRAL_DB_PASSWORD');
$demoPassword = envValue('LUZPARRAL_DEMO_PASSWORD');

if ($password === null) {
    throw new RuntimeException('Defina LUZPARRAL_DB_PASSWORD antes de ejecutar el generador.');
}

if ($demoPassword === null || strlen($demoPassword) < 12) {
    throw new RuntimeException('Defina LUZPARRAL_DEMO_PASSWORD con al menos 12 caracteres.');
}

$dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
$pdo = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
]);

assertSafeDatabase($pdo, (string) $database, (string) $allowedDatabase);
executeSchema($pdo, __DIR__.DIRECTORY_SEPARATOR.'schema_mysql.sql');

$existingDataset = (int) $pdo->query('SELECT COUNT(*) FROM dataset_metadata')->fetchColumn();
if ($existingDataset > 0 && ! $reset) {
    throw new RuntimeException('Ya existe un conjunto sintetico. Use --reset para regenerarlo de forma controlada.');
}
if ($reset || $existingDataset === 0) {
    clearSyntheticTables($pdo);
}

mt_srand($seed);
$now = new DateTimeImmutable('2026-09-09 12:00:00', new DateTimeZone('UTC'));
$pdo->beginTransaction();

try {
    $userRows = [
        ['Administracion Demo', 'admin@luzparral.example.invalid', 'admin'],
        ['Supervision Demo', 'supervisor@luzparral.example.invalid', 'supervisor'],
        ['Operacion Turno A', 'operador.a@luzparral.example.invalid', 'operator'],
        ['Operacion Turno B', 'operador.b@luzparral.example.invalid', 'operator'],
        ['Consulta Demo', 'consulta@luzparral.example.invalid', 'viewer'],
    ];
    $passwordHash = password_hash($demoPassword, PASSWORD_BCRYPT);
    $insertUser = $pdo->prepare('INSERT INTO users (name, email, email_verified_at, password, role, active, created_at, updated_at) VALUES (?, ?, ?, ?, ?, 1, ?, ?)');
    $userIds = [];
    foreach ($userRows as [$name, $email, $role]) {
        $insertUser->execute([$name, $email, dt($now), $passwordHash, $role, dt($now), dt($now)]);
        $userIds[] = (int) $pdo->lastInsertId();
    }

    $communeDefinitions = [
        'PARRAL' => ['code' => 'COM-01', 'lat' => -36.1430, 'lon' => -71.8260, 'weight' => 38],
        'RETIRO' => ['code' => 'COM-02', 'lat' => -36.0510, 'lon' => -71.7650, 'weight' => 22],
        'LONGAVI' => ['code' => 'COM-03', 'lat' => -35.9650, 'lon' => -71.6840, 'weight' => 20],
        'NIQUEN' => ['code' => 'COM-04', 'lat' => -36.2860, 'lon' => -71.9000, 'weight' => 12],
        'SAN CARLOS' => ['code' => 'COM-05', 'lat' => -36.4240, 'lon' => -71.9580, 'weight' => 8],
    ];
    $insertCommune = $pdo->prepare('INSERT INTO communes (code, name, center_lat, center_lon, active) VALUES (?, ?, ?, ?, 1)');
    $communes = [];
    foreach ($communeDefinitions as $name => $definition) {
        $insertCommune->execute([$definition['code'], $name, $definition['lat'], $definition['lon']]);
        $definition['id'] = (int) $pdo->lastInsertId();
        $communes[$name] = $definition;
    }

    $insertFeeder = $pdo->prepare('INSERT INTO feeders (commune_id, code, name, active, created_at, updated_at) VALUES (?, ?, ?, 1, ?, ?)');
    $feedersByCommune = [];
    $feederNumber = 1;
    foreach ($communes as $communeName => $commune) {
        $feedersByCommune[$communeName] = [];
        for ($local = 1; $local <= 2; $local++) {
            $code = sprintf('SYN-AL-%02d', $feederNumber++);
            $insertFeeder->execute([$commune['id'], $code, "Alimentador sintetico {$communeName} {$local}", dt($now), dt($now)]);
            $feedersByCommune[$communeName][] = (int) $pdo->lastInsertId();
        }
    }

    $communeWeights = [];
    foreach ($communes as $name => $definition) {
        $communeWeights[$name] = $definition['weight'];
    }

    $insertSupply = $pdo->prepare('INSERT INTO supply_points (synthetic_code, customer_code, commune_id, feeder_id, latitude, longitude, criticality, active, installed_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $supplyIdsByFeeder = [];
    for ($i = 1; $i <= $supplyPointCount; $i++) {
        $communeName = chooseWeighted($communeWeights);
        $commune = $communes[$communeName];
        $feederId = $feedersByCommune[$communeName][mt_rand(0, 1)];
        $latitude = $commune['lat'] + randomNormal() * 0.026;
        $longitude = $commune['lon'] + randomNormal() * 0.034;
        $criticalityDraw = mt_rand(1, 1000);
        $criticality = match (true) {
            $criticalityDraw <= 3 => 'critical_electrodependent',
            $criticalityDraw <= 15 => 'electrodependent',
            $criticalityDraw <= 40 => 'critical',
            default => 'normal',
        };
        $active = mt_rand(1, 1000) <= 992 ? 1 : 0;
        $installedAt = $now->sub(new DateInterval('P'.mt_rand(30, 4380).'D'))->format('Y-m-d');
        $insertSupply->execute([
            sprintf('SYN-SP-%06d', $i),
            sprintf('SYN-CL-%06d', $i),
            $commune['id'],
            $feederId,
            round($latitude, 7),
            round($longitude, 7),
            $criticality,
            $active,
            $installedAt,
            dt($now),
            dt($now),
        ]);
        $supplyId = (int) $pdo->lastInsertId();
        $supplyIdsByFeeder[$feederId][] = ['id' => $supplyId, 'criticality' => $criticality];
    }

    $insertBatch = $pdo->prepare('INSERT INTO import_batches (source_name, synthetic_file_name, status, total_rows, accepted_rows, rejected_rows, started_at, completed_at, imported_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $insertImportError = $pdo->prepare('INSERT INTO import_errors (import_batch_id, source_row_number, field_name, error_code, message, synthetic_reference, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $batchIds = [];
    $errorDefinitions = [
        ['MINUTOS', 'NEGATIVE_MINUTES', 'Duracion negativa detectada en registro sintetico'],
        ['X', 'MISSING_COORDINATE', 'Coordenada X ausente en registro sintetico'],
        ['Y', 'MISSING_COORDINATE', 'Coordenada Y ausente en registro sintetico'],
        ['STATUS', 'INVALID_STATUS', 'Estado no reconocido en registro sintetico'],
        ['ENCODING', 'INVALID_ENCODING', 'Caracter no valido para la codificacion esperada'],
    ];
    for ($month = 17; $month >= 0; $month--) {
        $started = $now->sub(new DateInterval("P{$month}M"))->setTime(6, 30);
        $totalRows = mt_rand(800, 1500);
        $rejectedRows = mt_rand(1, 8);
        $completed = $started->add(new DateInterval('PT'.mt_rand(3, 18).'M'));
        $insertBatch->execute([
            'PowerOn sintetico',
            'SYN_POWERON_'.$started->format('Ym').'.txt',
            'completed_with_warnings',
            $totalRows,
            $totalRows - $rejectedRows,
            $rejectedRows,
            dt($started),
            dt($completed),
            $userIds[mt_rand(1, 3)],
            dt($started),
            dt($completed),
        ]);
        $batchId = (int) $pdo->lastInsertId();
        $batchIds[] = $batchId;
        for ($errorIndex = 0; $errorIndex < $rejectedRows; $errorIndex++) {
            $error = $errorDefinitions[array_rand($errorDefinitions)];
            $rowNumber = mt_rand(2, $totalRows + 1);
            $insertImportError->execute([$batchId, $rowNumber, $error[0], $error[1], $error[2], sprintf('SYN-ROW-%06d', $rowNumber), dt($completed)]);
        }
    }

    $insertContingency = $pdo->prepare('INSERT INTO contingencies (code, osf_code, commune_id, feeder_id, source_batch_id, status, priority, cause, description, started_at, estimated_restore_at, restored_at, latitude, longitude, affected_total, critical_affected, electrodependent_affected, created_by, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0, 0, ?, ?, ?)');
    $insertImpact = $pdo->prepare('INSERT INTO contingency_impacts (contingency_id, supply_point_id, status, affected_at, restored_at, outage_minutes, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $insertHistory = $pdo->prepare('INSERT INTO contingency_history (contingency_id, status, note, event_at, user_id, source, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $insertFieldReport = $pdo->prepare('INSERT INTO field_reports (contingency_id, reported_by, progress_status, description, observed_at, latitude, longitude, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $updateContingency = $pdo->prepare('UPDATE contingencies SET priority = ?, affected_total = ?, critical_affected = ?, electrodependent_affected = ?, updated_at = ? WHERE id = ?');

    $causes = ['weather', 'vegetation', 'equipment_failure', 'vehicle_collision', 'third_party', 'unknown'];
    $openStatuses = ['reported', 'assigned', 'in_progress'];
    $finalStatuses = ['restored', 'closed'];
    $allFeederIds = array_merge(...array_values($feedersByCommune));
    $communeNameByFeeder = [];
    foreach ($feedersByCommune as $communeName => $ids) {
        foreach ($ids as $id) {
            $communeNameByFeeder[$id] = $communeName;
        }
    }

    $totalImpacts = 0;
    $totalHistory = 0;
    $totalFieldReports = 0;
    $openContingencies = max(12, (int) round($contingencyCount * 0.05));

    for ($i = 1; $i <= $contingencyCount; $i++) {
        $isOpen = $i <= $openContingencies;
        if ($isOpen) {
            $startedAt = $now->sub(new DateInterval('PT'.mt_rand(1, 72).'H'));
            $status = $openStatuses[array_rand($openStatuses)];
        } else {
            $startedAt = $now->sub(new DateInterval('P'.mt_rand(4, 365).'D'))->sub(new DateInterval('PT'.mt_rand(0, 23).'H'));
            $status = $finalStatuses[array_rand($finalStatuses)];
        }

        $feederId = $allFeederIds[array_rand($allFeederIds)];
        $communeName = $communeNameByFeeder[$feederId];
        $commune = $communes[$communeName];
        $estimateHours = mt_rand(2, 12);
        $estimatedRestoreAt = $startedAt->add(new DateInterval("PT{$estimateHours}H"));
        $restoredAt = null;
        if (! $isOpen) {
            $restoredAt = $startedAt->add(new DateInterval('PT'.mt_rand(35, 720).'M'));
        }

        $code = sprintf('SYN-CONT-%06d', $i);
        $osfCode = sprintf('SYN-OSF-%06d', $i);
        $latitude = $commune['lat'] + randomNormal() * 0.018;
        $longitude = $commune['lon'] + randomNormal() * 0.024;
        $cause = $causes[array_rand($causes)];
        $batchId = $batchIds[array_rand($batchIds)];
        $createdBy = $userIds[mt_rand(1, 3)];

        $insertContingency->execute([
            $code,
            $osfCode,
            $commune['id'],
            $feederId,
            $batchId,
            $status,
            'low',
            $cause,
            "Contingencia sintetica {$code}; no representa un evento real.",
            dt($startedAt),
            dt($estimatedRestoreAt),
            $restoredAt ? dt($restoredAt) : null,
            round($latitude, 7),
            round($longitude, 7),
            $createdBy,
            dt($startedAt),
            dt($restoredAt ?? $startedAt),
        ]);
        $contingencyId = (int) $pdo->lastInsertId();

        $availableSupplies = $supplyIdsByFeeder[$feederId];
        $affectedCount = min(count($availableSupplies), mt_rand(12, 125));
        $selectedIndexes = array_rand($availableSupplies, $affectedCount);
        if (! is_array($selectedIndexes)) {
            $selectedIndexes = [$selectedIndexes];
        }

        $criticalAffected = 0;
        $electrodependentAffected = 0;
        foreach ($selectedIndexes as $selectedIndex) {
            $supply = $availableSupplies[$selectedIndex];
            $affectedAt = $startedAt->add(new DateInterval('PT'.mt_rand(0, 15).'M'));
            $impactRestoredAt = null;
            $outageMinutes = null;
            $impactStatus = 'affected';
            if ($restoredAt !== null) {
                $impactRestoredAt = $restoredAt->add(new DateInterval('PT'.mt_rand(0, 20).'M'));
                $outageMinutes = max(0, (int) round(($impactRestoredAt->getTimestamp() - $affectedAt->getTimestamp()) / 60));
                $impactStatus = 'restored';
            }
            $insertImpact->execute([
                $contingencyId,
                $supply['id'],
                $impactStatus,
                dt($affectedAt),
                $impactRestoredAt ? dt($impactRestoredAt) : null,
                $outageMinutes,
                dt($affectedAt),
                dt($impactRestoredAt ?? $affectedAt),
            ]);
            $totalImpacts++;
            if (in_array($supply['criticality'], ['critical', 'critical_electrodependent'], true)) {
                $criticalAffected++;
            }
            if (in_array($supply['criticality'], ['electrodependent', 'critical_electrodependent'], true)) {
                $electrodependentAffected++;
            }
        }

        $priority = match (true) {
            $electrodependentAffected >= 4 || $criticalAffected >= 7 || $affectedCount >= 120 => 'critical',
            $electrodependentAffected >= 2 || $criticalAffected >= 4 || $affectedCount >= 90 => 'high',
            $electrodependentAffected > 0 || $criticalAffected > 0 || $affectedCount >= 40 => 'medium',
            default => 'low',
        };
        $updateContingency->execute([$priority, $affectedCount, $criticalAffected, $electrodependentAffected, dt($restoredAt ?? $startedAt), $contingencyId]);

        $historyRows = [
            ['reported', 'Contingencia sintetica registrada por importacion.', $startedAt, null, 'import'],
        ];
        if ($status !== 'reported') {
            $historyRows[] = ['assigned', 'Asignacion sintetica a equipo de operacion.', $startedAt->add(new DateInterval('PT10M')), $createdBy, 'user'];
        }
        if (in_array($status, ['in_progress', 'restored', 'closed'], true)) {
            $historyRows[] = ['in_progress', 'Analisis sintetico de la contingencia en curso.', $startedAt->add(new DateInterval('PT25M')), $createdBy, 'user'];
        }
        if ($restoredAt !== null) {
            $historyRows[] = ['restored', 'Suministro restablecido en escenario sintetico.', $restoredAt, $createdBy, 'system'];
            if ($status === 'closed') {
                $historyRows[] = ['closed', 'Contingencia sintetica cerrada y disponible para informe.', $restoredAt->add(new DateInterval('PT15M')), $userIds[1], 'user'];
            }
        }
        foreach ($historyRows as [$historyStatus, $note, $eventAt, $historyUser, $source]) {
            $insertHistory->execute([$contingencyId, $historyStatus, $note, dt($eventAt), $historyUser, $source, dt($eventAt)]);
            $totalHistory++;
        }

        $fieldReportCount = $i <= min(120, $contingencyCount) ? ($i % 5 === 0 ? 2 : 1) : 0;
        $fieldReportDescriptions = [
            'Inspeccion sintetica realizada; se verifican condiciones del sector y elementos de la red.',
            'Brigada sintetica informa avance de reparacion y coordinacion de recursos en terreno.',
            'Trabajo sintetico completado; se registran verificaciones previas a la reposicion.',
        ];
        for ($reportIndex = 1; $reportIndex <= $fieldReportCount; $reportIndex++) {
            $windowEnd = $restoredAt ?? $now;
            $windowSeconds = max(60, $windowEnd->getTimestamp() - $startedAt->getTimestamp());
            $isLastReport = $reportIndex === $fieldReportCount;
            $reportAt = $isLastReport && $restoredAt !== null
                ? $restoredAt
                : $startedAt->add(new DateInterval('PT'.max(60, (int) round($windowSeconds * $reportIndex / ($fieldReportCount + 1))).'S'));
            $progress = match (true) {
                $isLastReport && $restoredAt !== null => 'completed',
                $status === 'in_progress' || $reportIndex > 1 => 'repair',
                default => 'inspection',
            };
            $description = match ($progress) {
                'completed' => $fieldReportDescriptions[2],
                'repair' => $fieldReportDescriptions[1],
                default => $fieldReportDescriptions[0],
            };
            $reportLatitude = round($latitude + ((($i * 13 + $reportIndex * 7) % 17) - 8) * 0.0001, 7);
            $reportLongitude = round($longitude + ((($i * 11 + $reportIndex * 5) % 17) - 8) * 0.0001, 7);

            $insertFieldReport->execute([
                $contingencyId,
                $createdBy,
                $progress,
                $description,
                dt($reportAt),
                $reportLatitude,
                $reportLongitude,
                dt($reportAt),
                dt($reportAt),
            ]);
            $totalFieldReports++;

            $reportHistoryStatus = match ($progress) {
                'completed' => 'restored',
                'repair' => 'in_progress',
                default => $status === 'assigned' ? 'assigned' : 'reported',
            };
            $insertHistory->execute([
                $contingencyId,
                $reportHistoryStatus,
                'Antecedente de terreno sintetico incorporado al expediente.',
                dt($reportAt),
                $createdBy,
                'synthetic',
                dt($reportAt),
            ]);
            $totalHistory++;
        }
    }

    $parameters = json_encode([
        'users' => count($userRows),
        'communes' => count($communes),
        'feeders' => count($allFeederIds),
        'supply_points' => $supplyPointCount,
        'contingencies' => $contingencyCount,
        'impacts' => $totalImpacts,
        'history_events' => $totalHistory,
        'field_reports' => $totalFieldReports,
        'import_batches' => count($batchIds),
        'anchor_utc' => dt($now),
    ], JSON_THROW_ON_ERROR);

    $insertMetadata = $pdo->prepare('INSERT INTO dataset_metadata (dataset_key, generator_version, random_seed, generated_at, parameters_json, notes) VALUES (?, ?, ?, ?, ?, ?)');
    $insertMetadata->execute([
        DATASET_KEY,
        GENERATOR_VERSION,
        $seed,
        dt($now),
        $parameters,
        'Datos completamente sinteticos para desarrollo, pruebas y demostracion academica.',
    ]);

    $pdo->commit();

    echo json_encode([
        'dataset_key' => DATASET_KEY,
        'seed' => $seed,
        'users' => count($userRows),
        'communes' => count($communes),
        'feeders' => count($allFeederIds),
        'supply_points' => $supplyPointCount,
        'contingencies' => $contingencyCount,
        'impacts' => $totalImpacts,
        'history_events' => $totalHistory,
        'field_reports' => $totalFieldReports,
        'import_batches' => count($batchIds),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $error;
}
