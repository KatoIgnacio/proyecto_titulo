<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contingency_id')->constrained('contingencies')->cascadeOnDelete();
            $table->foreignId('reported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('progress_status', 30);
            $table->text('description');
            $table->dateTime('observed_at');
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->index(['contingency_id', 'observed_at'], 'idx_field_reports_cont_time');
            $table->index(['progress_status', 'observed_at'], 'idx_field_reports_progress_time');
        });

        Schema::create('field_report_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('field_report_id')->constrained('field_reports')->cascadeOnDelete();
            $table->string('disk', 30)->default('local');
            $table->string('path')->unique();
            $table->string('original_name', 180);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_report_attachments');
        Schema::dropIfExists('field_reports');
    }
};
