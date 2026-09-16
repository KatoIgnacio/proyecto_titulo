<?php

namespace App\Services\Imports;

use App\Contracts\ContingencyImportAdapter;
use App\Data\ContingencyImportPreview;
use App\Enums\ContingencyStatus;
use App\Models\Commune;
use App\Models\Contingency;
use App\Models\Feeder;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Http\UploadedFile;
use RuntimeException;
use Throwable;

class SyntheticCsvContingencyAdapter implements ContingencyImportAdapter
{
    private const MAX_ROWS = 1000;

    private const HEADERS = [
        'codigo',
        'osf',
        'comuna',
        'alimentador',
        'estado',
        'prioridad',
        'causa',
        'descripcion',
        'inicio',
        'reposicion_estimada',
        'reposicion_efectiva',
        'latitud',
        'longitud',
    ];

    private const PRIORITIES = ['critical', 'high', 'medium', 'low'];

    private const CAUSES = ['weather', 'vegetation', 'equipment_failure', 'vehicle_collision', 'third_party', 'unknown'];

    public function key(): string
    {
        return 'synthetic_csv_v1';
    }

    public function label(): string
    {
        return 'CSV sintético SIGCEL v1';
    }

    public function preview(UploadedFile $file): ContingencyImportPreview
    {
        $path = $file->getRealPath();
        $checksum = hash_file('sha256', $path);
        $rawContents = file_get_contents($path);

        if ($checksum === false || $rawContents === false) {
            throw new RuntimeException('No fue posible leer el archivo de importación.');
        }

        [$contents, $encoding] = $this->normalizeEncoding($rawContents);
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
        $headerIndex = $this->firstNonEmptyLineIndex($lines);

        if ($headerIndex === null) {
            return new ContingencyImportPreview(
                $checksum,
                $encoding,
                ';',
                0,
                [],
                [$this->error(1, 'header', 'EMPTY_FILE', 'El archivo no contiene una cabecera ni registros.')],
                true,
            );
        }

        $delimiter = $this->detectDelimiter($lines[$headerIndex]);
        $headers = array_map(
            static fn (string $header): string => mb_strtolower(trim($header), 'UTF-8'),
            str_getcsv($lines[$headerIndex], $delimiter, '"', ''),
        );
        $dataLines = array_slice($lines, $headerIndex + 1, null, true);
        $totalRows = count(array_filter($dataLines, static fn (string $line): bool => trim($line) !== ''));

        $missingHeaders = array_values(array_diff(self::HEADERS, $headers));
        $duplicateHeaders = array_keys(array_filter(array_count_values($headers), static fn (int $count): bool => $count > 1));
        if ($missingHeaders !== [] || $duplicateHeaders !== []) {
            $details = [];
            if ($missingHeaders !== []) {
                $details[] = 'faltan '.implode(', ', $missingHeaders);
            }
            if ($duplicateHeaders !== []) {
                $details[] = 'se repiten '.implode(', ', $duplicateHeaders);
            }

            return new ContingencyImportPreview(
                $checksum,
                $encoding,
                $delimiter,
                $totalRows,
                [],
                [$this->error($headerIndex + 1, 'header', 'INVALID_HEADER', 'Cabecera inválida: '.implode('; ', $details).'.')],
                true,
            );
        }

        if ($totalRows > self::MAX_ROWS) {
            return new ContingencyImportPreview(
                $checksum,
                $encoding,
                $delimiter,
                $totalRows,
                [],
                [$this->error($headerIndex + 1, 'file', 'ROW_LIMIT_EXCEEDED', 'El archivo supera el máximo de '.self::MAX_ROWS.' registros.')],
                true,
            );
        }

        $communes = Commune::query()->where('active', true)->get(['id', 'code'])->keyBy(
            static fn (Commune $commune): string => mb_strtoupper($commune->code, 'UTF-8'),
        );
        $feeders = Feeder::query()->where('active', true)->get(['id', 'commune_id', 'code'])->keyBy(
            static fn (Feeder $feeder): string => mb_strtoupper($feeder->code, 'UTF-8'),
        );
        $seenCodes = [];
        $seenOsfCodes = [];
        $candidates = [];
        $errors = [];

        foreach ($dataLines as $lineIndex => $line) {
            if (trim($line) === '') {
                continue;
            }

            $sourceRow = $lineIndex + 1;
            $values = str_getcsv($line, $delimiter, '"', '');
            if (count($values) !== count($headers)) {
                $errors[] = $this->error($sourceRow, 'row', 'COLUMN_COUNT', 'La cantidad de columnas no coincide con la cabecera.');

                continue;
            }

            /** @var array<string, string> $record */
            $record = array_combine($headers, array_map(static fn (string $value): string => trim($value), $values));
            $rowErrors = [];
            $code = mb_strtoupper($record['codigo'], 'UTF-8');
            $osfCode = mb_strtoupper($record['osf'], 'UTF-8');
            $reference = preg_match('/^SYN-[A-Z0-9-]+$/', $code) === 1
                ? $code
                : sprintf('SYN-ROW-%06d', $sourceRow);

            if (preg_match('/^SYN-[A-Z0-9-]{3,35}$/', $code) !== 1) {
                $rowErrors[] = $this->error($sourceRow, 'codigo', 'INVALID_SYNTHETIC_CODE', 'El código debe comenzar con SYN- y usar solo letras, números o guiones.', $reference);
            } elseif (isset($seenCodes[$code])) {
                $rowErrors[] = $this->error($sourceRow, 'codigo', 'DUPLICATE_IN_FILE', 'El código ya apareció en este archivo.', $reference);
            }
            $seenCodes[$code] = true;

            if (preg_match('/^SYN-[A-Z0-9-]{3,35}$/', $osfCode) !== 1) {
                $rowErrors[] = $this->error($sourceRow, 'osf', 'INVALID_SYNTHETIC_OSF', 'El código OSF debe comenzar con SYN-.', $reference);
            } elseif (isset($seenOsfCodes[$osfCode])) {
                $rowErrors[] = $this->error($sourceRow, 'osf', 'DUPLICATE_IN_FILE', 'El código OSF ya apareció en este archivo.', $reference);
            }
            $seenOsfCodes[$osfCode] = true;

            $communeCode = mb_strtoupper($record['comuna'], 'UTF-8');
            $feederCode = mb_strtoupper($record['alimentador'], 'UTF-8');
            $commune = $communes->get($communeCode);
            $feeder = $feeders->get($feederCode);
            if (! $commune) {
                $rowErrors[] = $this->error($sourceRow, 'comuna', 'UNKNOWN_COMMUNE', 'La comuna no está registrada o se encuentra inactiva.', $reference);
            }
            if (! $feeder) {
                $rowErrors[] = $this->error($sourceRow, 'alimentador', 'UNKNOWN_FEEDER', 'El alimentador no está registrado o se encuentra inactivo.', $reference);
            } elseif ($commune && $feeder->commune_id !== $commune->id) {
                $rowErrors[] = $this->error($sourceRow, 'alimentador', 'FEEDER_COMMUNE_MISMATCH', 'El alimentador no pertenece a la comuna indicada.', $reference);
            }

            $status = ContingencyStatus::tryFrom($record['estado']);
            if (! $status) {
                $rowErrors[] = $this->error($sourceRow, 'estado', 'INVALID_STATUS', 'El estado no pertenece al flujo de contingencias.', $reference);
            }
            if (! in_array($record['prioridad'], self::PRIORITIES, true)) {
                $rowErrors[] = $this->error($sourceRow, 'prioridad', 'INVALID_PRIORITY', 'La prioridad no está permitida.', $reference);
            }
            if (! in_array($record['causa'], self::CAUSES, true)) {
                $rowErrors[] = $this->error($sourceRow, 'causa', 'INVALID_CAUSE', 'La causa no está permitida.', $reference);
            }
            if (mb_strlen($record['descripcion'], 'UTF-8') < 10 || mb_strlen($record['descripcion'], 'UTF-8') > 300) {
                $rowErrors[] = $this->error($sourceRow, 'descripcion', 'INVALID_DESCRIPTION', 'La descripción debe contener entre 10 y 300 caracteres.', $reference);
            }

            $startedAt = $this->parseDate($record['inicio']);
            $estimatedRestoreAt = $this->parseOptionalDate($record['reposicion_estimada']);
            $restoredAt = $this->parseOptionalDate($record['reposicion_efectiva']);
            if ($startedAt === null) {
                $rowErrors[] = $this->error($sourceRow, 'inicio', 'INVALID_DATE', 'La fecha de inicio no tiene un formato válido.', $reference);
            }
            if ($record['reposicion_estimada'] !== '' && $estimatedRestoreAt === null) {
                $rowErrors[] = $this->error($sourceRow, 'reposicion_estimada', 'INVALID_DATE', 'La reposición estimada no tiene un formato válido.', $reference);
            }
            if ($record['reposicion_efectiva'] !== '' && $restoredAt === null) {
                $rowErrors[] = $this->error($sourceRow, 'reposicion_efectiva', 'INVALID_DATE', 'La reposición efectiva no tiene un formato válido.', $reference);
            }
            if ($startedAt && $estimatedRestoreAt && $estimatedRestoreAt < $startedAt) {
                $rowErrors[] = $this->error($sourceRow, 'reposicion_estimada', 'INVALID_CHRONOLOGY', 'La reposición estimada no puede ser anterior al inicio.', $reference);
            }
            if ($startedAt && $restoredAt && $restoredAt < $startedAt) {
                $rowErrors[] = $this->error($sourceRow, 'reposicion_efectiva', 'INVALID_CHRONOLOGY', 'La reposición efectiva no puede ser anterior al inicio.', $reference);
            }
            if ($status && in_array($status, [ContingencyStatus::Restored, ContingencyStatus::Closed], true) && $restoredAt === null) {
                $rowErrors[] = $this->error($sourceRow, 'reposicion_efectiva', 'MISSING_RESTORATION', 'Los estados repuesta y cerrada requieren fecha de reposición efectiva.', $reference);
            }

            $latitude = filter_var($record['latitud'], FILTER_VALIDATE_FLOAT);
            $longitude = filter_var($record['longitud'], FILTER_VALIDATE_FLOAT);
            if ($latitude === false || $latitude < -37.0 || $latitude > -35.0) {
                $rowErrors[] = $this->error($sourceRow, 'latitud', 'INVALID_COORDINATE', 'La latitud debe estar dentro del área sintética permitida.', $reference);
            }
            if ($longitude === false || $longitude < -72.5 || $longitude > -71.0) {
                $rowErrors[] = $this->error($sourceRow, 'longitud', 'INVALID_COORDINATE', 'La longitud debe estar dentro del área sintética permitida.', $reference);
            }

            if ($rowErrors !== []) {
                array_push($errors, ...$rowErrors);

                continue;
            }

            $candidates[] = [
                'source_row_number' => $sourceRow,
                'code' => $code,
                'osf_code' => $osfCode,
                'commune_id' => $commune->id,
                'feeder_id' => $feeder->id,
                'status' => $status->value,
                'priority' => $record['prioridad'],
                'cause' => $record['causa'],
                'description' => $record['descripcion'],
                'started_at' => $startedAt,
                'estimated_restore_at' => $estimatedRestoreAt,
                'restored_at' => $restoredAt,
                'latitude' => $latitude,
                'longitude' => $longitude,
            ];
        }

        $existingCodes = Contingency::query()
            ->whereIn('code', array_column($candidates, 'code'))
            ->pluck('code')
            ->flip();
        $existingOsfCodes = Contingency::query()
            ->whereIn('osf_code', array_column($candidates, 'osf_code'))
            ->pluck('osf_code')
            ->flip();
        $acceptedRows = [];

        foreach ($candidates as $candidate) {
            $duplicateErrors = [];
            if ($existingCodes->has($candidate['code'])) {
                $duplicateErrors[] = $this->error($candidate['source_row_number'], 'codigo', 'DUPLICATE_DATABASE', 'La contingencia ya existe en la base.', $candidate['code']);
            }
            if ($existingOsfCodes->has($candidate['osf_code'])) {
                $duplicateErrors[] = $this->error($candidate['source_row_number'], 'osf', 'DUPLICATE_DATABASE', 'El código OSF ya existe en la base.', $candidate['code']);
            }

            if ($duplicateErrors !== []) {
                array_push($errors, ...$duplicateErrors);
            } else {
                $acceptedRows[] = $candidate;
            }
        }

        return new ContingencyImportPreview(
            $checksum,
            $encoding,
            $delimiter,
            $totalRows,
            $acceptedRows,
            $errors,
        );
    }

