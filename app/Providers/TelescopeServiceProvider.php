<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Laravel\Telescope\IncomingEntry;
use Laravel\Telescope\Telescope;
use Laravel\Telescope\TelescopeApplicationServiceProvider;

class TelescopeServiceProvider extends TelescopeApplicationServiceProvider
{
    public function register(): void
    {
        $this->hideSensitiveRequestDetails();

        $recordsAllEntries = $this->app->environment(['local', 'testing']);

        Telescope::filter(function (IncomingEntry $entry) use ($recordsAllEntries) {
            return $recordsAllEntries
                || $entry->isReportableException()
                || $entry->isFailedRequest()
                || $entry->isFailedJob()
                || $entry->isScheduledTask()
                || $entry->hasMonitoredTag();
        });
    }

    protected function hideSensitiveRequestDetails(): void
    {
        if ($this->app->environment(['local', 'testing'])) {
            return;
        }

        Telescope::hideRequestParameters([
            '_token',
            'password',
            'password_confirmation',
            'current_password',
            'new_password',
            'token',
            'keycloak_admin_client_secret',
            'platform_service_token',
            'supply_service_token',
            'calculation_service_token',
        ]);

        Telescope::hideRequestHeaders([
            'authorization',
            'cookie',
            'x-csrf-token',
            'x-xsrf-token',
        ]);
    }

    protected function gate(): void
    {
        Gate::define('viewTelescope', function (?User $user = null) {
            if ($this->app->environment(['local', 'testing'])) {
                return true;
            }

            $allowedEmails = array_values(array_filter(array_map(
                static fn (string $email): string => trim($email),
                explode(',', (string) config('telescope.allowed_emails', '')),
            )));

            $allowedIps = array_values(array_filter(array_map(
                static fn (string $ip): string => trim($ip),
                explode(',', (string) config('telescope.allowed_ips', '')),
            )));

            if ($user?->email !== null && in_array($user->email, $allowedEmails, true)) {
                return true;
            }

            $request = request();

            return $request !== null && in_array($request->ip(), $allowedIps, true);
        });
    }
}
