<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use JsonException;
use Throwable;

class ProvisionBaseUsers extends Command
{
    protected $signature = 'luzparral:provision-users
                            {--file= : Archivo JSON privado con las cinco cuentas y nombres de variables de contraseña}
                            {--update-existing : Permite actualizar cuentas reales que ya existen}
                            {--confirm : Confirma el aprovisionamiento y la desactivación de cuentas sintéticas}';

    protected $description = 'Aprovisiona las cinco cuentas base sin recibir ni mostrar contraseñas en la línea de comandos';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Operación cancelada: falta --confirm.');

            return self::FAILURE;
        }

        try {
            $accounts = $this->loadAndValidateAccounts();
            $emails = array_column($accounts, 'email');
            $existingEmails = User::query()->whereIn('email', $emails)->pluck('email')->all();

            if ($existingEmails !== [] && ! $this->option('update-existing')) {
                throw new \RuntimeException('Ya existen cuentas del archivo. Revise el origen y use --update-existing solo si desea rotarlas.');
            }

            $maxActiveUsers = min(10, max(5, (int) config('security.max_active_users', 10)));
            $unmanagedActiveUsers = User::query()
                ->where('active', true)
                ->whereNotIn('email', $emails)
                ->where('email', 'not like', '%@luzparral.example.invalid')
                ->count();

            if ($unmanagedActiveUsers + count($accounts) > $maxActiveUsers) {
                throw new \RuntimeException("El aprovisionamiento superaría el máximo de {$maxActiveUsers} cuentas activas.");
            }

            DB::transaction(function () use ($accounts): void {
                $syntheticUserIds = User::query()
                    ->where('email', 'like', '%@luzparral.example.invalid')
                    ->pluck('id');

                if ($syntheticUserIds->isNotEmpty()) {
                    User::query()
                        ->whereKey($syntheticUserIds)
                        ->update(['active' => false, 'remember_token' => null]);

                    if (Schema::hasTable('sessions')) {
                        DB::table('sessions')->whereIn('user_id', $syntheticUserIds)->delete();
                    }
                }

                foreach ($accounts as $account) {
                    $user = User::query()->firstOrNew(['email' => $account['email']]);
                    $user->name = $account['name'];
                    $user->role = $account['role'];
                    $user->active = true;
                    $user->password = $account['password'];
                    $user->email_verified_at = now();
                    $user->remember_token = Str::random(60);
                    $user->save();
                }
            });
        } catch (JsonException $error) {
            $this->error('El archivo de cuentas no contiene JSON válido: '.$error->getMessage());

            return self::FAILURE;
        } catch (Throwable $error) {
            $this->error($error->getMessage());

            return self::FAILURE;
        }

        $this->info('Cinco cuentas base aprovisionadas.');
        $this->line('Las cuentas sintéticas quedaron desactivadas y sus sesiones fueron revocadas.');
        $this->line('No se mostraron ni almacenaron contraseñas en archivos del proyecto.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, array{name: string, email: string, role: string, password: string}>
     *
     * @throws JsonException
     */
    private function loadAndValidateAccounts(): array
    {
        $fileOption = $this->option('file');
        if (! is_string($fileOption) || trim($fileOption) === '') {
            throw new \RuntimeException('Debe indicar --file con un JSON privado ubicado fuera del repositorio.');
        }

        $filePath = realpath($fileOption);
        if ($filePath === false || ! is_file($filePath)) {
            throw new \RuntimeException('No se encontró el archivo privado de cuentas.');
        }

        $repositoryPath = realpath(base_path());
        if ($repositoryPath === false) {
            throw new \RuntimeException('No fue posible resolver la ruta del repositorio.');
        }

        $normalizedFile = strtolower(str_replace('\\', '/', $filePath));
        $normalizedRepository = rtrim(strtolower(str_replace('\\', '/', $repositoryPath)), '/').'/';
        if (str_starts_with($normalizedFile, $normalizedRepository)) {
            throw new \RuntimeException('El archivo con la definición de cuentas debe permanecer fuera del repositorio.');
        }

        $contents = file_get_contents($filePath);
        if ($contents === false) {
            throw new \RuntimeException('No fue posible leer el archivo privado de cuentas.');
        }

        $records = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($records) || ! array_is_list($records) || count($records) !== 5) {
            throw new \RuntimeException('El archivo debe contener exactamente cinco cuentas.');
        }

        $accounts = [];
        $passwordFingerprints = [];
        foreach ($records as $index => $record) {
            $validator = Validator::make((array) $record, [
                'name' => ['required', 'string', 'max:255'],
                'email' => ['required', 'string', 'lowercase', 'email:rfc', 'max:255'],
                'role' => ['required', Rule::enum(UserRole::class)],
                'password_env' => ['required', 'string', 'regex:/^LUZPARRAL_USER_[A-Z0-9_]+_PASSWORD$/'],
            ]);

            if ($validator->fails()) {
                throw new \RuntimeException('La cuenta '.($index + 1).' no cumple el formato requerido: '.$validator->errors()->first());
            }

            $validated = $validator->validated();
            if (app()->isProduction() && preg_match('/\.(example|invalid|test)$/', $validated['email']) === 1) {
                throw new \RuntimeException('En producción se deben reemplazar todos los dominios reservados del ejemplo.');
            }

            $password = getenv($validated['password_env']);
            if (! is_string($password) || $password === '') {
                throw new \RuntimeException("Falta la variable privada {$validated['password_env']}.");
            }

            $passwordValidator = Validator::make(
                ['password' => $password],
                ['password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()]],
            );
            if ($passwordValidator->fails()) {
                throw new \RuntimeException("La variable {$validated['password_env']} no cumple la política de contraseña.");
            }

            $fingerprint = hash('sha256', $password);
            if (in_array($fingerprint, $passwordFingerprints, true)) {
                throw new \RuntimeException('Cada cuenta debe utilizar una contraseña distinta.');
            }
            $passwordFingerprints[] = $fingerprint;

            $accounts[] = [
                'name' => trim($validated['name']),
                'email' => strtolower($validated['email']),
                'role' => $validated['role'],
                'password' => $password,
            ];
        }

        if (count(array_unique(array_column($accounts, 'email'))) !== 5) {
            throw new \RuntimeException('Las cinco cuentas deben utilizar correos distintos.');
        }

        $roleCounts = array_count_values(array_column($accounts, 'role'));
        foreach ([
            UserRole::Admin->value => 1,
            UserRole::Supervisor->value => 1,
            UserRole::Operator->value => 2,
            UserRole::Viewer->value => 1,
        ] as $role => $expected) {
            if (($roleCounts[$role] ?? 0) !== $expected) {
                throw new \RuntimeException('La base requiere 1 administrador, 1 supervisor, 2 operadores y 1 usuario de consulta.');
            }
        }

        return $accounts;
    }
}
