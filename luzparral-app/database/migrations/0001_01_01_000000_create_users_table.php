<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->string('email', 190)->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->string('role', 30)->default('viewer');
            $table->boolean('active')->default(true);
            $table->rememberToken();
            $table->timestamps();
            $table->index(['role', 'active'], 'idx_users_role_active');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('users');
    }
};
