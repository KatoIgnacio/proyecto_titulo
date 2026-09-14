<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class ResetUserPassword extends Command
{
    protected $signature = 'luzparral:reset-user-password
                            {email : Correo de la cuenta aprovisionada}
                            {--password-env=LUZPARRAL_RESET_PASSWORD : Variable de entorno que contiene la nueva contraseña}
                            {--confirm : Confirma la rotación y el cierre de sus sesiones}';

    protected $description = 'Rota una contraseña sin SMTP y sin exponerla como argumento o salida';

    public function handle(): int
    {
        if (! $this->option('confirm')) {
            $this->error('Operación cancelada: falta --confirm.');

            return self::FAILURE;
        }

        $email = strtolower((string) $this->argument('email'));
        if (str_ends_with($email, '@luzparral.example.invalid')) {
            $this->error('No se permite reactivar credenciales sintéticas mediante este comando.');

            return self::FAILURE;
        }

        $passwordEnvironment = (string) $this->option('password-env');
        if (! preg_match('/^LUZPARRAL_[A-Z0-9_]+_PASSWORD$/', $passwordEnvironment)) {
            $this->error('El nombre indicado en --password-env no es válido.');

            return self::FAILURE;
        }

        $password = getenv($passwordEnvironment);
        $validator = Validator::make(
            ['email' => $email, 'password' => $password === false ? null : $password],
            [
                'email' => ['required', 'email:rfc'],
                'password' => ['required', Password::min(12)->mixedCase()->numbers()->symbols()],
            ],
        );
        if ($validator->fails()) {
            $this->error('La cuenta o la contraseña privada no cumple la política requerida.');

            return self::FAILURE;
        }

        $user = User::query()->where('email', $email)->where('active', true)->first();
        if ($user === null) {
            $this->error('No existe una cuenta activa con ese correo.');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $password): void {
            $user->password = $password;
            $user->remember_token = Str::random(60);
            $user->save();

            if (Schema::hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $user->id)->delete();
            }
        });

        $this->info('Contraseña rotada y sesiones de la cuenta revocadas.');
        $this->line('La contraseña no fue mostrada ni almacenada por el comando.');

        return self::SUCCESS;
    }
}
