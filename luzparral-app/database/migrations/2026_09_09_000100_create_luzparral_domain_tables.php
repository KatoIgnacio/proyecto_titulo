<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dataset_metadata', function (Blueprint $table) {
            $table->id();
            $table->string('dataset_key', 80)->unique();
            $table->string('generator_version', 20);
            $table->unsignedInteger('random_seed');
            $table->dateTime('generated_at');
            $table->json('parameters_json');
            $table->string('notes', 500);
        });

        Schema::create('communes', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->string('name', 100)->unique();
            $table->decimal('center_lat', 10, 7);
            $table->decimal('center_lon', 10, 7);
            $table->boolean('active')->default(true);
        });

        Schema::create('feeders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('commune_id')->constrained('communes');
            $table->string('code', 30)->unique();
            $table->string('name', 120);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['commune_id', 'active'], 'idx_feeders_commune_active');
        });

        Schema::create('supply_points', function (Blueprint $table) {
            $table->id();
            $table->string('synthetic_code', 40)->unique();
            $table->string('customer_code', 40)->unique();
            $table->foreignId('commune_id')->constrained('communes');
            $table->foreignId('feeder_id')->constrained('feeders');
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->string('criticality', 35)->default('normal');
            $table->boolean('active')->default(true);
            $table->date('installed_at');
            $table->timestamps();
            $table->index(['commune_id', 'feeder_id', 'criticality', 'active'], 'idx_supply_filters');
            $table->index(['latitude', 'longitude'], 'idx_supply_coordinates');
        });

        Schema::create('import_batches', function (Blueprint $table) {
            $table->id();
            $table->string('source_name', 80);
            $table->string('synthetic_file_name', 180);
            $table->string('status', 25);
            $table->unsignedInteger('total_rows');
            $table->unsignedInteger('accepted_rows');
            $table->unsignedInteger('rejected_rows');
            $table->dateTime('started_at');
            $table->dateTime('completed_at')->nullable();
            $table->foreignId('imported_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['started_at', 'status'], 'idx_batches_date_status');
        });

        Schema::create('import_errors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('import_batch_id')->constrained('import_batches')->cascadeOnDelete();
            $table->unsignedInteger('source_row_number');
            $table->string('field_name', 80)->nullable();
            $table->string('error_code', 50);
            $table->string('message', 300);
            $table->string('synthetic_reference', 80)->nullable();
            $table->dateTime('created_at');
            $table->index(['import_batch_id', 'error_code'], 'idx_errors_batch_code');
        });

        Schema::create('contingencies', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('osf_code', 40)->unique();
            $table->foreignId('commune_id')->constrained('communes');
            $table->foreignId('feeder_id')->constrained('feeders');
            $table->foreignId('source_batch_id')->nullable()->constrained('import_batches')->nullOnDelete();
            $table->string('status', 30);
            $table->string('priority', 20);
            $table->string('cause', 60);
            $table->string('description', 300);
            $table->dateTime('started_at');
            $table->dateTime('estimated_restore_at')->nullable();
            $table->dateTime('restored_at')->nullable();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('affected_total')->default(0);
            $table->unsignedInteger('critical_affected')->default(0);
            $table->unsignedInteger('electrodependent_affected')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['started_at', 'commune_id', 'status', 'priority', 'feeder_id'], 'idx_cont_filters');
            $table->index(['latitude', 'longitude'], 'idx_cont_coordinates');
        });

        Schema::create('contingency_impacts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contingency_id')->constrained('contingencies')->cascadeOnDelete();
            $table->foreignId('supply_point_id')->constrained('supply_points');
            $table->string('status', 25);
            $table->dateTime('affected_at');
            $table->dateTime('restored_at')->nullable();
            $table->unsignedInteger('outage_minutes')->nullable();
            $table->timestamps();
            $table->unique(['contingency_id', 'supply_point_id'], 'uq_impact_cont_supply');
            $table->index(['status', 'affected_at', 'restored_at'], 'idx_impacts_status_time');
            $table->index(['supply_point_id', 'affected_at'], 'idx_impacts_supply');
        });

        Schema::create('contingency_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contingency_id')->constrained('contingencies')->cascadeOnDelete();
            $table->string('status', 30);
            $table->string('note', 300);
            $table->dateTime('event_at');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('source', 25);
            $table->dateTime('created_at');
            $table->index(['contingency_id', 'event_at'], 'idx_history_cont_time');
            $table->index('event_at', 'idx_history_event_time');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contingency_history');
        Schema::dropIfExists('contingency_impacts');
        Schema::dropIfExists('contingencies');
        Schema::dropIfExists('import_errors');
        Schema::dropIfExists('import_batches');
        Schema::dropIfExists('supply_points');
        Schema::dropIfExists('feeders');
        Schema::dropIfExists('communes');
        Schema::dropIfExists('dataset_metadata');
    }
};
