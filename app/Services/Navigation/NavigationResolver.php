<?php

namespace App\Services\Navigation;

use App\Models\User;

class NavigationResolver
{
    public function resolve(User $user): array
    {
        $services = $user->serviceAccesses()
            ->orderBy('service_code')
            ->get()
            ->map(fn ($access) => [
                'service' => $access->service_code,
                'label' => ucfirst($access->service_code),
                'access_status' => $access->access_status,
                'entry_url' => null,
            ])
            ->values()
            ->all();

        $allowedServices = array_values(array_map(
            fn ($service) => $service['service'],
            array_filter($services, fn ($service) => $service['access_status'] === 'allowed'),
        ));

        $blockedServices = array_values(array_map(
            fn ($service) => $service['service'],
            array_filter($services, fn ($service) => $service['access_status'] === 'blocked'),
        ));

        $pendingServices = array_values(array_map(
            fn ($service) => $service['service'],
            array_filter($services, fn ($service) => $service['access_status'] === 'pending'),
        ));

        $pendingAccess = $allowedServices === [];
        $preferredApp = $this->resolvePreferredApp($user, $allowedServices);

        return [
            'services' => $services,
            'preferred_app' => $preferredApp,
            'preferred_route' => $this->resolvePreferredRoute($pendingAccess, $preferredApp),
            'pending_access' => $pendingAccess,
            'allowed_services' => $allowedServices,
            'blocked_services' => $blockedServices,
            'pending_services' => $pendingServices,
        ];
    }

    private function resolvePreferredApp(User $user, array $allowedServices): ?string
    {
        $preferredApp = trim((string) ($user->preferred_app ?? ''));

        if ($preferredApp !== '' && in_array($preferredApp, $allowedServices, true)) {
            return $preferredApp;
        }

        if (in_array('platform', $allowedServices, true)) {
            return 'platform';
        }

        return $allowedServices[0] ?? null;
    }

    private function resolvePreferredRoute(bool $pendingAccess, ?string $preferredApp): string
    {
        if ($pendingAccess) {
            return 'platform.access.pending';
        }

        return match ($preferredApp) {
            'supply' => 'service.supply',
            'calculation' => 'service.calculation',
            default => 'platform.dashboard',
        };
    }
}
