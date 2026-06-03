<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Registration\RegistrationPolicyService;
use App\Support\Auth\PlatformIdentity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegistrationSettingsController extends Controller
{
    public function __construct(
        private readonly RegistrationPolicyService $registrationPolicyService,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json([
            'data' => $this->serializePolicy(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'registration_enabled' => ['required', 'boolean'],
            'approval_mode' => ['required', 'string', 'in:admin_approval,auto_approve'],
            'default_new_user_status' => ['required', 'string', 'in:pending_access,active,suspended,archived'],
            'notes' => ['nullable', 'string'],
        ]);

        /** @var PlatformIdentity $identity */
        $identity = $request->attributes->get('platform_identity');

        $policy = $this->registrationPolicyService->update($validated, $identity);

        return response()->json([
            'data' => [
                'registration_enabled' => $policy->registration_enabled,
                'approval_mode' => $policy->approval_mode,
                'default_new_user_status' => $policy->default_new_user_status,
                'notes' => $policy->notes,
            ],
        ]);
    }

    private function serializePolicy(): array
    {
        $policy = $this->registrationPolicyService->current();

        return [
            'registration_enabled' => $policy->registration_enabled,
            'approval_mode' => $policy->approval_mode,
            'default_new_user_status' => $policy->default_new_user_status,
            'notes' => $policy->notes,
        ];
    }
}
