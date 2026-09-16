<?php

namespace App\Services\Imports;

use App\Models\Contingency;
use App\Models\ImportBatch;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ImportContingencies
{
    public function __construct(private readonly ContingencyImportManager $manager) {}

    public function execute(
        string $source,
        UploadedFile $file,
        string $expectedChecksum,
        User $user,
    ): ImportBatch {
        $adapter = $this->manager->adapter($source);
        $preview = $adapter->preview($file);

        if (! hash_equals($expectedChecksum, $preview->checksum)) {
            throw ValidationException::withMessages([
                'file' => 'El archivo cambió después de la validación. Valídelo nuevamente.',
            ]);
        }

        if (! $preview->canImport()) {
            throw ValidationException::withMessages([
                'file' => 'El archivo presenta un error estructural y no puede importarse.',
            ]);
        }

        $originalName = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'importacion.csv';

        try {
            return DB::transaction(function () use ($adapter, $preview, $user, $originalName): ImportBatch {
                $startedAt = now();
                $batch = ImportBatch::query()->create([
                    'source_name' => $adapter->label(),
                    'synthetic_file_name' => Str::limit($originalName, 180, ''),
                    'status' => match (true) {
                        $preview->rejectedCount() === 0 => 'completed',
                        $preview->acceptedCount() === 0 => 'rejected',
                        default => 'completed_with_warnings',
                    },
                    'total_rows' => $preview->totalRows,
                    'accepted_rows' => $preview->acceptedCount(),
                    'rejected_rows' => $preview->rejectedCount(),
                    'started_at' => $startedAt,
                    'completed_at' => now(),
                    'imported_by' => $user->id,
                ]);

                foreach ($preview->errors as $error) {
                    $batch->errors()->create($error + ['created_at' => now()]);
                }

                foreach ($preview->acceptedRows as $row) {
                    unset($row['source_row_number']);
                    $contingency = Contingency::query()->create($row + [
                        'source_batch_id' => $batch->id,
                        'affected_total' => 0,
                        'critical_affected' => 0,
                        'electrodependent_affected' => 0,
                        'created_by' => $user->id,
                    ]);
                    $contingency->history()->create([
                        'status' => $contingency->status,
                        'note' => 'Contingencia sintética incorporada mediante importación controlada.',
                        'event_at' => $contingency->started_at,
                        'user_id' => $user->id,
                        'source' => 'import',
                        'created_at' => now(),
                    ]);
                }

                return $batch->fresh(['importedBy:id,name']);
            });
        } catch (QueryException $error) {
            if ($this->isUniqueConstraintViolation($error)) {
                throw ValidationException::withMessages([
                    'file' => 'Otra importación incorporó uno de estos códigos. Valide nuevamente el archivo.',
                ]);
            }

            throw $error;
        }
    }

    private function isUniqueConstraintViolation(QueryException $error): bool
    {
        $message = mb_strtolower($error->getMessage(), 'UTF-8');
        $driverCode = (int) ($error->errorInfo[1] ?? 0);

        return (string) $error->getCode() === '23505'
            || $driverCode === 1062
            || str_contains($message, 'unique constraint failed')
            || str_contains($message, 'duplicate entry');
    }
}
