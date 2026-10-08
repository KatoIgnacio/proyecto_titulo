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
        $accounts = User::query()
            ->where('role', UserRole::Viewer)
            ->whereNotNull('expires_at')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (User $account): array => [
                'id' => $account->id,
                'name' => $account->name,
                'email' => $account->email,
                'active' => $account->isActive(),
                'expires_at' => $account->expires_at?->toIso8601String(),
                'created_at' => $account->created_at?->toIso8601String(),
            ]);

        return Inertia::render('GuestAccounts/Index', [
            'accounts' => $accounts,
            'credentials' => session('temporary_viewer_credentials'),
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
            'role' => UserRole::Viewer,
            'active' => true,
            'expires_at' => $expiresAt,
            'created_by' => $request->user()?->id,
            'remember_token' => null,
        ]);

        return redirect()
            ->route('temporary-viewer-accounts.index')
            ->with('success', 'Cuenta temporal de consulta creada. Copie la clave ahora; no volverá a mostrarse.')
            ->with('temporary_viewer_credentials', [
                'email' => $email,
                'password' => $password,
                'expires_at' => $expiresAt->toIso8601String(),
            ]);
    }

    public function destroy(User $account): RedirectResponse
    {
        abort_unless(
            $account->role === UserRole::Viewer && $account->expires_at !== null,
            HttpResponse::HTTP_NOT_FOUND,
        );

        DB::transaction(function () use ($account): void {
            $account->forceFill([
                'active' => false,
                'remember_token' => null,
            ])->save();

            if (DB::getSchemaBuilder()->hasTable('sessions')) {
                DB::table('sessions')->where('user_id', $account->id)->delete();
            }
        });

        return redirect()
            ->route('temporary-viewer-accounts.index')
            ->with('success', 'Cuenta temporal de consulta desactivada y sesiones revocadas.');
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
            $email = 'consulta-temporal-'.Str::lower(Str::random(12)).'@luzparral.example.invalid';
        } while (User::query()->where('email', $email)->exists());

        return $email;
    }
}
