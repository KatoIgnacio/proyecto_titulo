<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('feeders')) {
            DB::table('feeders')
                ->where('code', 'like', 'SYN-AL-%')
                ->where('name', 'like', 'Alimentador sintetico %')
                ->get(['id', 'name'])
                ->each(fn (object $feeder) => DB::table('feeders')
                    ->where('id', $feeder->id)
                    ->update(['name' => str_replace('Alimentador sintetico ', 'Alimentador ', $feeder->name)]));
        }

        if (Schema::hasTable('import_batches')) {
            DB::table('import_batches')
                ->where('source_name', 'PowerOn sintetico')
                ->where('synthetic_file_name', 'like', 'SYN_POWERON_%')
                ->update(['source_name' => 'PowerOn']);
        }

        if (Schema::hasTable('import_errors')) {
            $errorMessages = [
                'Duracion negativa detectada en registro sintetico' => 'Duración negativa detectada en el registro',
                'Coordenada X ausente en registro sintetico' => 'Coordenada X ausente en el registro',
                'Coordenada Y ausente en registro sintetico' => 'Coordenada Y ausente en el registro',
                'Estado no reconocido en registro sintetico' => 'Estado no reconocido en el registro',
            ];

            foreach ($errorMessages as $old => $new) {
                DB::table('import_errors')->where('message', $old)->update(['message' => $new]);
            }
        }

        if (! Schema::hasTable('contingencies')) {
            return;
        }

        $descriptions = [
            'weather' => 'Interrupción asociada a condiciones meteorológicas.',
            'vegetation' => 'Interrupción asociada a vegetación sobre la red.',
            'equipment_failure' => 'Interrupción asociada a falla de equipamiento.',
            'vehicle_collision' => 'Interrupción asociada a colisión vehicular.',
            'third_party' => 'Interrupción asociada a intervención de terceros.',
            'unknown' => 'Causa de interrupción en evaluación.',
        ];

        DB::table('contingencies')
            ->where('code', 'like', 'SYN-CONT-%')
            ->where('description', 'like', 'Contingencia sintetica %; no representa un evento real.')
            ->get(['id', 'cause'])
            ->each(fn (object $contingency) => DB::table('contingencies')
                ->where('id', $contingency->id)
                ->update(['description' => $descriptions[$contingency->cause] ?? $descriptions['unknown']]));

        $contingencyIds = DB::table('contingencies')
            ->where('code', 'like', 'SYN-CONT-%')
            ->pluck('id')
            ->all();

        if ($contingencyIds === []) {
            return;
        }

        if (Schema::hasTable('contingency_history')) {
            $historyNotes = [
                'Contingencia sintetica registrada por importacion.' => 'Contingencia registrada por importación.',
                'Asignacion sintetica a equipo de operacion.' => 'Asignación a equipo de operación.',
                'Analisis sintetico de la contingencia en curso.' => 'Análisis de la contingencia en curso.',
                'Suministro restablecido en escenario sintetico.' => 'Suministro restablecido.',
                'Contingencia sintetica cerrada y disponible para informe.' => 'Contingencia cerrada y disponible para informe.',
                'Antecedente de terreno sintetico incorporado al expediente.' => 'Antecedente de terreno incorporado al expediente.',
            ];

            foreach ($historyNotes as $old => $new) {
                DB::table('contingency_history')
                    ->whereIn('contingency_id', $contingencyIds)
                    ->where('note', $old)
                    ->update(['note' => $new]);
            }
        }

        if (Schema::hasTable('field_reports')) {
            $fieldReportDescriptions = [
                'Inspeccion sintetica realizada; se verifican condiciones del sector y elementos de la red.' => 'Inspección realizada; se verifican condiciones del sector y elementos de la red.',
                'Brigada sintetica informa avance de reparacion y coordinacion de recursos en terreno.' => 'La brigada informa avance de reparación y coordinación de recursos en terreno.',
                'Trabajo sintetico completado; se registran verificaciones previas a la reposicion.' => 'Trabajo completado; se registran verificaciones previas a la reposición.',
            ];

            foreach ($fieldReportDescriptions as $old => $new) {
                DB::table('field_reports')
                    ->whereIn('contingency_id', $contingencyIds)
                    ->where('description', $old)
                    ->update(['description' => $new]);
            }
        }
    }

    public function down(): void
    {
        // La limpieza es deliberadamente irreversible para no sobrescribir
        // textos que un usuario pueda editar después de ejecutar la migración.
    }
};
