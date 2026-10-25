<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $normalizedName = preg_replace('/\s+/u', ' ', (string) $request->input('name'));
        $request->merge(['name' => trim($normalizedName ?? '')]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80', 'regex:/^[\pL\pM]+(?:[ \'’\-][\pL\pM]+)*$/u'],
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            'name.regex' => 'El nombre solo puede contener letras, espacios, apóstrofes y guiones.',
        ]);

        if ($this->activeUserCount() >= $this->maxActiveUsers()) {
            throw ValidationException::withMessages([
                'email' => 'No hay cupos disponibles para nuevas cuentas de consulta.',
            ]);
        }

        $expiresAt = CarbonImmutable::now('America/Santiago')->addDays(7);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'email_verified_at' => now(),
            'password' => Hash::make($validated['password']),
            'role' => UserRole::Viewer,
            'active' => true,
            'expires_at' => $expiresAt,
            'created_by' => null,
            'remember_token' => null,
        ]);

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()
            ->route('dashboard')
            ->with('success', 'Cuenta temporal de consulta creada. Estará activa durante siete días.');
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
}
