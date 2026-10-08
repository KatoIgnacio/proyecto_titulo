<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->where('role', 'guest')
            ->update(['role' => 'viewer']);
    }

    public function down(): void
    {
        // La consolidación de roles es intencionalmente irreversible:
        // Consulta identifica tanto cuentas permanentes como temporales.
    }
};
