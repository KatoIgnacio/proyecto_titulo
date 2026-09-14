<?php

namespace Tests\Feature;

use Tests\TestCase;

class ContainerConfigurationTest extends TestCase
{
    public function test_container_definition_contains_the_required_runtime(): void
    {
        $contents = file_get_contents(base_path('Containerfile'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('FROM php:8.3-apache-bookworm AS runtime', $contents);
        $this->assertStringContainsString('pdo_mysql', $contents);
        $this->assertStringContainsString('intl', $contents);
        $this->assertStringContainsString('mbstring', $contents);
        $this->assertStringContainsString('EXPOSE 8080', $contents);
        $this->assertStringContainsString('http://127.0.0.1:8080/up', $contents);
    }

    public function test_container_context_excludes_secrets_and_local_dependencies(): void
    {
        foreach (['.dockerignore', '.containerignore'] as $ignoreFile) {
            $contents = file_get_contents(base_path($ignoreFile));

            $this->assertIsString($contents);
            $this->assertMatchesRegularExpression('/^\.env$/m', $contents);
            $this->assertMatchesRegularExpression('/^node_modules$/m', $contents);
            $this->assertMatchesRegularExpression('/^vendor$/m', $contents);
            $this->assertMatchesRegularExpression('/^CIOP_DATA$/m', $contents);
        }
    }

    public function test_container_does_not_run_database_migrations_automatically(): void
    {
        $entrypoint = file_get_contents(base_path('deploy/container-entrypoint.sh'));

        $this->assertIsString($entrypoint);
        $this->assertStringNotContainsString('migrate', $entrypoint);
    }
}
