<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user,
                'permissions' => [
                    'viewReports' => $user?->role?->canViewReports() ?? false,
                    'updateContingencies' => $user?->role?->canUpdateContingencies() ?? false,
                    'registerFieldReports' => $user?->role?->canRegisterFieldReports() ?? false,
                    'importContingencies' => $user?->role?->canImportContingencies() ?? false,
                ],
            ],
            'flash' => [
                'success' => fn (): ?string => $request->session()->get('success'),
            ],
        ];
    }
}
