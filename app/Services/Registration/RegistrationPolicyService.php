<?php

namespace App\Services\Registration;

use App\Models\AuditLog;
use App\Models\RegistrationPolicy;
use App\Services\Identity\KeycloakAdminProvisioner;
use App\Support\Auth\PlatformIdentity;

class RegistrationPolicyService
{
    public function __construct(
        private readonly KeycloakAdminProvisioner $keycloakAdminProvisioner,
    ) {}

    public function current(): RegistrationPolicy
    {
        return RegistrationPolicy::query()->firstOrCreate([], [
            'registration_enabled' => false,
            'approval_mode' => 'admin_approval',
            'default_new_user_status' => 'pending_access',
            'notes' => null,
        ]);
    }

    public function update(array $attributes, PlatformIdentity $identity): RegistrationPolicy
    {
        $policy = $this->current();
        $policy->fill($attributes);
        $policy->save();

        $this->keycloakAdminProvisioner->setRealmRegistrationEnabled((bool) $policy->registration_enabled);

        AuditLog::query()->create([
            'actor_subject' => $identity->subject,
            'action' => 'registration_policy_updated',
            'target_type' => 'registration_policy',
            'target_id' => (string) $policy->id,
            'payload' => $policy->only([
                'registration_enabled',
                'approval_mode',
                'default_new_user_status',
                'notes',
            ]),
        ]);

        return $policy->refresh();
    }
}
