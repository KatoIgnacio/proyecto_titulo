<?php

namespace App\Services\Contingencies;

use App\Enums\FieldReportProgress;
use App\Models\Contingency;
use App\Models\FieldReport;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class StoreFieldReport
{
    /**
     * @param  array{progress_status: string, description: string, observed_at: string, latitude?: mixed, longitude?: mixed}  $data
     * @param  array<int, UploadedFile>  $attachments
     */
    public function execute(Contingency $contingency, User $user, array $data, array $attachments): FieldReport
    {
        $storedPaths = [];

        try {
            return DB::transaction(function () use ($contingency, $user, $data, $attachments, &$storedPaths): FieldReport {
                $progress = FieldReportProgress::from($data['progress_status']);
                $report = $contingency->fieldReports()->create([
                    'reported_by' => $user->id,
                    'progress_status' => $progress,
                    'description' => $data['description'],
                    'observed_at' => $data['observed_at'],
                    'latitude' => $data['latitude'] ?? null,
                    'longitude' => $data['longitude'] ?? null,
                ]);

                foreach ($attachments as $attachment) {
                    $extension = strtolower($attachment->getClientOriginalExtension());
                    $filename = Str::uuid()->toString().'.'.$extension;
                    $directory = 'field-reports/'.$contingency->id.'/'.$report->id;
                    $checksum = hash_file('sha256', $attachment->getRealPath());

                    if ($checksum === false) {
                        throw new RuntimeException('No fue posible verificar la evidencia de terreno.');
                    }

                    $path = $attachment->storeAs($directory, $filename, 'local');

                    if (! is_string($path) || $path === '') {
                        throw new RuntimeException('No fue posible almacenar la evidencia de terreno.');
                    }

                    $storedPaths[] = $path;
                    $originalName = basename(str_replace('\\', '/', $attachment->getClientOriginalName()));
                    $originalName = preg_replace('/[\x00-\x1F\x7F]/u', '', $originalName) ?: 'evidencia.'.$extension;
                    $report->attachments()->create([
                        'disk' => 'local',
                        'path' => $path,
                        'original_name' => Str::limit($originalName, 180, ''),
                        'mime_type' => (string) $attachment->getMimeType(),
                        'size_bytes' => $attachment->getSize(),
                        'checksum_sha256' => $checksum,
                    ]);
                }

                $contingency->history()->create([
                    'status' => $contingency->status,
                    'note' => Str::limit('Antecedente de terreno: '.$data['description'], 300, ''),
                    'event_at' => $data['observed_at'],
                    'user_id' => $user->id,
                    'source' => 'manual',
                    'created_at' => now(),
                ]);

                return $report->load(['reporter:id,name', 'attachments']);
            });
        } catch (Throwable $error) {
            foreach ($storedPaths as $path) {
                Storage::disk('local')->delete($path);
            }

            throw $error;
        }
    }
}
