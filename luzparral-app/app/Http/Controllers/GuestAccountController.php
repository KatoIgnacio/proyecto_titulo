<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Http\Requests\StoreGuestAccountRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GuestAccountController extends Controller
{
    public function index(): Response
    {
        $guests = User::query()
            ->where('role', UserRole::Guest)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $guest): array => [
                'id' => $guest->id,
                'name' => $guest->name,
                'email' => $guest->email,
                'active' => $guest->isActive(),
                'expires_at' => $guest->expires_at?->toIso8601String(),
                'created_at' => $guest->created_at?->toIso8601String(),
            ]);

        return Inertia::render('GuestAccounts/Index', [
            'guests' => $guests,
            'credentials' => session('guest_credentials'),
            'maxActiveUsers' => $this->maxActiveUsers(),
            'activeUsers' => $this->activeUserCount(),
        ]);
    }

    public function store(StoreGuestAccountRequest $request): RedirectResponse
    {
        if ($this->activeUserCount() >= $this->maxActiveUsers()) {
            return back()->withErrors([
                'name' => 'Se alcanzó el máximo institucional de cuentas activas.',
            ]);
        }

        $validated = $request->validated();
        $password = Str::password(18, true, true, true, false);
        $expiresAt = CarbonImmutable::now('America/Santiago')
            ->addHours((int) $validated['duration_hours']);
        $email = $this->uniqueSyntheticEmail();

        User::query()->create([
            'name' => trim((string) $validated['name']),
            'email' => $email,
            'email_verified_at' => now(),
            'password' => $password,
            'role' => UserRole::Guest,
            'active' => true,
            'expires_at' => $expiresAt,
            'created_by' => $request->user()?->id,
            'remember_token' => null,
        ]);

        return redirect()
            ->route('guest-accounts.index')
            ->with('success', 'Cuenta de visita creada. Copie la clave ahora; no volverá a mostrarse.')
            ->with('guest_credentials', [
                'email' => $email,
                'password' => $password,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
    }

    public function destroy(User $guest): RedirectResponse
    {
        abort_unless($guest->role === UserRole::Guest, HttpResponse::HTTP_NOT_FOUND);

        DB::transaction(function () use ($guest): void {
            $guest->forceFill([
                'active' => false,
                'remember_token' => null,
            ])->save();

            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $guest->id)->delete();
            }
        });

        return redirect()
            ->route('guest-accounts.index')
            ->with('success', 'Cuenta de visita desactivada y sesiones revocadas.');
    }

    private function activeUserCount(): int
    {
        return User::query()
            ->where('active', true)
            ->where(function ($query): void {
                $query
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->count();
    }

    private function maxActiveUsers(): int
    {
        return min(10, max(5, (int) config('security.max_active_users', 10)));
    }

    private function uniqueSyntheticEmail(): string
    {
        do {
            $email = 'visita-'.Str::lower(Str::random(12)).'@luzparral.example.invalid';
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }
}
