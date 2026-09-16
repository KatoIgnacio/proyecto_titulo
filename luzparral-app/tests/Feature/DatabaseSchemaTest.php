<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DatabaseSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_domain_tables_are_created(): void
    {
        foreach ([
            'dataset_metadata',
            'communes',
            'feeders',
            'supply_points',
            'import_batches',
            'import_errors',
            'contingencies',
            'contingency_impacts',
            'contingency_history',
            'field_reports',
            'field_report_attachments',
        ] as $table) {
            $this->assertTrue(Schema::hasTable($table), "Falta la tabla {$table}");
        }
    }

    public function test_users_have_a_role_and_active_state(): void
    {
        $user = User::factory()->create();

        $this->assertSame(UserRole::Viewer, $user->role);
        $this->assertTrue($user->active);
    }
}
