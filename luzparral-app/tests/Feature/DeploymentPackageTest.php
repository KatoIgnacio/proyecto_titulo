<?php

namespace Tests\Feature;

use Tests\TestCase;

class DeploymentPackageTest extends TestCase
{
    public function test_parra_environment_template_contains_no_credentials(): void
    {
        $contents = file_get_contents(base_path('deploy/parra/parra.env.example'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('APP_ENV=production', $contents);
        $this->assertStringContainsString('APP_DEBUG=false', $contents);
        $this->assertStringContainsString('APP_KEY=REEMPLAZAR_', $contents);
        $this->assertStringContainsString('DB_PASSWORD=REEMPLAZAR_', $contents);
        $this->assertStringNotContainsString('APP_KEY=base64:', $contents);
    }

    public function test_parra_deployment_separates_staging_and_production_ports(): void
    {
        $contents = file_get_contents(base_path('deploy/parra/deploy.sh'));

        $this->assertIsString($contents);
        $this->assertStringContainsString('host_port=2004', $contents);
        $this->assertStringContainsString('host_port=2003', $contents);
        $this->assertStringContainsString('--env-file "$environment_file"', $contents);
        $this->assertStringContainsString(':/var/www/html/storage', $contents);
        $this->assertStringContainsString('APP_URL debe utilizar el puerto', $contents);
        $this->assertStringContainsString('/up', $contents);
        $this->assertStringNotContainsString('artisan migrate', $contents);
    }

    public function test_image_transfer_requires_an_integrity_check(): void
    {
        $exportScript = file_get_contents(base_path('deploy/EXPORTAR_IMAGEN.ps1'));
        $loadScript = file_get_contents(base_path('deploy/parra/load-image.sh'));

        $this->assertIsString($exportScript);
        $this->assertIsString($loadScript);
        $this->assertStringContainsString('Get-FileHash -Algorithm SHA256', $exportScript);
        $this->assertStringContainsString('[System.IO.File]::WriteAllText', $exportScript);
        $this->assertStringContainsString('git status --porcelain', $exportScript);
        $this->assertStringContainsString('sha256sum --check', $loadScript);
        $this->assertStringContainsString('podman load --input', $loadScript);
    }

    public function test_deployment_files_are_not_copied_into_the_runtime_image(): void
    {
        foreach (['.dockerignore', '.containerignore'] as $ignoreFile) {
            $contents = file_get_contents(base_path($ignoreFile));

            $this->assertIsString($contents);
            $this->assertMatchesRegularExpression('/^artifacts$/m', $contents);
            $this->assertMatchesRegularExpression('/^deploy\/EXPORTAR_IMAGEN\.ps1$/m', $contents);
            $this->assertMatchesRegularExpression('/^deploy\/parra$/m', $contents);
        }
    }
}