    /** @return array{string, string} */
    private function normalizeEncoding(string $contents): array
    {
        if (str_starts_with($contents, "\xEF\xBB\xBF")) {
            $contents = substr($contents, 3);
        }

        if (mb_check_encoding($contents, 'UTF-8')) {
            return [$contents, 'UTF-8'];
        }

        $converted = mb_convert_encoding($contents, 'UTF-8', 'Windows-1252');
        if (! mb_check_encoding($converted, 'UTF-8')) {
            throw new RuntimeException('El archivo no utiliza UTF-8 ni Windows-1252 válidos.');
        }

        return [$converted, 'Windows-1252'];
    }

    /** @param list<string> $lines */
    private function firstNonEmptyLineIndex(array $lines): ?int
    {
        foreach ($lines as $index => $line) {
            if (trim($line) !== '') {
                return $index;
            }
        }

        return null;
    }

    private function detectDelimiter(string $header): string
    {
        $counts = [];
        foreach ([';' => ';', ',' => ',', "\t" => "\t"] as $key => $delimiter) {
            $counts[$key] = count(str_getcsv($header, $delimiter, '"', ''));
        }

        arsort($counts);

        return (string) array_key_first($counts);
    }

    private function parseOptionalDate(string $value): ?string
    {
        return $value === '' ? null : $this->parseDate($value);
    }

    private function parseDate(string $value): ?string
    {
        $timezone = new DateTimeZone((string) config('app.timezone'));
        foreach (['Y-m-d H:i:s', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i'] as $format) {
            try {
                $date = DateTimeImmutable::createFromFormat('!'.$format, $value, $timezone);
                $dateErrors = DateTimeImmutable::getLastErrors();
                if ($date && ($dateErrors === false || ($dateErrors['warning_count'] === 0 && $dateErrors['error_count'] === 0)) && $date->format($format) === $value) {
                    return $date->format('Y-m-d H:i:s');
                }
            } catch (Throwable) {
                // Continue with the next accepted format.
            }
        }

        return null;
    }

    /** @return array{source_row_number: int, field_name: ?string, error_code: string, message: string, synthetic_reference: string} */
    private function error(
        int $row,
        ?string $field,
        string $code,
        string $message,
        ?string $reference = null,
    ): array {
        return [
            'source_row_number' => $row,
            'field_name' => $field,
            'error_code' => $code,
            'message' => $message,
            'synthetic_reference' => $reference ?? sprintf('SYN-ROW-%06d', $row),
        ];
    }
}
