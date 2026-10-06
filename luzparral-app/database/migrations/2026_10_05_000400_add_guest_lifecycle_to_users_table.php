<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('active');
            $table->foreignId('created_by')->nullable()->after('expires_at')->constrained('users')->nullOnDelete();
            $table->index(['role', 'active', 'expires_at'], 'idx_users_guest_lifecycle');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_guest_lifecycle');
            $table->dropConstrainedForeignId('created_by');
            $table->dropColumn('expires_at');
        });
    }
};
