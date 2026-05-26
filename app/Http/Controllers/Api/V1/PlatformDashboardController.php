<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\ServiceAccess;
use App\Models\User;
use App\Services\Registration\RegistrationPolicyService;
use Illuminate\Http\JsonResponse;

class PlatformDashboardController extends Controller
{
    public function __construct(
        private readonly RegistrationPolicyService $registrationPolicyService,
    ) {
    }

    public function __invoke(): JsonResponse
    {
        $registrationPolicy = $this->registrationPolicyService->current();

        $serviceDistribution = collect(['platform', 'supply', 'calculation'])
            ->map(function (string $serviceCode): array {
                $count = User::query()
                    ->whereHas('serviceAccesses', function ($query) use ($serviceCode): void {
                        $query->where('service_code', $serviceCode)
                            ->where('access_status', 'allowed');
                    })
                    ->count();

                return [
                    'service' => $serviceCode,
                    'label' => ucfirst($serviceCode),
                    'count' => $count,
                ];
            });

        $recentActivities = User::query()
            ->with('roles')
            ->orderByDesc('last_login_at')
            ->orderByDesc('updated_at')
            ->limit(5)
            ->get()
            ->map(function (User $user): array {
                $firstRole = $user->roles->pluck('name')->first();

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'category' => $firstRole ?? 'Menunggu Akses',
                    'category_color' => $firstRole !== null ? 'primary' : 'warning',
                    'updated_at_human' => optional($user->last_login_at ?? $user->updated_at)?->diffForHumans(),
                ];
            })
            ->values();

        $allowedUsers = User::query()
            ->whereHas('serviceAccesses', function ($query): void {
                $query->where('access_status', 'allowed');
            })
            ->count();

        return response()->json([
            'data' => [
                'summary' => [
                    'total_users' => User::query()->count(),
                    'role_count' => Role::query()->count(),
                    'permission_count' => Permission::query()->count(),
                    'pending_access_count' => User::query()->doesntHave('roles')->count(),
                    'allowed_user_count' => $allowedUsers,
                    'registration_enabled' => $registrationPolicy->registration_enabled,
                ],
                'chart' => [
                    'labels' => $serviceDistribution->pluck('label')->all(),
                    'data' => $serviceDistribution->pluck('count')->all(),
                ],
                'recent_activities' => $recentActivities,
                'service_matrix' => [
                    'platform' => ServiceAccess::query()->where('service_code', 'platform')->where('access_status', 'allowed')->count(),
                    'supply' => ServiceAccess::query()->where('service_code', 'supply')->where('access_status', 'allowed')->count(),
                    'calculation' => ServiceAccess::query()->where('service_code', 'calculation')->where('access_status', 'allowed')->count(),
                ],
            ],
        ]);
    }
}
