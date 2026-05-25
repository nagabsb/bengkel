<?php

namespace App\Http\Middleware;

use App\Services\Platform\PlatformBrandingService;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $branding = app(PlatformBrandingService::class)->sharedProps();
        $user = $request->user();

        return [
            ...parent::share($request),
            ...$branding,
            'auth' => [
                'user' => fn () => $user?->only('id', 'name', 'email', 'tenant_id'),
            ],
            'permissions' => fn () => $user
                ? $user->getAllPermissions()->pluck('name')->values()->all()
                : [],
            'flash' => [
                'status' => fn () => $request->session()->get('status'),
                'status_level' => fn () => $request->session()->get('status_level', 'success'),
                'payment_redirect_url' => fn () => $request->session()->get('payment_redirect_url'),
                'payment_snap_token' => fn () => $request->session()->get('payment_snap_token'),
                'estimate_approval_link' => fn () => $request->session()->get('estimate_approval_link'),
                'estimate_code' => fn () => $request->session()->get('estimate_code'),
                'estimate_status' => fn () => $request->session()->get('estimate_status'),
                'ai_estimate_draft' => fn () => $request->session()->get('ai_estimate_draft'),
                'ai_diagnosis_draft' => fn () => $request->session()->get('ai_diagnosis_draft'),
                'ai_prompt_test_result' => fn () => $request->session()->get('ai_prompt_test_result'),
            ],
            'ownerWorkshopSwitcher' => fn () => $request->attributes->get('owner_workshop_switcher'),
        ];
    }
}
